<?php

namespace App\Services\BoosterTributi;

/**
 * Normalizza indirizzi per confrontare l'ubicazione immobile (File 1) con la
 * residenza anagrafica (File 6 "Dati anagrafici residenza"), che nella pratica
 * Halley sono scritti con convenzioni diverse. Esempi reali (Sant'Agata de' Goti):
 *   "Via Domenico mustilli n. 14 p. 2 i. 3"  <->  "VIA DOMENICO MUSTILLI 14 I. 3 P. 2"
 *   "Via Santisi"                             <->  "VIA SANTISI SNC"
 * Unico punto "fuzzy" del sistema: tutti gli altri match (codice utenza, codice
 * fiscale, codice famiglia) sono su chiave esatta.
 *
 * Il confronto è su "via + civico": piano/interno/scala dopo il civico sono
 * ignorati; se uno dei due indirizzi non ha civico (o è SNC) basta la via.
 */
class AddressNormalizer
{
    private const SOGLIA_SIMILARITA_VIA = 90.0;

    private const ABBREVIAZIONI = [
        'V.LE' => 'VIALE', 'VLE' => 'VIALE',
        'P.ZZA' => 'PIAZZA', 'P.ZA' => 'PIAZZA', 'PZA' => 'PIAZZA', 'PZZA' => 'PIAZZA',
        'C.DA' => 'CONTRADA', 'C/DA' => 'CONTRADA', 'CDA' => 'CONTRADA',
        'C.SO' => 'CORSO', 'CSO' => 'CORSO',
        'FRAZ.' => 'FRAZIONE', 'FRAZ' => 'FRAZIONE', 'FR.' => 'FRAZIONE',
        'LOC.' => 'LOCALITA', 'LOC' => 'LOCALITA',
        'TRAV.' => 'TRAVERSA', 'TRAV' => 'TRAVERSA',
        'VICO' => 'VICOLO',
    ];

    private const SANTI = ['SAN', 'SANTO', 'SANTA', 'SANT', 'STA', 'STO', 'SS'];

    public static function normalizza(?string $indirizzo): string
    {
        [$via, $civico] = self::scomponi($indirizzo);

        return trim($via.' '.($civico ?? ''));
    }

    public static function corrispondono(?string $a, ?string $b): bool
    {
        [$viaA, $civicoA] = self::scomponi($a);
        [$viaB, $civicoB] = self::scomponi($b);

        if ($viaA === '' || $viaB === '') {
            return false;
        }

        if ($civicoA !== null && $civicoB !== null && $civicoA !== $civicoB) {
            return false;
        }

        if ($viaA === $viaB) {
            return true;
        }

        similar_text($viaA, $viaB, $percentuale);

        return $percentuale >= self::SOGLIA_SIMILARITA_VIA;
    }

    /**
     * @return array{0: string, 1: ?string} [via normalizzata, civico (solo cifre, senza zeri iniziali) o null]
     */
    public static function scomponi(?string $indirizzo): array
    {
        if ($indirizzo === null || trim($indirizzo) === '') {
            return ['', null];
        }

        $v = mb_strtoupper(trim($indirizzo));
        $v = self::rimuoviAccenti($v);

        foreach (self::ABBREVIAZIONI as $abbr => $esteso) {
            $v = preg_replace('/(?<![A-Z])'.preg_quote($abbr, '/').'(?![A-Z])/u', $esteso, $v);
        }

        // Apostrofi/accenti grafici (es. "Attivita`", "Sant'Anna"), barre e
        // punteggiatura -> spazio; "N." / "NR." / "N°" davanti al civico via.
        $v = preg_replace('/\b(N°|NR\.?|N\.)\s*(?=\d)/u', ' ', $v);
        $v = preg_replace('/[^A-Z0-9 ]+/u', ' ', $v);
        $token = preg_split('/\s+/', trim($v), -1, PREG_SPLIT_NO_EMPTY);

        // Civico = primo numero dopo almeno due parole (così "VIA 4 NOVEMBRE 12"
        // -> 12); tutto quello che segue (piano, interno, scala) è ignorato.
        $via = [];
        $civico = null;
        $parole = 0;
        foreach ($token as $t) {
            if (ctype_digit($t) && $parole >= 2) {
                $civico = ltrim($t, '0') ?: '0';
                break;
            }
            if ($t === 'SNC') {
                break;
            }
            if (! ctype_digit($t)) {
                $parole++;
            }
            $via[] = in_array($t, self::SANTI, true) ? 'S' : $t;
        }

        return [implode(' ', $via), $civico];
    }

    private static function rimuoviAccenti(string $v): string
    {
        $traslitterato = @iconv('UTF-8', 'ASCII//TRANSLIT', $v);

        return $traslitterato !== false ? $traslitterato : $v;
    }
}
