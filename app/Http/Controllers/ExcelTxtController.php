<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class ExcelTxtController extends Controller
{
    public function index()
    {
        return view('excel_txt.index');
    }

    /**
     * Step 1: Upload file e mostra anteprima colonne
     */
    public function preview(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls',
            'tipo'       => 'required|string',
            'anno'       => 'required|digits:4',
        ]);

        $file = $request->file('excel_file');
        $path = $file->store('excel_tmp', 'local');
        $fullPath = storage_path('app/' . $path);

        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        // Rimuovi eventuali righe di intestazione "titolo ordine" (celle non-array con prima colonna lunga)
        // Trova la riga header vera (quella con ITEM, COD.CLI., ecc.)
        $headerRowIndex = 0;
        foreach ($rows as $i => $row) {
            if (isset($row[0]) && strtoupper(trim($row[0])) === 'ITEM') {
                $headerRowIndex = $i;
                break;
            }
        }

        $headers = $rows[$headerRowIndex];
        $dataRows = array_slice($rows, $headerRowIndex + 1);

        // Prendi solo prime 10 righe per anteprima e normalizza le date come stringhe
        $rawPreview = array_slice($dataRows, 0, 10);
        $preview = array_map(function ($row) {
            return array_map(function ($cell) {
                if ($cell instanceof \DateTimeInterface) {
                    return $cell->format('d/m/Y');
                }
                // Numero seriale Excel (range date tipico: 40000-60000)
                if (is_numeric($cell) && (int)$cell > 40000 && (int)$cell < 60000) {
                    try {
                        return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$cell)->format('d/m/Y');
                    } catch (\Exception $e) { /* non era una data */ }
                }
                return $cell;
            }, $row);
        }, $rawPreview);

        // Salva info in sessione per step successivo
        session([
            'excel_path'       => $path,
            'header_row_index' => $headerRowIndex,
            'tipo'             => $request->tipo,
            'anno'             => $request->anno,
        ]);

        return view('excel_txt.preview', compact('headers', 'preview'));
    }

    /**
     * Step 2: L'utente seleziona le colonne → genera TXT
     */
    public function generate(Request $request)
    {
        $request->validate([
            'col_note' => 'required|integer|min:0',
            'col_data' => 'required|integer|min:0',
        ]);

        $path        = session('excel_path');
        $headerRowIndex = session('header_row_index');
        $tipo        = session('tipo');
        $anno        = session('anno');
        $colNote     = (int) $request->col_note;
        $colData     = (int) $request->col_data;

        if (!$path) {
            return redirect()->route('excel-txt.index')->with('error', 'Sessione scaduta, ricarica il file.');
        }

        $fullPath = storage_path('app/' . $path);
        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        $dataRows = array_slice($rows, $headerRowIndex + 1);

        $lines = [];
        foreach ($dataRows as $row) {
            // Salta righe vuote
            if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $noteRaw = $row[$colNote] ?? '';
            $dataRaw = $row[$colData] ?? '';

            $numero = $this->estraiNumero($noteRaw);
            $data   = $this->formattaData($dataRaw);

            if ($numero === null || $data === null) {
                continue; // salta righe con dati non parsabili
            }

            $lines[] = "{$tipo};{$anno};{$numero};{$data}";
        }

        $output = implode("\n", $lines);

        // Pulisci file temporaneo
        \Storage::disk('local')->delete($path);
        session()->forget(['excel_path', 'header_row_index', 'tipo', 'anno']);

        // Scarica come file TXT
        return response($output, 200, [
            'Content-Type'        => 'text/plain',
            'Content-Disposition' => 'attachment; filename="output.txt"',
        ]);
    }

    /**
     * Estrae il numero dall'intestazione NOTE secondo i 4 pattern conosciuti:
     *
     * ACC. TASI 18 N. 156 CF. ...     → dopo "N. "
     * ACC IMU N. 825 CF. ...           → dopo "N. "
     * IMU 2020 N. 1316-CF-CU          → dopo "N. "
     * IMUSUP 20 1789                   → ultima parola (separatore spazio)
     */
    private function estraiNumero(string $nota): ?string
    {
        $nota = trim($nota);

        // Pattern 1, 2, 3: "N. <numero>"
        if (preg_match('/N\.\s*(\d+)/i', $nota, $m)) {
            return $m[1];
        }

        // Pattern 4: "IMUSUP XX <numero>" → ultima parola numerica
        if (preg_match('/IMUSUP\s+\d+\s+(\d+)/i', $nota, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Formatta la data in YYYYMMDD.
     *
     * Gestisce:
     *  - Oggetto DateTime/DateTimeInterface (da PhpSpreadsheet)
     *  - Numero seriale Excel (intero o float)
     *  - Stringhe italiane: GG/MM/AAAA, GG/M/AA, GG/MM/AA, GG-MM-AAAA, ecc.
     *  - Stringhe ISO: AAAA-MM-GG
     */
    private function formattaData($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            // 1) Già un oggetto DateTime (PhpSpreadsheet lo restituisce così per celle-data)
            if ($raw instanceof \DateTimeInterface) {
                return $raw->format('Ymd');
            }

            // 2) Numero seriale Excel (es. 45342)
            if (is_numeric($raw) && !str_contains((string)$raw, '/') && !str_contains((string)$raw, '-')) {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$raw);
                return $date->format('Ymd');
            }

            $str = trim((string)$raw);

            // 3) Formati italiani GG/MM/AAAA e varianti (separatori / o -)
            //    Cattura: 1-2 cifre giorno, 1-2 cifre mese, 2-4 cifre anno
            if (preg_match(
                '/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/',
                $str,
                $m
            )) {
                $d   = (int) $m[1];
                $mo  = (int) $m[2];
                $y   = (int) $m[3];

                // Anno a 2 cifre → espandi (00-49 = 2000-2049, 50-99 = 1950-1999)
                if ($y < 100) {
                    $y += ($y < 50) ? 2000 : 1900;
                }

                if (checkdate($mo, $d, $y)) {
                    return sprintf('%04d%02d%02d', $y, $mo, $d);
                }
            }

            // 4) Formato ISO AAAA-MM-GG
            if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $str, $m)) {
                $y  = (int) $m[1];
                $mo = (int) $m[2];
                $d  = (int) $m[3];
                if (checkdate($mo, $d, $y)) {
                    return sprintf('%04d%02d%02d', $y, $mo, $d);
                }
            }

            // 5) Fallback Carbon (ultimo tentativo)
            return Carbon::parse($str)->format('Ymd');

        } catch (\Exception $e) {
            return null;
        }
    }
}