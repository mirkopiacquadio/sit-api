<?php

namespace App\Jobs;

use App\Services\BoosterTributi\ComuneSchema;
use App\Services\BoosterTributi\RecuperoCalculator;
use App\Support\BoosterTributi\ComuneConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "DIFFERENZE MQ TARI" (ISTRUZIONI Monter): differenza = 80% mq catasto - mq TARI
 * dichiarati; se positiva, quantifica il recupero (ruolo anno corrente +
 * accertamenti fino a 5 anni) via RecuperoCalculator sulla quota fissa.
 */
class CalcolaRecuperoMqTari implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 1;

    public function __construct(
        private string $codiceComune,
        private string $jobKey,
        private string $batchImmobili,
        private string $batchDettaglio
    ) {
    }

    public function handle(): void
    {
        $cacheKey = "job_status_bt_{$this->jobKey}";
        Cache::put($cacheKey, ['status' => 'running', 'processate' => 0, 'totale' => 0], 14400);

        try {
            ComuneConnection::connetti($this->codiceComune);
            ComuneSchema::assicura();

            $immobili = DB::connection('pgsql')->table('bt_tari_immobili')
                ->where('import_batch_id', $this->batchImmobili)
                ->get();

            $dettagli = DB::connection('pgsql')->table('bt_tari_dettaglio_sottocategoria')
                ->where('import_batch_id', $this->batchDettaglio)
                ->get()
                ->keyBy('codice_utenza');

            $calcolatore = RecuperoCalculator::conConnessioniDefault();
            $risultati = [];
            $processate = 0;
            $totale = $immobili->count();

            foreach ($immobili as $immobile) {
                $processate++;
                if ($processate % 200 === 0) {
                    Cache::put($cacheKey, ['status' => 'running', 'processate' => $processate, 'totale' => $totale], 14400);
                }

                if ($immobile->mq_catasto === null || $immobile->mq_tari === null) {
                    continue;
                }

                $mqDiff = round((float) $immobile->mq_catasto * 0.8 - (float) $immobile->mq_tari, 2);
                if ($mqDiff <= 0) {
                    continue;
                }

                $dettaglio = $dettagli->get($immobile->codice_utenza);
                if ($dettaglio === null) {
                    continue;
                }

                $esito = $calcolatore->calcola(
                    $mqDiff,
                    $dettaglio->codice_tariffa,
                    $dettaglio->data_inizio_validita ?? $immobile->data_inizio_validita,
                    'fissa',
                    [$dettaglio->riduzione_1, $dettaglio->riduzione_2, $dettaglio->riduzione_3]
                );

                $risultati[] = [
                    'import_batch_id' => $this->batchImmobili,
                    'codice_utenza' => $immobile->codice_utenza,
                    'mq_diff' => $mqDiff,
                    'dettaglio_anni' => json_encode($esito['dettaglio_anni']),
                    'totale_recuperabile' => $esito['totale_recuperabile'],
                    'created_at' => now(),
                ];
            }

            DB::connection('pgsql')->table('bt_recupero_mq')
                ->where('import_batch_id', $this->batchImmobili)
                ->delete();

            foreach (array_chunk($risultati, 500) as $chunk) {
                DB::connection('pgsql')->table('bt_recupero_mq')->insert($chunk);
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'processate' => $processate,
                'totale' => $totale,
                'righe_risultato' => count($risultati),
            ], 14400);
        } catch (\Throwable $e) {
            Log::error('CalcolaRecuperoMqTari: '.$e->getMessage());
            Cache::put($cacheKey, ['status' => 'error', 'errore' => $e->getMessage()], 14400);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Cache::put("job_status_bt_{$this->jobKey}", ['status' => 'error', 'errore' => $e->getMessage()], 14400);
    }
}
