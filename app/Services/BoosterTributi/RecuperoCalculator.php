<?php

namespace App\Services\BoosterTributi;

use Illuminate\Support\Facades\DB;

/**
 * Motore di calcolo del recupero economico, condiviso dalle due analisi
 * (differenze mq TARI e differenze componenti familiari): l'unica differenza tra
 * i due usi è quale "differenza" si passa e se si usa la quota fissa o variabile
 * della tariffa (vedi ISTRUZIONI Monter, sezioni "DIFFERENZE MQ TARI" e
 * "DIFFERENZE COMPONENTI FAMILIARI").
 *
 * Logica per anno recuperabile (da anno(data_inizio_validita) a anno corrente,
 * max 5 anni), vedi calcolaPerAnno():
 *   dovuto_anno = differenza * tariffa(anno, quota) * (1 - riduzione%), in pro-rata
 *                 giornaliero nell'anno di inizio validità
 *   totale_anno = dovuto_anno + sanzione 30% + interessi legali cumulati
 *
 * Le tre dipendenze sono iniettate come closure (non DB::query dirette) così che
 * la logica di calcolo sia testabile con fixture in memoria (vedi
 * tests/Unit/BoosterTributi/RecuperoCalculatorTest.php) senza bisogno di un
 * Postgres raggiungibile.
 */
class RecuperoCalculator
{
    private const SANZIONE_PERCENTUALE = 0.30;

    private const MAX_ANNI_ACCERTAMENTO = 5;

    private const GIORNI_ANNO = 365;

    private array $cacheTariffario = [];

    private array $cacheInteressi = [];

    /**
     * @param  \Closure(int,string,string):?float  $tariffaLookup  ($anno, $codiceTariffa, $tipoQuota) -> tariffa o null se mancante
     * @param  \Closure(int,string,string):float  $riduzioneLookup  ($anno, $nomeRiduzione, $tipoQuota) -> percentuale 0-1 (0 se non applicabile/non trovata)
     * @param  \Closure(int):float  $interesseLookup  ($anno) -> percentuale legale di quell'anno, 0-1
     */
    public function __construct(
        private \Closure $tariffaLookup,
        private \Closure $riduzioneLookup,
        private \Closure $interesseLookup,
        private ?int $annoCorrente = null
    ) {}

    /**
     * Istanza pronta all'uso nei Job, con lookup basati sulle tabelle bt_tariffario /
     * bt_riduzioni (connessione 'pgsql', già puntata al comune corrente) e
     * bt_interessi_legali (connessione 'info-generali').
     */
    public static function conConnessioniDefault(): self
    {
        return new self(
            function (int $anno, string $codiceTariffa, string $tipoQuota): ?float {
                $riga = DB::connection('pgsql')->table('bt_tariffario')
                    ->where('anno', $anno)
                    ->where('codice_tariffa', $codiceTariffa)
                    ->first();

                if ($riga === null) {
                    return null;
                }

                $campo = $tipoQuota === 'variabile' ? 'tariffa_variabile' : 'tariffa_fissa';

                return $riga->{$campo} !== null ? (float) $riga->{$campo} : null;
            },
            function (int $anno, string $nomeRiduzione, string $tipoQuota): float {
                $riga = DB::connection('pgsql')->table('bt_riduzioni')
                    ->where('anno', $anno)
                    ->whereRaw('LOWER(TRIM(descrizione)) = ?', [mb_strtolower(trim($nomeRiduzione))])
                    ->first();

                if ($riga === null || $riga->percentuale === null) {
                    return 0.0;
                }

                $applicazione = mb_strtolower((string) $riga->applicazione);
                $compatibile = str_contains($applicazione, $tipoQuota)
                    || (str_contains($applicazione, 'fissa') && str_contains($applicazione, 'variabile'));

                return $compatibile ? ((float) $riga->percentuale) / 100 : 0.0;
            },
            function (int $anno): float {
                $riga = DB::connection('info-generali')->table('bt_interessi_legali')
                    ->where('anno', $anno)
                    ->first();

                return $riga !== null ? ((float) $riga->percentuale) / 100 : 0.0;
            }
        );
    }

    /**
     * @param  array<int,string|null>  $riduzioniApplicate  descrizioni riduzione (File 2, colonne riduzione_1/2/3)
     * @return array{dettaglio_anni: array<int, array<string, mixed>>, totale_recuperabile: float, totale_con_sanzioni_interessi: float, anni_mancanti: array<int>}
     */
    public function calcola(
        float $differenza,
        ?string $codiceTariffa,
        ?string $dataInizioValidita,
        string $tipoQuota,
        array $riduzioniApplicate = []
    ): array {
        return $this->calcolaPerAnno($dataInizioValidita, function (int $anno) use ($differenza, $codiceTariffa, $tipoQuota, $riduzioniApplicate): ?array {
            $tariffa = $codiceTariffa ? $this->tariffaAnno($anno, $codiceTariffa, $tipoQuota) : null;
            if ($tariffa === null) {
                return null;
            }

            $riduzionePerc = $this->riduzionePercentuale($anno, $riduzioniApplicate, $tipoQuota);

            return [$differenza * $tariffa * (1 - $riduzionePerc), $riduzionePerc];
        });
    }

    /**
     * Variante di calcola() per le "DIFFERENZE COMPONENTI FAMILIARI": la tariffa
     * variabile NON è lineare per componente (File 3 è una tabella a scaglioni,
     * es. 1 componente 156€, 2 componenti 308€, 3 componenti 398€...), quindi il
     * dovuto di un anno è la DIFFERENZA tra lo scaglione reale e quello dichiarato,
     * non "numero componenti in più × tariffa di un singolo scaglione".
     *
     * La riduzione "Unico occupante" applicata nel dichiarato (1 componente) non è
     * più applicabile quando i componenti reali sono >1: viene esclusa dal ricalcolo
     * per nome (case-insensitive), le altre riduzioni dichiarate (es. Compostaggio)
     * restano valide. Vedi ISTRUZIONI Monter e annotazioni cliente 2026-09-27.
     *
     * @param  array<int,string|null>  $riduzioniApplicate  descrizioni riduzione dichiarate (File 2)
     * @return array{dettaglio_anni: array<int, array<string, mixed>>, totale_recuperabile: float, totale_con_sanzioni_interessi: float, anni_mancanti: array<int>}
     */
    public function calcolaComponenti(
        ?string $codiceTariffaDichiarato,
        int $componentiReali,
        ?string $dataInizioValidita,
        array $riduzioniApplicate = []
    ): array {
        $categoria = $codiceTariffaDichiarato !== null ? explode('.', $codiceTariffaDichiarato, 2)[0] : null;
        $riduzioniRicalcolo = $this->escludiUnicoOccupante($riduzioniApplicate, $componentiReali);

        return $this->calcolaPerAnno($dataInizioValidita, function (int $anno) use ($codiceTariffaDichiarato, $categoria, $componentiReali, $riduzioniRicalcolo): ?array {
            $tariffaDichiarata = $codiceTariffaDichiarato ? $this->tariffaAnno($anno, $codiceTariffaDichiarato, 'variabile') : null;
            $tariffaReale = $categoria !== null ? $this->tariffaScaglioneComponenti($anno, $categoria, $componentiReali) : null;
            if ($tariffaDichiarata === null || $tariffaReale === null) {
                return null;
            }

            $riduzionePerc = $this->riduzionePercentuale($anno, $riduzioniRicalcolo, 'variabile');

            return [($tariffaReale - $tariffaDichiarata) * (1 - $riduzionePerc), $riduzionePerc];
        });
    }

    /**
     * Ciclo comune ai due calcoli (annotazioni cliente 2026-10-05, punti 1/2/6).
     *
     * Per ogni anno recuperabile (da anno(data_inizio_validita) all'anno corrente,
     * max 5 anni indietro):
     *   dovuto  = dovuto annuo pieno * giorni coperti / 365 (pro-rata solo
     *             nell'anno di inizio validità: dal 01/12 -> 31/365)
     *   sanzione  = 30% del dovuto
     *   interessi = dovuto * somma tassi legali dall'anno dovuto all'anno corrente
     *               INCLUSO (es. 2022 -> 2022+2023+2024+2025+2026 = 12,35%), con il
     *               tasso dell'anno di inizio validità anch'esso in pro-rata
     *   totale    = dovuto + sanzione + interessi
     * Sanzioni e interessi si applicano anche all'anno corrente: il cliente vuole
     * per ogni annualità la colonna "con sanzioni e interessi" accanto al dovuto.
     *
     * @param  \Closure(int):?array{0: float, 1: float}  $dovutoAnnuo  anno -> [dovuto annuo pieno, riduzione applicata] o null se manca il tariffario
     */
    private function calcolaPerAnno(?string $dataInizioValidita, \Closure $dovutoAnnuo): array
    {
        $annoCorrente = $this->annoCorrente ?? (int) now()->format('Y');
        $inizio = $dataInizioValidita ? strtotime($dataInizioValidita) : false;
        $annoInizio = $inizio !== false ? (int) date('Y', $inizio) : $annoCorrente;
        $primoAnno = max($annoInizio, $annoCorrente - self::MAX_ANNI_ACCERTAMENTO);

        // Frazione d'anno coperta nell'anno di inizio validità: giorni dal giorno di
        // inizio al 31/12 inclusi, diviso 365 (come richiesto dal cliente, max 1
        // negli anni bisestili). Se l'inizio è prima della finestra, anno pieno.
        $quotaPrimoAnno = 1.0;
        $giorniPrimoAnno = 365;
        if ($inizio !== false && $annoInizio === $primoAnno) {
            $giorniPrimoAnno = (int) date('z', mktime(0, 0, 0, 12, 31, $annoInizio)) - (int) date('z', $inizio) + 1;
            $quotaPrimoAnno = min($giorniPrimoAnno / self::GIORNI_ANNO, 1.0);
        }

        $dettaglioAnni = [];
        $anniMancanti = [];
        $totale = 0.0;
        $totaleConSanzioni = 0.0;

        for ($anno = $primoAnno; $anno <= $annoCorrente; $anno++) {
            $annuo = $dovutoAnnuo($anno);

            if ($annuo === null) {
                $anniMancanti[] = $anno;
                $dettaglioAnni[$anno] = ['errore' => 'tariffario mancante'];

                continue;
            }

            [$dovutoPieno, $riduzionePerc] = $annuo;
            $quota = $anno === $primoAnno ? $quotaPrimoAnno : 1.0;
            $dovutoAnno = round($dovutoPieno * $quota, 2);

            $sanzione = round($dovutoAnno * self::SANZIONE_PERCENTUALE, 2);
            $interessi = round($dovutoAnno * $this->interessiCumulati($anno, $annoCorrente, $quota), 2);
            $totaleAnno = round($dovutoAnno + $sanzione + $interessi, 2);

            $dettaglioAnni[$anno] = [
                'giorni' => $anno === $primoAnno ? min($giorniPrimoAnno, self::GIORNI_ANNO) : self::GIORNI_ANNO,
                'dovuto' => $dovutoAnno,
                'sanzione' => $sanzione,
                'interessi' => $interessi,
                'riduzione_applicata' => $riduzionePerc,
                'totale' => $totaleAnno,
            ];

            $totale += $dovutoAnno;
            $totaleConSanzioni += $totaleAnno;
        }

        return [
            'dettaglio_anni' => $dettaglioAnni,
            'totale_recuperabile' => round($totale, 2),
            'totale_con_sanzioni_interessi' => round($totaleConSanzioni, 2),
            'anni_mancanti' => $anniMancanti,
        ];
    }

    /**
     * Scaglione più vicino ai componenti reali per quella categoria/anno: se i
     * componenti reali superano lo scaglione massimo importato (es. "6 o più
     * componenti"), scende finché non trova uno scaglione presente in tariffario.
     */
    private function tariffaScaglioneComponenti(int $anno, string $categoria, int $componentiReali): ?float
    {
        for ($n = max($componentiReali, 1); $n >= 1; $n--) {
            $tariffa = $this->tariffaAnno($anno, $categoria.'.'.$n, 'variabile');
            if ($tariffa !== null) {
                return $tariffa;
            }
        }

        return null;
    }

    /**
     * @param  array<int,string|null>  $riduzioniApplicate
     * @return array<int,string|null>
     */
    private function escludiUnicoOccupante(array $riduzioniApplicate, int $componentiReali): array
    {
        if ($componentiReali <= 1) {
            return $riduzioniApplicate;
        }

        return array_map(
            fn (?string $nome) => ($nome !== null && str_contains(mb_strtolower(trim($nome)), 'unico occupante')) ? null : $nome,
            $riduzioniApplicate
        );
    }

    private function tariffaAnno(int $anno, string $codiceTariffa, string $tipoQuota): ?float
    {
        $chiave = $anno.'|'.$codiceTariffa.'|'.$tipoQuota;

        if (! array_key_exists($chiave, $this->cacheTariffario)) {
            $this->cacheTariffario[$chiave] = ($this->tariffaLookup)($anno, $codiceTariffa, $tipoQuota);
        }

        return $this->cacheTariffario[$chiave];
    }

    /**
     * @param  array<int,string|null>  $riduzioniApplicate
     */
    private function riduzionePercentuale(int $anno, array $riduzioniApplicate, string $tipoQuota): float
    {
        $nomi = array_values(array_filter(array_map(
            static fn (?string $nome) => $nome !== null ? trim($nome) : null,
            $riduzioniApplicate
        )));
        if ($nomi === []) {
            return 0.0;
        }

        $totalePerc = 0.0;
        foreach ($nomi as $nome) {
            $totalePerc += ($this->riduzioneLookup)($anno, $nome, $tipoQuota);
        }

        return min($totalePerc, 1.0);
    }

    /**
     * Somma dei tassi legali dall'anno dovuto all'anno corrente incluso; il tasso
     * dell'anno dovuto è moltiplicato per $quotaPrimoAnno (pro-rata giornaliero
     * quando l'annualità è quella di inizio validità).
     */
    private function interessiCumulati(int $annoDovuto, int $annoCorrente, float $quotaPrimoAnno = 1.0): float
    {
        $totale = 0.0;

        for ($anno = $annoDovuto; $anno <= $annoCorrente; $anno++) {
            if (! array_key_exists($anno, $this->cacheInteressi)) {
                $this->cacheInteressi[$anno] = ($this->interesseLookup)($anno);
            }

            $totale += $this->cacheInteressi[$anno] * ($anno === $annoDovuto ? $quotaPrimoAnno : 1.0);
        }

        return $totale;
    }
}
