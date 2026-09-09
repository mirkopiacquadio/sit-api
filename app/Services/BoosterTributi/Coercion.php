<?php

namespace App\Services\BoosterTributi;

use DateTimeInterface;

/**
 * Conversioni di valori grezzi (celle Excel o testo da tabelle HTML) verso i tipi
 * usati dalle tabelle bt_*. Centralizzata perché gli export Halley mescolano
 * formati numerici italiani ("0,762461") e valori Excel nativi a seconda del file.
 */
class Coercion
{
    public static function stringa($valore): ?string
    {
        if ($valore === null) {
            return null;
        }

        $valore = trim((string) $valore);

        return $valore === '' ? null : $valore;
    }

    public static function intero($valore): ?int
    {
        if ($valore === null || $valore === '') {
            return null;
        }

        if (is_numeric($valore)) {
            return (int) round((float) $valore);
        }

        $pulito = preg_replace('/[^0-9\-]/', '', (string) $valore);

        return $pulito === '' ? null : (int) $pulito;
    }

    public static function decimale($valore): ?float
    {
        if ($valore === null || $valore === '') {
            return null;
        }

        if (is_float($valore) || is_int($valore)) {
            return (float) $valore;
        }

        $valore = trim((string) $valore);

        if ($valore === '') {
            return null;
        }

        // Formato italiano: punto = migliaia, virgola = decimali (es. "1.234,56").
        if (str_contains($valore, ',')) {
            $valore = str_replace('.', '', $valore);
            $valore = str_replace(',', '.', $valore);
        }

        return is_numeric($valore) ? (float) $valore : null;
    }

    public static function data($valore): ?string
    {
        if ($valore === null || $valore === '') {
            return null;
        }

        if ($valore instanceof DateTimeInterface) {
            return $valore->format('Y-m-d');
        }

        $valore = trim((string) $valore);
        if ($valore === '') {
            return null;
        }

        $timestamp = strtotime($valore);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    public static function booleano($valore): bool
    {
        if ($valore === null) {
            return false;
        }

        if (is_bool($valore)) {
            return $valore;
        }

        $valore = strtolower(trim((string) $valore));

        return in_array($valore, ['1', 'x', 'si', 'sì', 'true', 'yes', '●'], true);
    }
}
