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
 *   totale_anno = dovuto_anno + sanzione 30% + interessi legali cumulati (solo
 *                 anni chiusi; l'anno in corso è solo ruolo)
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
        return $this->calcolaPerAnno($dataInizioValidita, function (int $anno, string $dal, int $giorni, int $divisore) use ($differenza, $codiceTariffa, $tipoQuota, $riduzioniApplicate): ?array {
            $tariffa = $codiceTariffa ? $this->tariffaAnno($anno, $codiceTariffa, $tipoQuota) : null;
            if ($tariffa === null) {
                return null;
            }

            $riduzionePerc = $this->riduzionePercentuale($anno, $riduzioniApplicate, $tipoQuota);

            return [$differenza * $tariffa * (1 - $riduzionePerc) * $giorni / $divisore, $riduzionePerc, []];
        });
    }

    /**
     * Variante di calcola() per le "DIFFERENZE COMPONENTI FAMILIARI".
     *
     * - La tariffa variabile NON è lineare per componente (File 3 è una tabella a
     *   scaglioni, es. 1 componente 156€, 2 componenti 308€, 3 componenti 398€...):
     *   il dovuto è la DIFFERENZA tra lo scaglione reale e quello dichiarato.
     * - I componenti reali sono verificati giorno per giorno (istruzioni cliente
     *   2026-10-08): ogni componente conta solo dalla sua data di ingresso nel
     *   nucleo, quindi il periodo viene spezzato nei tratti in cui il numero
     *   cambia (es. +1 dal 2018 e +1 dal 2024: dal 2018 si recupera lo scaglione
     *   +1, dal 2024 lo scaglione +2). Uscite/decessi passati non sono noti dal
     *   File 6: i componenti attuali senza data di ingresso contano sempre.
     * - La riduzione "Unico occupante" non è più applicabile quando i componenti
     *   reali sono >1: esclusa per nome (case-insensitive), le altre riduzioni
     *   dichiarate (es. Compostaggio) restano valide.
     *
     * @param  array<int,string>  $dateIngresso  data di ingresso (Y-m-d) di ciascun componente attuale che ne ha una
     * @param  array<int,string|null>  $riduzioniApplicate  descrizioni riduzione dichiarate (File 2)
     * @return array{dettaglio_anni: array<int, array<string, mixed>>, totale_recuperabile: float, totale_con_sanzioni_interessi: float, anni_mancanti: array<int>, data_inizio_recupero: ?string}
     */
    public function calcolaComponenti(
        ?string $codiceTariffaDichiarato,
        int $componentiDichiarati,
        int $componentiReali,
        array $dateIngresso,
        ?string $dataInizioValidita,
        array $riduzioniApplicate = []
    ): array {
        $categoria = $codiceTariffaDichiarato !== null ? explode('.', $codiceTariffaDichiarato, 2)[0] : null;
        $dateIngresso = array_values(array_filter(array_map(fn ($d) => $d ? substr((string) $d, 0, 10) : null, $dateIngresso)));
        $dataInizioRecupero = null;

        // Componenti presenti nel giorno $giorno: quelli attuali meno chi è entrato dopo.
        $presentiIl = function (string $giorno) use ($componentiReali, $dateIngresso): int {
            $entratiDopo = count(array_filter($dateIngresso, fn ($d) => $d > $giorno));

            return max($componentiReali - $entratiDopo, 1);
        };

        $esito = $this->calcolaPerAnno($dataInizioValidita, function (int $anno, string $dal, int $giorni, int $divisore) use ($codiceTariffaDichiarato, $categoria, $componentiDichiarati, $riduzioniApplicate, $dateIngresso, $presentiIl, &$dataInizioRecupero): ?array {
            $tariffaDichiarata = $codiceTariffaDichiarato ? $this->tariffaAnno($anno, $codiceTariffaDichiarato, 'variabile') : null;
            if ($tariffaDichiarata === null || $categoria === null) {
                return null;
            }

            // Tratti [inizio, fine) del periodo separati dalle date di ingresso.
            $fineAnno = sprintf('%04d-12-31', $anno);
            $tagli = array_filter($dateIngresso, fn ($d) => $d > $dal && $d <= $fineAnno);
            $tagli = array_values(array_unique(array_merge([$dal], $tagli)));
            sort($tagli);

            $dovuto = 0.0;
            $riduzionePerc = 0.0;
            foreach ($tagli as $i => $inizioTratto) {
                $fineTratto = $tagli[$i + 1] ?? date('Y-m-d', strtotime($fineAnno.' +1 day'));
                $giorniTratto = (int) round((strtotime($fineTratto) - strtotime($inizioTratto)) / 86400);
                $presenti = $presentiIl($inizioTratto);

                if ($presenti <= $componentiDichiarati) {
                    continue;
                }

                $tariffaReale = $this->tariffaScaglioneComponenti($anno, $categoria, $presenti);
                if ($tariffaReale === null) {
                    return null;
                }

                $riduzionePerc = $this->riduzionePercentuale($anno, $this->escludiUnicoOccupante($riduzioniApplicate, $presenti), 'variabile');
                $dovuto += ($tariffaReale - $tariffaDichiarata) * (1 - $riduzionePerc) * $giorniTratto / $divisore;
                $dataInizioRecupero ??= $inizioTratto;
            }

            return [$dovuto, $riduzionePerc, ['componenti_reali' => $presentiIl($fineAnno)]];
        });

        return $esito + ['data_inizio_recupero' => $dataInizioRecupero];
    }

    /**
     * "FAMIGLIE NON PRESENTI IN TARI": il nucleo non paga nulla, quindi si
     * recupera l'intera tariffa domestica dello scaglione dei componenti reali:
     * quota fissa * mq (80% superficie catastale) + quota variabile, entrambe
     * della sottocategoria "N componenti" (istruzioni cliente 2026-10-08). Come
     * per le differenze componenti, il numero è verificato giorno per giorno
     * dalle date di ingresso; ai nuclei di 1 componente si applica la riduzione
     * "unico occupante".
     *
     * @param  array<int,string>  $dateIngresso  data di ingresso (Y-m-d) di ciascun componente
     * @return array{dettaglio_anni: array<int, array<string, mixed>>, totale_recuperabile: float, totale_con_sanzioni_interessi: float, anni_mancanti: array<int>}
     */
    public function calcolaUtenzaDomestica(
        string $categoriaDomestica,
        float $mq,
        int $componentiReali,
        array $dateIngresso,
        ?string $dataInizio,
        ?string $riduzioneUnicoOccupante = null
    ): array {
        $dateIngresso = array_values(array_filter(array_map(fn ($d) => $d ? substr((string) $d, 0, 10) : null, $dateIngresso)));
        $presentiIl = function (string $giorno) use ($componentiReali, $dateIngresso): int {
            return max($componentiReali - count(array_filter($dateIngresso, fn ($d) => $d > $giorno)), 1);
        };

        return $this->calcolaPerAnno($dataInizio, function (int $anno, string $dal, int $giorni, int $divisore) use ($categoriaDomestica, $mq, $dateIngresso, $presentiIl, $riduzioneUnicoOccupante): ?array {
            $fineAnno = sprintf('%04d-12-31', $anno);
            $tagli = array_filter($dateIngresso, fn ($d) => $d > $dal && $d <= $fineAnno);
            $tagli = array_values(array_unique(array_merge([$dal], $tagli)));
            sort($tagli);

            $dovuto = 0.0;
            $riduzionePerc = 0.0;
            foreach ($tagli as $i => $inizioTratto) {
                $fineTratto = $tagli[$i + 1] ?? date('Y-m-d', strtotime($fineAnno.' +1 day'));
                $giorniTratto = (int) round((strtotime($fineTratto) - strtotime($inizioTratto)) / 86400);
                $presenti = $presentiIl($inizioTratto);

                $fissa = $this->tariffaScaglioneComponenti($anno, $categoriaDomestica, $presenti, 'fissa');
                $variabile = $this->tariffaScaglioneComponenti($anno, $categoriaDomestica, $presenti, 'variabile');
                if ($fissa === null || $variabile === null) {
                    return null;
                }

                $riduzioni = ($presenti === 1 && $riduzioneUnicoOccupante !== null) ? [$riduzioneUnicoOccupante] : [];
                $riduzioneFissa = $this->riduzionePercentuale($anno, $riduzioni, 'fissa');
                $riduzionePerc = $this->riduzionePercentuale($anno, $riduzioni, 'variabile');

                $annuo = $fissa * $mq * (1 - $riduzioneFissa) + $variabile * (1 - $riduzionePerc);
                $dovuto += $annuo * $giorniTratto / $divisore;
            }

            return [$dovuto, $riduzionePerc, ['componenti_reali' => $presentiIl($fineAnno)]];
        });
    }

    /**
     * Ciclo comune ai due calcoli (annotazioni cliente 2026-10-05 e 2026-10-08).
     *
     * Anni recuperabili: gli ultimi 5 più quello in corso (nel 2026: 2021-2026;
     * dal 2027 la finestra scorre da sola a 2022-2027), a partire dall'anno di
     * inizio validità della scheda TARI.
     *   dovuto    = dovuto annuo in pro-rata giornaliero: nel primo anno contano
     *               solo i giorni dalla data di inizio (dal 01/12 -> 31/365)
     *   anni chiusi:
     *     sanzione  = 30% del dovuto
     *     interessi = dovuto * somma tassi legali dall'anno dovuto all'anno corrente
     *                 INCLUSO (es. 2022 -> 2022+2023+2024+2025+2026 = 12,35%), con
     *                 il tasso dell'anno di inizio validità anch'esso in pro-rata
     *   anno in corso: solo ruolo, niente sanzioni né interessi (il comune può
     *                  applicarli solo dall'anno successivo)
     *
     * @param  \Closure(int,string,int,int):?array{0: float, 1: float, 2: array<string,mixed>}  $dovutoPeriodo
     *                                                                                                          ($anno, $dal Y-m-d, $giorni coperti, $divisore giorni) -> [dovuto del periodo, riduzione applicata, dati extra] o null se manca il tariffario
     */
    private function calcolaPerAnno(?string $dataInizioValidita, \Closure $dovutoPeriodo): array
    {
        $annoCorrente = $this->annoCorrente ?? (int) now()->format('Y');
        $inizio = $dataInizioValidita ? strtotime($dataInizioValidita) : false;
        $annoInizio = $inizio !== false ? (int) date('Y', $inizio) : $annoCorrente;
        $primoAnno = max($annoInizio, $annoCorrente - self::MAX_ANNI_ACCERTAMENTO);

        $dettaglioAnni = [];
        $anniMancanti = [];
        $totale = 0.0;
        $totaleConSanzioni = 0.0;

        for ($anno = $primoAnno; $anno <= $annoCorrente; $anno++) {
            // Periodo coperto: dal giorno di inizio validità (solo nel suo anno) al
            // 31/12. Divisore 365 come richiesto dal cliente, ma mai sotto i giorni
            // dell'anno (un bisestile intero vale 1, non 366/365).
            $dal = ($inizio !== false && $anno === $annoInizio) ? date('Y-m-d', $inizio) : sprintf('%04d-01-01', $anno);
            $giorni = (int) date('z', mktime(0, 0, 0, 12, 31, $anno)) - (int) date('z', strtotime($dal)) + 1;
            $divisore = max(self::GIORNI_ANNO, $giorni);
            $quota = $giorni / $divisore;

            $periodo = $dovutoPeriodo($anno, $dal, $giorni, $divisore);

            if ($periodo === null) {
                $anniMancanti[] = $anno;
                $dettaglioAnni[$anno] = ['errore' => 'tariffario mancante'];

                continue;
            }

            [$dovuto, $riduzionePerc, $extra] = $periodo;
            $dovutoAnno = round($dovuto, 2);

            if ($anno === $annoCorrente) {
                $sanzione = 0.0;
                $interessi = 0.0;
            } else {
                $sanzione = round($dovutoAnno * self::SANZIONE_PERCENTUALE, 2);
                $interessi = round($dovutoAnno * $this->interessiCumulati($anno, $annoCorrente, $quota), 2);
            }
            $totaleAnno = round($dovutoAnno + $sanzione + $interessi, 2);

            $dettaglioAnni[$anno] = [
                'giorni' => min($giorni, self::GIORNI_ANNO),
                'dovuto' => $dovutoAnno,
                'sanzione' => $sanzione,
                'interessi' => $interessi,
                'riduzione_applicata' => $riduzionePerc,
                'totale' => $totaleAnno,
                'tipo' => $anno === $annoCorrente ? 'ruolo' : 'accertamento',
            ] + $extra;

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
    private function tariffaScaglioneComponenti(int $anno, string $categoria, int $componentiReali, string $tipoQuota = 'variabile'): ?float
    {
        for ($n = max($componentiReali, 1); $n >= 1; $n--) {
            $tariffa = $this->tariffaAnno($anno, $categoria.'.'.$n, $tipoQuota);
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
