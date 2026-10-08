<?php

namespace App\Jobs;

use App\Services\BoosterTributi\AddressNormalizer;
use App\Services\BoosterTributi\ComponentiFamiliari;
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
 * "DIFFERENZE COMPONENTI FAMILIARI" (ISTRUZIONI Monter): confronta i componenti
 * dichiarati in TARI (sottocategoria File 2) con quelli reali del nucleo
 * anagrafico, vedi ComponentiFamiliari. Il match ubicazione immobile <-> residenza anagrafica
 * serve a scremare le seconde case (non necessariamente omissione).
 */
class CalcolaRecuperoComponentiFamiliari implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public $tries = 1;

    public function __construct(
        private string $codiceComune,
        private string $jobKey,
        private string $batchImmobili,
        private string $batchDettaglio,
        private string $batchAnagrafeResidenza
    ) {}

    public function handle(): void
    {
        $cacheKey = "job_status_bt_{$this->jobKey}";
        Cache::put($cacheKey, ['status' => 'running', 'processate' => 0, 'totale' => 0], 14400);

        try {
            ComuneConnection::connetti($this->codiceComune);
            ComuneSchema::assicura();

            $immobili = DB::connection('pgsql')->table('bt_tari_immobili')
                ->where('import_batch_id', $this->batchImmobili)
                ->get()
                ->filter(fn ($riga) => ComponentiFamiliari::immobileAnalizzabile($riga));

            $dettagli = DB::connection('pgsql')->table('bt_tari_dettaglio_sottocategoria')
                ->where('import_batch_id', $this->batchDettaglio)
                ->get()
                ->keyBy('codice_utenza');

            $componenti = ComponentiFamiliari::daBatch($this->batchAnagrafeResidenza);

            $calcolatore = RecuperoCalculator::conConnessioniDefault();
            $risultati = [];
            $processate = 0;
            $totale = $immobili->count();

            foreach ($immobili as $immobile) {
                $processate++;
                if ($processate % 200 === 0) {
                    Cache::put($cacheKey, ['status' => 'running', 'processate' => $processate, 'totale' => $totale], 14400);
                }

                $reali = $componenti->reali($immobile->codice_fiscale_piva);
                if ($reali === null) {
                    continue;
                }

                $dettaglio = $dettagli->get($immobile->codice_utenza);
                $componentiDichiarati = ComponentiFamiliari::dichiarati($dettaglio);
                if ($componentiDichiarati === null) {
                    continue;
                }

                $componentiDiff = $reali['n'] - $componentiDichiarati;
                if ($componentiDiff <= 0) {
                    continue;
                }

                $matchResidenza = AddressNormalizer::corrispondono($immobile->indirizzo_immobile, $reali['indirizzo']);

                $dataInizioValidita = $dettaglio->data_inizio_validita ?? $immobile->data_inizio_validita;

                $esito = $calcolatore->calcolaComponenti(
                    $dettaglio->codice_tariffa ?? null,
                    $componentiDichiarati,
                    $reali['n'],
                    $reali['date_ingresso'],
                    $dataInizioValidita,
                    [$dettaglio->riduzione_1, $dettaglio->riduzione_2, $dettaglio->riduzione_3]
                );

                $risultati[] = [
                    'import_batch_id' => $this->batchImmobili,
                    'codice_utenza' => $immobile->codice_utenza,
                    'componenti_dichiarati' => $componentiDichiarati,
                    'componenti_diff' => $componentiDiff,
                    'match_residenza_ubicazione' => $matchResidenza,
                    'data_inizio_validita' => $dataInizioValidita,
                    'data_variazione_nucleo' => $reali['data_variazione_nucleo'],
                    'data_inizio_recupero' => $esito['data_inizio_recupero'],
                    'dettaglio_anni' => json_encode($esito['dettaglio_anni']),
                    'totale_recuperabile' => $esito['totale_recuperabile'],
                    'totale_con_sanzioni_interessi' => $esito['totale_con_sanzioni_interessi'],
                    'created_at' => now(),
                ];
            }

            DB::connection('pgsql')->table('bt_recupero_componenti')
                ->where('import_batch_id', $this->batchImmobili)
                ->delete();

            foreach (array_chunk($risultati, 500) as $chunk) {
                DB::connection('pgsql')->table('bt_recupero_componenti')->insert($chunk);
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'processate' => $processate,
                'totale' => $totale,
                'righe_risultato' => count($risultati),
            ], 14400);
        } catch (\Throwable $e) {
            Log::error('CalcolaRecuperoComponentiFamiliari: '.$e->getMessage());
            Cache::put($cacheKey, ['status' => 'error', 'errore' => $e->getMessage()], 14400);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Cache::put("job_status_bt_{$this->jobKey}", ['status' => 'error', 'errore' => $e->getMessage()], 14400);
    }
}
