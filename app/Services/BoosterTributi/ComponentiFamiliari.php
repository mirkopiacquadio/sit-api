<?php

namespace App\Services\BoosterTributi;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Confronto componenti dichiarati TARI <-> componenti reali da anagrafe, condiviso
 * dal Job CalcolaRecuperoComponentiFamiliari (differenze in positivo, da recuperare)
 * e dall'AnomalyDetector (differenze in negativo, contate nella fotografia).
 *
 * - Dichiarati: sottocategoria della scheda TARI (File 2, colonna Q, es. "Sei o
 *   piu` componenti"), cioè lo scaglione per cui il contribuente è davvero tassato
 *   in quota variabile; il numero componenti del File 1 può essere inserito male
 *   dall'operatore comunale (annotazioni cliente 2026-10-05, punto 5: sui dati
 *   reali di Sant'Agata differisce in ~830 utenze).
 * - Reali: CF -> riga del "File 6 - Dati anagrafici residenza" -> n. componenti
 *   del nucleo (col. R) e residenza attuale (col. G).
 * - Date di ingresso: per ogni componente vivo dello stesso codice famiglia
 *   (col. P) la più recente tra data immigrazione (F), variazione indirizzo (H) e
 *   nascita (D); RecuperoCalculator::calcolaComponenti le usa per sapere quanti
 *   componenti c'erano davvero giorno per giorno (istruzioni cliente 2026-10-08).
 */
class ComponentiFamiliari
{
    private const CATEGORIE_ESCLUSE_PREFISSO = ['B', 'D', 'F'];

    private const CATEGORIE_ESCLUSE_ESATTE = ['C01', 'C/01'];

    private const NUMERI_IN_LETTERE = [
        'uno' => 1, 'una' => 1, 'un' => 1, 'unico' => 1, 'singolo' => 1,
        'due' => 2, 'tre' => 3, 'quattro' => 4, 'cinque' => 5, 'sei' => 6,
        'sette' => 7, 'otto' => 8, 'nove' => 9, 'dieci' => 10,
    ];

    /** @var Collection<string,object> */
    private Collection $perCodiceFiscale;

    /** @var Collection<string,Collection<int,object>> */
    private Collection $perFamiglia;

    /**
     * @param  Collection<int,object>  $righeResidenza  righe bt_anagrafe_residenza di un batch
     */
    public function __construct(Collection $righeResidenza)
    {
        $this->perCodiceFiscale = $righeResidenza
            ->filter(fn ($r) => self::normalizzaCf($r->codice_fiscale) !== null)
            ->keyBy(fn ($r) => self::normalizzaCf($r->codice_fiscale));

        $this->perFamiglia = $righeResidenza
            ->filter(fn ($r) => $r->codice_famiglia !== null)
            ->groupBy(fn ($r) => trim((string) $r->codice_famiglia));
    }

    public static function daBatch(string $batchResidenza): self
    {
        return new self(
            DB::connection('pgsql')->table('bt_anagrafe_residenza')
                ->where('import_batch_id', $batchResidenza)
                ->get()
        );
    }

    /**
     * Solo persone fisiche e categorie abitative/pertinenze (esclude B, D, F, C/01).
     */
    public static function immobileAnalizzabile(object $immobile): bool
    {
        return $immobile->tipo_persona === 'F' && ! self::categoriaEsclusa($immobile->categoria_catastale);
    }

    /**
     * @return array{n: int, indirizzo: ?string, date_ingresso: array<int,string>, data_variazione_nucleo: ?string}|null null se il CF non è in anagrafe o manca il n. componenti
     */
    public function reali(?string $codiceFiscale): ?array
    {
        $cf = self::normalizzaCf($codiceFiscale);
        if ($cf === null) {
            return null;
        }

        $residente = $this->perCodiceFiscale->get($cf);
        // Il File 6 contiene anche i deceduti: il loro n. componenti non descrive
        // un nucleo attuale (l'intestatario TARI deceduto è già nella fotografia).
        if ($residente === null || $residente->n_componenti === null || ! empty($residente->data_decesso)) {
            return null;
        }

        $famiglia = $residente->codice_famiglia !== null
            ? $this->perFamiglia->get(trim((string) $residente->codice_famiglia), collect([$residente]))
            : collect([$residente]);

        $dateIngresso = $famiglia
            ->map(fn ($componente) => self::dataIngresso($componente))
            ->filter()
            ->values()
            ->all();

        return [
            'n' => (int) $residente->n_componenti,
            'indirizzo' => $residente->indirizzo_attuale,
            'date_ingresso' => $dateIngresso,
            'data_variazione_nucleo' => $dateIngresso === [] ? null : max($dateIngresso),
        ];
    }

    /**
     * Da quando il componente vive nel nucleo attuale: la più recente tra data
     * immigrazione (F), data variazione indirizzo (H) e data di nascita (D).
     * null per i deceduti (non sono tra i componenti attuali) o senza date.
     */
    public static function dataIngresso(object $componente): ?string
    {
        if (! empty($componente->data_decesso)) {
            return null;
        }

        $date = [];
        foreach (['data_immigrazione', 'data_variazione_indirizzo', 'data_nascita'] as $campo) {
            if (! empty($componente->{$campo})) {
                $date[] = substr((string) $componente->{$campo}, 0, 10);
            }
        }

        return $date === [] ? null : max($date);
    }

    /**
     * null = utenza non confrontabile: manca la riga File 2, oppure la
     * sottocategoria non indica un numero di componenti (utenza non domestica,
     * es. "Uffici,agenzie", "Autorimesse e magazzini..." anche se intestata a
     * persona fisica con categoria catastale abitativa/pertinenza).
     */
    public static function dichiarati(?object $dettaglio): ?int
    {
        return $dettaglio !== null ? self::numeroDaSottocategoria($dettaglio->sottocategoria) : null;
    }

    /**
     * Testo sottocategoria Halley, es. "3 componenti", "Tre componenti",
     * "Sei o più componenti", "Unico occupante".
     */
    public static function numeroDaSottocategoria(?string $sottocategoria): ?int
    {
        if ($sottocategoria === null || trim($sottocategoria) === '') {
            return null;
        }

        $testo = mb_strtolower(trim($sottocategoria));

        if (preg_match('/\d+/', $testo, $m)) {
            return (int) $m[0];
        }

        foreach (preg_split('/[^\p{L}]+/u', $testo, -1, PREG_SPLIT_NO_EMPTY) as $parola) {
            if (isset(self::NUMERI_IN_LETTERE[$parola])) {
                return self::NUMERI_IN_LETTERE[$parola];
            }
        }

        return null;
    }

    /**
     * Confronto CF Immobili TARI (File 1) <-> Anagrafe residenti (File 7): gli
     * export Halley possono avere spazi iniziali/finali (campi a larghezza fissa),
     * quindi serve trim oltre a maiuscole/minuscole, altrimenti CF identici non
     * matchano e il ricalcolo scarta la riga.
     */
    public static function normalizzaCf(?string $cf): ?string
    {
        if ($cf === null) {
            return null;
        }

        $cf = mb_strtoupper(trim($cf));

        return $cf !== '' ? $cf : null;
    }

    private static function categoriaEsclusa(?string $categoria): bool
    {
        if ($categoria === null) {
            return false;
        }

        $categoria = mb_strtoupper(trim($categoria));

        if (in_array(str_replace('/', '', $categoria), self::CATEGORIE_ESCLUSE_ESATTE, true)) {
            return true;
        }

        return in_array(mb_substr($categoria, 0, 1), self::CATEGORIE_ESCLUSE_PREFISSO, true);
    }
}
