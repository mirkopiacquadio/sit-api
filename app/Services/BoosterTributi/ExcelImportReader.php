<?php

namespace App\Services\BoosterTributi;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Legge gli export Halley (xlsx/csv reali, oppure i vecchi "tariffario/riduzioni.xls"
 * che in realtà sono tabelle HTML salvate con estensione .xls) restituendo un array
 * di righe (array indicizzato per colonna, 0 = colonna A).
 *
 * Tutti gli export osservati condividono lo stesso layout: riga 0 = intestazione
 * "Stampa ..." generata da Halley, riga 1 = intestazioni di colonna, riga 2+ = dati.
 */
class ExcelImportReader
{
    /**
     * @return array<int,array<int,mixed>> solo le righe dati (intestazioni scartate)
     */
    public static function righeDati(string $path): array
    {
        $tutte = self::isTabellaHtml($path)
            ? self::leggiHtml($path)
            : self::leggiSpreadsheet($path);

        $dati = array_slice($tutte, 2);

        return array_values(array_filter($dati, [self::class, 'rigaNonVuota']));
    }

    private static function rigaNonVuota(array $riga): bool
    {
        foreach ($riga as $valore) {
            if ($valore !== null && $valore !== '') {
                return true;
            }
        }

        return false;
    }

    private static function isTabellaHtml(string $path): bool
    {
        $estensione = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($estensione, ['xls', 'csv'], true)) {
            return false;
        }

        $inizio = file_get_contents($path, false, null, 0, 2048);
        if ($inizio === false) {
            return false;
        }

        return (bool) preg_match('/<html|<table/i', $inizio);
    }

    /**
     * @return array<int,array<int,mixed>>
     */
    private static function leggiSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $righe = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $cella = [];
            foreach ($cellIterator as $cell) {
                $cella[] = self::valoreCella($cell);
            }
            $righe[] = $cella;
        }

        return $righe;
    }

    private static function valoreCella($cell)
    {
        $valore = $cell->getValue();

        if ($valore === null || $valore === '') {
            return $valore;
        }

        if (is_numeric($valore) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject($valore);
        }

        return $valore;
    }

    /**
     * @return array<int,array<int,mixed>>
     */
    private static function leggiHtml(string $path): array
    {
        $contenuto = file_get_contents($path);
        if ($contenuto === false) {
            return [];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$contenuto);
        libxml_use_internal_errors(false);

        $righe = [];
        foreach ($dom->getElementsByTagName('tr') as $tr) {
            $riga = [];
            foreach ($tr->childNodes as $cellNode) {
                if (! in_array($cellNode->nodeName, ['td', 'th'], true)) {
                    continue;
                }
                $riga[] = trim($cellNode->textContent);
            }
            if ($riga !== []) {
                $righe[] = $riga;
            }
        }

        return $righe;
    }
}
