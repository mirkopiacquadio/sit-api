<?php

namespace App\Services\BoosterTributi;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Confronto componenti dichiarati TARI <-> componenti reali da anagrafe, condiviso
 * dal Job CalcolaRecuperoComponentiFamiliari (differenze in positivo, da recuperare)
 * e dall'AnomalyDetector (differenze in negativo, contate nella fotografia).
 *
 * - Dichiarati: sottocategoria della scheda TARI (File 2, colonna Q), cioè lo
 *   scaglione per cui il contribuente è davvero tassato in quota variabile; il
 *   numero componenti del File 1 può essere inserito male dall'operatore comunale
 *   (annotazioni cliente 2026-10-05, punto 5). Fallback: suffisso del codice
 *   tariffa (stesso scaglione), poi File 1.
 * - Reali: CF -> famiglia (File 7) -> n. componenti dal File 8, fallback File 6.
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

    /**
     * @param  Collection<string,object>  $residenti  File 7 indicizzato per CF normalizzato
     * @param  Collection<int,object>|null  $gruppiPerFamiglia  File 8 per numero_famiglia
     * @param  Collection<int,object>|null  $famigliePerNumero  File 6 per numero_famiglia
     */
    public function __construct(
        private Collection $residenti,
        private ?Collection $gruppiPerFamiglia,
        private ?Collection $famigliePerNumero
    ) {}

    public static function daBatch(string $batchResidenti, ?string $batchFamiglie, ?string $batchGruppi): self
    {
        $residenti = DB::connection('pgsql')->table('bt_anagrafe_residenti')
            ->where('import_batch_id', $batchResidenti)
            ->get()
            ->keyBy(fn ($r) => self::normalizzaCf($r->codice_fiscale));

        $gruppi = $batchGruppi
            ? DB::connection('pgsql')->table('bt_anagrafe_gruppi_famiglia')
                ->where('import_batch_id', $batchGruppi)
                ->get()
                ->keyBy('numero_famiglia')
            : null;

        $famiglie = $batchFamiglie
            ? DB::connection('pgsql')->table('bt_anagrafe_famiglie')
                ->where('import_batch_id', $batchFamiglie)
                ->get()
                ->keyBy('numero_famiglia')
            : null;

        return new self($residenti, $gruppi, $famiglie);
    }

    /**
     * Solo persone fisiche e categorie abitative/pertinenze (esclude B, D, F, C/01).
     */
    public static function immobileAnalizzabile(object $immobile): bool
    {
        return $immobile->tipo_persona === 'F' && ! self::categoriaEsclusa($immobile->categoria_catastale);
    }

    /**
     * @return array{n: int, indirizzo: ?string}|null null se il CF non è in anagrafe o la famiglia non è nei File 6/8
     */
    public function reali(?string $codiceFiscale): ?array
    {
        $cf = self::normalizzaCf($codiceFiscale);
        if ($cf === null) {
            return null;
        }

        $residente = $this->residenti->get($cf);
        if ($residente === null) {
            return null;
        }

        $indirizzo = $residente->indirizzo_residenza;

        if ($this->gruppiPerFamiglia && $this->gruppiPerFamiglia->has($residente->numero_famiglia)) {
            return ['n' => (int) $this->gruppiPerFamiglia->get($residente->numero_famiglia)->n_componenti, 'indirizzo' => $indirizzo];
        }

        if ($this->famigliePerNumero && $this->famigliePerNumero->has($residente->numero_famiglia)) {
            $famiglia = $this->famigliePerNumero->get($residente->numero_famiglia);

            return ['n' => (int) $famiglia->n_componenti, 'indirizzo' => $indirizzo ?: $famiglia->indirizzo];
        }

        return null;
    }

    public static function dichiarati(?object $dettaglio, object $immobile): int
    {
        if ($dettaglio !== null) {
            $n = self::numeroDaSottocategoria($dettaglio->sottocategoria)
                ?? self::numeroDaCodiceTariffa($dettaglio->codice_tariffa);
            if ($n !== null) {
                return $n;
            }
        }

        return (int) $immobile->componenti_residenti;
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
     * Codice tariffa "categoria.sottocategoria" (es. "1.3"): per le utenze
     * domestiche la sottocategoria è lo scaglione componenti (vedi
     * RecuperoCalculator::tariffaScaglioneComponenti).
     */
    public static function numeroDaCodiceTariffa(?string $codiceTariffa): ?int
    {
        if ($codiceTariffa === null || ! preg_match('/^\s*\d+\.(\d+)\s*$/', $codiceTariffa, $m)) {
            return null;
        }

        return (int) $m[1];
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
