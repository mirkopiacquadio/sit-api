<?php

namespace App\Jobs;

use App\Services\BoosterTributi\AddressNormalizer;
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
 * dichiarati in TARI con quelli reali del nucleo anagrafico (via CF -> famiglia
 * -> n. componenti), solo per persone fisiche e categorie abitative/pertinenze
 * (esclude B, D, F, C/01). Il match ubicazione immobile <-> residenza anagrafica
 * serve a scremare le seconde case (non necessariamente omissione).
 */
class CalcolaRecuperoComponentiFamiliari implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 1;

    private const CATEGORIE_ESCLUSE_PREFISSO = ['B', 'D', 'F'];
    private const CATEGORIE_ESCLUSE_ESATTE = ['C01', 'C/01'];

    public function __construct(
        private string $codiceComune,
        private string $jobKey,
        private string $batchImmobili,
        private string $batchDettaglio,
        private string $batchAnagrafeResidenti,
        private ?string $batchAnagrafeFamiglie,
        private ?string $batchGruppiFamiglia
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
                ->where('tipo_persona', 'F')
                ->get()
                ->filter(fn ($riga) => ! $this->categoriaEsclusa($riga->categoria_catastale));

            $dettagli = DB::connection('pgsql')->table('bt_tari_dettaglio_sottocategoria')
                ->where('import_batch_id', $this->batchDettaglio)
                ->get()
                ->keyBy('codice_utenza');

            $residenti = DB::connection('pgsql')->table('bt_anagrafe_residenti')
                ->where('import_batch_id', $this->batchAnagrafeResidenti)
                ->get()
                ->keyBy(fn ($r) => mb_strtoupper((string) $r->codice_fiscale));

            $gruppiPerFamiglia = null;
            if ($this->batchGruppiFamiglia) {
                $gruppiPerFamiglia = DB::connection('pgsql')->table('bt_anagrafe_gruppi_famiglia')
                    ->where('import_batch_id', $this->batchGruppiFamiglia)
                    ->get()
                    ->keyBy('numero_famiglia');
            }

            $famigliePerNumero = null;
            if ($this->batchAnagrafeFamiglie) {
                $famigliePerNumero = DB::connection('pgsql')->table('bt_anagrafe_famiglie')
                    ->where('import_batch_id', $this->batchAnagrafeFamiglie)
                    ->get()
                    ->keyBy('numero_famiglia');
            }

            $calcolatore = RecuperoCalculator::conConnessioniDefault();
            $risultati = [];
            $processate = 0;
            $totale = $immobili->count();

            foreach ($immobili as $immobile) {
                $processate++;
                if ($processate % 200 === 0) {
                    Cache::put($cacheKey, ['status' => 'running', 'processate' => $processate, 'totale' => $totale], 14400);
                }

                $cf = $immobile->codice_fiscale_piva ? mb_strtoupper($immobile->codice_fiscale_piva) : null;
                if ($cf === null) {
                    continue;
                }

                $residente = $residenti->get($cf);
                if ($residente === null) {
                    continue;
                }

                $componentiReali = null;
                $indirizzoResidenza = $residente->indirizzo_residenza;

                if ($gruppiPerFamiglia && $gruppiPerFamiglia->has($residente->numero_famiglia)) {
                    $componentiReali = (int) $gruppiPerFamiglia->get($residente->numero_famiglia)->n_componenti;
                } elseif ($famigliePerNumero && $famigliePerNumero->has($residente->numero_famiglia)) {
                    $famiglia = $famigliePerNumero->get($residente->numero_famiglia);
                    $componentiReali = (int) $famiglia->n_componenti;
                    $indirizzoResidenza = $indirizzoResidenza ?: $famiglia->indirizzo;
                }

                if ($componentiReali === null) {
                    continue;
                }

                $componentiDiff = $componentiReali - (int) $immobile->componenti_residenti;
                if ($componentiDiff <= 0) {
                    continue;
                }

                $matchResidenza = AddressNormalizer::corrispondono($immobile->indirizzo_immobile, $indirizzoResidenza);

                $dettaglio = $dettagli->get($immobile->codice_utenza);

                $esito = $calcolatore->calcola(
                    (float) $componentiDiff,
                    $dettaglio->codice_tariffa ?? null,
                    $dettaglio->data_inizio_validita ?? $immobile->data_inizio_validita,
                    'variabile',
                    $dettaglio ? [$dettaglio->riduzione_1, $dettaglio->riduzione_2, $dettaglio->riduzione_3] : []
                );

                $risultati[] = [
                    'import_batch_id' => $this->batchImmobili,
                    'codice_utenza' => $immobile->codice_utenza,
                    'componenti_diff' => $componentiDiff,
                    'match_residenza_ubicazione' => $matchResidenza,
                    'dettaglio_anni' => json_encode($esito['dettaglio_anni']),
                    'totale_recuperabile' => $esito['totale_recuperabile'],
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

    private function categoriaEsclusa(?string $categoria): bool
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

    public function failed(\Throwable $e): void
    {
        Cache::put("job_status_bt_{$this->jobKey}", ['status' => 'error', 'errore' => $e->getMessage()], 14400);
    }
}
