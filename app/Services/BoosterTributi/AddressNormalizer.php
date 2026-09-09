<?php

namespace App\Services\BoosterTributi;

/**
 * Normalizza indirizzi per confrontare l'ubicazione immobile (File 1) con la
 * residenza anagrafica (File 6/7), che nella pratica Halley sono scritti con
 * convenzioni lessicali diverse (es. "Contrada Longano n. 48" vs
 * "CONTRADA LONGANO 48"). Unico punto "fuzzy" del sistema: tutti gli altri match
 * (codice utenza, codice fiscale, numero famiglia) sono su chiave esatta.
 */
class AddressNormalizer
{
    private const SOGLIA_SIMILARITA = 90.0;

    public static function normalizza(?string $indirizzo): string
    {
        if ($indirizzo === null) {
            return '';
        }

        $v = mb_strtoupper(trim($indirizzo));
        $v = self::rimuoviAccenti($v);

        // Abbreviazioni civico: "N.", "NR.", "N°", "N " -> niente, resta solo il numero.
        $v = preg_replace('/\b(N°|NR\.?|N\.)\s*/u', '', $v);

        // Interno/scala: "I. 5", "INT. 5", "INTERNO 5" -> normalizzato in "INT 5"
        // cosicché un confronto "via base" possa comunque tagliarlo via se serve.
        $v = preg_replace('/\bI\.\s*(\d+)/u', 'INT $1', $v);
        $v = preg_replace('/\bINT(ERNO)?\.?\s*(\d+)/u', 'INT $2', $v);

        // Punteggiatura -> spazio, poi collassa spazi multipli.
        $v = preg_replace('/[.,\/]/u', ' ', $v);
        $v = preg_replace('/\s+/u', ' ', $v);

        return trim($v);
    }

    /**
     * Indirizzo senza l'eventuale interno, per un confronto "via + civico" più
     * tollerante quando il confronto stretto normalizzato fallisce.
     */
    public static function baseSenzaInterno(?string $indirizzo): string
    {
        $v = self::normalizza($indirizzo);

        return trim(preg_replace('/\bINT\s*\d+\b/u', '', $v));
    }

    public static function corrispondono(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        $na = self::normalizza($a);
        $nb = self::normalizza($b);

        if ($na === '' || $nb === '') {
            return false;
        }

        if ($na === $nb) {
            return true;
        }

        $ba = self::baseSenzaInterno($a);
        $bb = self::baseSenzaInterno($b);
        if ($ba !== '' && $ba === $bb) {
            return true;
        }

        similar_text($na, $nb, $percentuale);

        return $percentuale >= self::SOGLIA_SIMILARITA;
    }

    private static function rimuoviAccenti(string $v): string
    {
        $traslitterato = @iconv('UTF-8', 'ASCII//TRANSLIT', $v);

        return $traslitterato !== false ? $traslitterato : $v;
    }
}
