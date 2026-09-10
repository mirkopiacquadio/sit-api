<?php

namespace App\Http\Controllers;

use App\Jobs\CalcolaRecuperoComponentiFamiliari;
use App\Jobs\CalcolaRecuperoMqTari;
use App\Services\BoosterTributi\AnomalyDetector;
use App\Services\BoosterTributi\ComuneSchema;
use App\Services\BoosterTributi\TariImportService;
use App\Support\BoosterTributi\ComuneConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BoosterTributiController extends Controller
{
    public function index()
    {
        return view('booster-tributi.index', [
            'comuni' => ComuneConnection::elenco(),
        ]);
    }

    /**
     * Punta la connessione al comune indicato e assicura che le tabelle bt_*
     * esistano. Va chiamato all'inizio di ogni endpoint che tocca il DB.
     */
    private function setComune(string $codiceComune): void
    {
        if (! ComuneConnection::isValido($codiceComune)) {
            abort(422, "Codice comune non valido o non abilitato per BoosterTributi: {$codiceComune}");
        }

        ComuneConnection::connetti($codiceComune);
        ComuneSchema::assicura();
    }

    private function fileTemporaneo(Request $request, string $campo): string
    {
        // I vecchi export Halley .xls sono in realtà tabelle HTML (vedi
        // ExcelImportReader::isTabellaHtml): il PHP fileinfo li rileva come
        // text/html, quindi la validazione va fatta sull'estensione dichiarata
        // dal client (`extensions`), non sul mime-sniffing di `mimes`.
        $request->validate([
            $campo => 'required|file|extensions:xlsx,xls,csv',
        ]);

        $path = $request->file($campo)->store('booster_tributi_tmp', 'local');

        return storage_path('app/'.$path);
    }

    private function eliminaTemporaneo(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function importaImmobili(Request $request, string $comune)
    {
        $path = null;

        try {
            $this->setComune($comune);
            $path = $this->fileTemporaneo($request, 'file');

            $esito = (new TariImportService())->importaFile1Immobili($path);
            $anomalie = (new AnomalyDetector())->rileva($esito['batch_id']);

            return response()->json([
                'success' => true,
                'import' => $esito,
                'anomalie' => $anomalie,
            ]);
        } catch (\Throwable $e) {
            Log::error('BoosterTributi importaImmobili: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => $this->messaggioErrore($e)], 500);
        } finally {
            if ($path) {
                $this->eliminaTemporaneo($path);
            }
        }
    }

    public function importaDettaglio(Request $request, string $comune)
    {
        $path = null;

        try {
            $this->setComune($comune);
            $path = $this->fileTemporaneo($request, 'file');

            $esito = (new TariImportService())->importaFile2Dettaglio($path);

            return response()->json(['success' => true, 'import' => $esito]);
        } catch (\Throwable $e) {
            Log::error('BoosterTributi importaDettaglio: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => $this->messaggioErrore($e)], 500);
        } finally {
            if ($path) {
                $this->eliminaTemporaneo($path);
            }
        }
    }

    public function importaTariffario(Request $request, string $comune)
    {
        $path = null;

        try {
            $this->setComune($comune);
            $request->validate(['anno' => 'required|digits:4']);
            $path = $this->fileTemporaneo($request, 'file');

            $esito = (new TariImportService())->importaFile3Tariffario($path, (int) $request->input('anno'));

            return response()->json(['success' => true, 'import' => $esito]);
        } catch (\Throwable $e) {
            Log::error('BoosterTributi importaTariffario: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => $this->messaggioErrore($e)], 500);
        } finally {
            if ($path) {
                $this->eliminaTemporaneo($path);
            }
        }
    }

    public function importaRiduzioni(Request $request, string $comune)
    {
        $path = null;

        try {
            $this->setComune($comune);
            $request->validate(['anno' => 'required|digits:4']);
            $path = $this->fileTemporaneo($request, 'file');

            $esito = (new TariImportService())->importaFile4Riduzioni($path, (int) $request->input('anno'));

            return response()->json(['success' => true, 'import' => $esito]);
        } catch (\Throwable $e) {
            Log::error('BoosterTributi importaRiduzioni: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => $this->messaggioErrore($e)], 500);
        } finally {
            if ($path) {
                $this->eliminaTemporaneo($path);
            }
        }
    }

    public function importaAnagrafeFamiglie(Request $request, string $comune)
    {
        $path = null;

        try {
            $this->setComune($comune);
            $path = $this->fileTemporaneo($request, 'file');

            $esito = (new TariImportService())->importaFile6AnagrafeFamiglie($path);

            return response()->json(['success' => true, 'import' => $esito]);
        } catch (\Throwable $e) {
            Log::error('BoosterTributi importaAnagrafeFamiglie: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => $this->messaggioErrore($e)], 500);
        } finally {
            if ($path) {
                $this->eliminaTemporaneo($path);
            }
        }
    }

    public function importaAnagrafeResidenti(Request $request, string $comune)
    {
        $path = null;

        try {
            $this->setComune($comune);
            $path = $this->fileTemporaneo($request, 'file');

            $esito = (new TariImportService())->importaFile7AnagrafeResidenti($path);

            return response()->json(['success' => true, 'import' => $esito]);
        } catch (\Throwable $e) {
            Log::error('BoosterTributi importaAnagrafeResidenti: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => $this->messaggioErrore($e)], 500);
        } finally {
            if ($path) {
                $this->eliminaTemporaneo($path);
            }
        }
    }

    public function importaGruppiFamiglia(Request $request, string $comune)
    {
        $path = null;

        try {
            $this->setComune($comune);
            $path = $this->fileTemporaneo($request, 'file');

            $esito = (new TariImportService())->importaFile8GruppiFamiglia($path);

            return response()->json(['success' => true, 'import' => $esito]);
        } catch (\Throwable $e) {
            Log::error('BoosterTributi importaGruppiFamiglia: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => $this->messaggioErrore($e)], 500);
        } finally {
            if ($path) {
                $this->eliminaTemporaneo($path);
            }
        }
    }

    /**
     * Messaggio leggibile per l'alert JS: le ValidationException (es. anno
     * mancante, file di tipo non ammesso) hanno un messaggio generico ("The
     * given data was invalid.") ma espongono il dettaglio in errors().
     */
    private function messaggioErrore(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return collect($e->errors())->flatten()->implode(' ');
        }

        return $e->getMessage();
    }

    /**
     * Stato import per il comune: ultimo batch per ogni tipo file, con conteggio
     * righe, usato dalla UI per abilitare/disabilitare i pulsanti di calcolo.
     */
    public function stato(string $comune)
    {
        $this->setComune($comune);

        $ultimiBatch = DB::table('bt_import_batch')
            ->select('tipo_file', DB::raw('MAX(imported_at) as ultimo'))
            ->groupBy('tipo_file')
            ->pluck('ultimo', 'tipo_file');

        $dettaglio = [];
        foreach ($ultimiBatch as $tipo => $ultimo) {
            $batch = DB::table('bt_import_batch')
                ->where('tipo_file', $tipo)
                ->where('imported_at', $ultimo)
                ->orderByDesc('id')
                ->first();
            $dettaglio[$tipo] = $batch;
        }

        return response()->json(['success' => true, 'batch' => $dettaglio]);
    }

    public function anomalie(string $comune, string $batchId)
    {
        $this->setComune($comune);

        $righe = DB::table('bt_anomalie_snapshot')
            ->where('import_batch_id', $batchId)
            ->join('bt_tari_immobili', function ($join) use ($batchId) {
                $join->on('bt_tari_immobili.codice_utenza', '=', 'bt_anomalie_snapshot.codice_utenza')
                    ->where('bt_tari_immobili.import_batch_id', '=', $batchId);
            })
            ->select(
                'bt_anomalie_snapshot.codice_utenza',
                'bt_anomalie_snapshot.tipi_anomalia',
                'bt_tari_immobili.denominazione',
                'bt_tari_immobili.indirizzo_immobile',
                'bt_tari_immobili.mq_tari',
                'bt_tari_immobili.mq_catasto'
            )
            ->get();

        return response()->json(['success' => true, 'righe' => $righe]);
    }

    public function exportAnomalie(string $comune, string $batchId)
    {
        $this->setComune($comune);

        $righe = DB::table('bt_anomalie_snapshot')
            ->where('import_batch_id', $batchId)
            ->join('bt_tari_immobili', function ($join) use ($batchId) {
                $join->on('bt_tari_immobili.codice_utenza', '=', 'bt_anomalie_snapshot.codice_utenza')
                    ->where('bt_tari_immobili.import_batch_id', '=', $batchId);
            })
            ->select(
                'bt_anomalie_snapshot.codice_utenza',
                'bt_anomalie_snapshot.tipi_anomalia',
                'bt_tari_immobili.denominazione',
                'bt_tari_immobili.codice_fiscale_piva',
                'bt_tari_immobili.indirizzo_immobile',
                'bt_tari_immobili.foglio',
                'bt_tari_immobili.numero',
                'bt_tari_immobili.mq_tari',
                'bt_tari_immobili.mq_catasto',
                'bt_tari_immobili.componenti_residenti',
                'bt_tari_immobili.componenti_non_residenti',
                'bt_tari_immobili.data_decesso'
            )
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'Codice utenza', 'Anomalie', 'Denominazione', 'Codice fiscale/P.IVA', 'Indirizzo immobile',
            'Foglio', 'Numero', 'Mq TARI', 'Mq catasto', 'Componenti residenti', 'Componenti non residenti', 'Data decesso',
        ], null, 'A1');

        $rigaExcel = 2;
        foreach ($righe as $riga) {
            $tipi = json_decode($riga->tipi_anomalia, true) ?? [];
            $sheet->fromArray([
                $riga->codice_utenza,
                implode(', ', $tipi),
                $riga->denominazione,
                $riga->codice_fiscale_piva,
                $riga->indirizzo_immobile,
                $riga->foglio,
                $riga->numero,
                $riga->mq_tari,
                $riga->mq_catasto,
                $riga->componenti_residenti,
                $riga->componenti_non_residenti,
                $riga->data_decesso,
            ], null, "A{$rigaExcel}");
            $rigaExcel++;
        }

        $fileName = "booster_tributi_anomalie_{$comune}_".now()->format('Ymd_His').'.xlsx';
        $tempPath = storage_path('app/booster_tributi_tmp/'.$fileName);
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    private function ultimoBatch(string $tipoFile): ?string
    {
        $batch = DB::table('bt_import_batch')
            ->where('tipo_file', $tipoFile)
            ->orderByDesc('imported_at')
            ->first();

        return $batch->id ?? null;
    }

    public function calcolaMq(string $comune)
    {
        $this->setComune($comune);

        $batchImmobili = $this->ultimoBatch('file1_immobili');
        $batchDettaglio = $this->ultimoBatch('file2_dettaglio_sottocategoria');

        if (! $batchImmobili || ! $batchDettaglio) {
            return response()->json([
                'success' => false,
                'error' => 'Servono File 1 (immobili) e File 2 (dettaglio sottocategoria) importati per questo comune.',
            ], 422);
        }

        $jobKey = (string) Str::uuid();
        CalcolaRecuperoMqTari::dispatch($comune, $jobKey, $batchImmobili, $batchDettaglio);

        return response()->json(['success' => true, 'job_key' => $jobKey]);
    }

    public function calcolaComponenti(string $comune)
    {
        $this->setComune($comune);

        $batchImmobili = $this->ultimoBatch('file1_immobili');
        $batchDettaglio = $this->ultimoBatch('file2_dettaglio_sottocategoria');
        $batchResidenti = $this->ultimoBatch('file7_anagrafe_residenti');
        $batchFamiglie = $this->ultimoBatch('file6_anagrafe_famiglie');
        $batchGruppi = $this->ultimoBatch('file8_gruppi_famiglia');

        if (! $batchImmobili || ! $batchDettaglio || ! $batchResidenti || (! $batchFamiglie && ! $batchGruppi)) {
            return response()->json([
                'success' => false,
                'error' => 'Servono File 1, File 2, File 7 e almeno uno tra File 6/File 8 importati per questo comune.',
            ], 422);
        }

        $jobKey = (string) Str::uuid();
        CalcolaRecuperoComponentiFamiliari::dispatch(
            $comune,
            $jobKey,
            $batchImmobili,
            $batchDettaglio,
            $batchResidenti,
            $batchFamiglie,
            $batchGruppi
        );

        return response()->json(['success' => true, 'job_key' => $jobKey]);
    }

    public function statoCalcolo(string $jobKey)
    {
        $stato = Cache::get("job_status_bt_{$jobKey}");

        if ($stato === null) {
            return response()->json(['success' => false, 'error' => 'Job non trovato o scaduto'], 404);
        }

        return response()->json(['success' => true] + $stato);
    }

    public function risultatiMq(string $comune)
    {
        $this->setComune($comune);
        $batchImmobili = $this->ultimoBatch('file1_immobili');

        $righe = DB::table('bt_recupero_mq')
            ->where('import_batch_id', $batchImmobili)
            ->join('bt_tari_immobili', function ($join) use ($batchImmobili) {
                $join->on('bt_tari_immobili.codice_utenza', '=', 'bt_recupero_mq.codice_utenza')
                    ->where('bt_tari_immobili.import_batch_id', '=', $batchImmobili);
            })
            ->select('bt_tari_immobili.*', 'bt_recupero_mq.mq_diff', 'bt_recupero_mq.dettaglio_anni', 'bt_recupero_mq.totale_recuperabile')
            ->orderByDesc('bt_recupero_mq.totale_recuperabile')
            ->get();

        return response()->json([
            'success' => true,
            'righe' => $righe,
            'totale_generale' => round($righe->sum('totale_recuperabile'), 2),
        ]);
    }

    public function risultatiComponenti(string $comune)
    {
        $this->setComune($comune);
        $batchImmobili = $this->ultimoBatch('file1_immobili');

        $righe = DB::table('bt_recupero_componenti')
            ->where('import_batch_id', $batchImmobili)
            ->join('bt_tari_immobili', function ($join) use ($batchImmobili) {
                $join->on('bt_tari_immobili.codice_utenza', '=', 'bt_recupero_componenti.codice_utenza')
                    ->where('bt_tari_immobili.import_batch_id', '=', $batchImmobili);
            })
            ->select(
                'bt_tari_immobili.*',
                'bt_recupero_componenti.componenti_diff',
                'bt_recupero_componenti.match_residenza_ubicazione',
                'bt_recupero_componenti.dettaglio_anni',
                'bt_recupero_componenti.totale_recuperabile'
            )
            ->orderByDesc('bt_recupero_componenti.totale_recuperabile')
            ->get();

        return response()->json([
            'success' => true,
            'righe' => $righe,
            'totale_generale' => round($righe->sum('totale_recuperabile'), 2),
        ]);
    }

    public function exportRisultatiMq(string $comune)
    {
        return $this->exportRisultati($comune, 'mq');
    }

    public function exportRisultatiComponenti(string $comune)
    {
        return $this->exportRisultati($comune, 'componenti');
    }

    private function exportRisultati(string $comune, string $tipo)
    {
        $this->setComune($comune);
        $batchImmobili = $this->ultimoBatch('file1_immobili');
        $tabella = $tipo === 'mq' ? 'bt_recupero_mq' : 'bt_recupero_componenti';
        $colonnaDiff = $tipo === 'mq' ? 'mq_diff' : 'componenti_diff';

        $righe = DB::table($tabella)
            ->where('import_batch_id', $batchImmobili)
            ->join('bt_tari_immobili', function ($join) use ($batchImmobili, $tabella) {
                $join->on('bt_tari_immobili.codice_utenza', '=', "{$tabella}.codice_utenza")
                    ->where('bt_tari_immobili.import_batch_id', '=', $batchImmobili);
            })
            ->select('bt_tari_immobili.*', "{$tabella}.{$colonnaDiff}", "{$tabella}.dettaglio_anni", "{$tabella}.totale_recuperabile")
            ->orderByDesc("{$tabella}.totale_recuperabile")
            ->get();

        $anni = [];
        foreach ($righe as $riga) {
            foreach (array_keys(json_decode($riga->dettaglio_anni, true) ?? []) as $anno) {
                $anni[$anno] = true;
            }
        }
        ksort($anni);
        $anni = array_keys($anni);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $intestazione = [
            'Codice utenza', 'Denominazione', 'Codice fiscale/P.IVA', 'Indirizzo immobile',
            'Foglio', 'Numero', 'Subalterno', 'Categoria catastale',
            $tipo === 'mq' ? 'Differenza mq' : 'Differenza componenti',
        ];
        foreach ($anni as $anno) {
            $intestazione[] = "Recuperabile {$anno}";
        }
        $intestazione[] = 'Totale recuperabile';
        $sheet->fromArray($intestazione, null, 'A1');

        $rigaExcel = 2;
        foreach ($righe as $riga) {
            $dettaglio = json_decode($riga->dettaglio_anni, true) ?? [];
            $colonne = [
                $riga->codice_utenza,
                $riga->denominazione,
                $riga->codice_fiscale_piva,
                $riga->indirizzo_immobile,
                $riga->foglio,
                $riga->numero,
                $riga->subalterno,
                $riga->categoria_catastale,
                $riga->{$colonnaDiff},
            ];
            foreach ($anni as $anno) {
                $colonne[] = $dettaglio[$anno]['totale'] ?? ($dettaglio[$anno]['errore'] ?? null);
            }
            $colonne[] = $riga->totale_recuperabile;

            $sheet->fromArray($colonne, null, "A{$rigaExcel}");
            $rigaExcel++;
        }

        $colonnaTotale = chr(ord('A') + count($intestazione) - 1);
        $sheet->setCellValue("A{$rigaExcel}", 'TOTALE');
        $sheet->setCellValue(
            "{$colonnaTotale}{$rigaExcel}",
            "=SUM({$colonnaTotale}2:{$colonnaTotale}".($rigaExcel - 1).')'
        );

        $fileName = "booster_tributi_recupero_{$tipo}_{$comune}_".now()->format('Ymd_His').'.xlsx';
        $tempPath = storage_path('app/booster_tributi_tmp/'.$fileName);
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
}
