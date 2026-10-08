<?php

namespace App\Jobs;

use App\Services\BoosterTributi\ComponentiFamiliari;
use App\Services\BoosterTributi\ComuneSchema;
use App\Services\BoosterTributi\FamiglieNonPresentiTari;
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
 * "FAMIGLIE NON PRESENTI IN TARI" (istruzioni cliente 2026-10-08): individua i
 * nuclei (FamiglieNonPresentiTari), prende la superficie dell'immobile di
 * residenza dal catasto fabbricati (DB informativo-immobili, solo categoria A,
 * versione attuale dell'unità) e quantifica il recupero dell'intera tariffa
 * domestica su 80% dei mq.
 */
class CalcolaFamiglieNonPresentiTari implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public $tries = 1;

    public function __construct(
        private string $codiceComune,
        private string $jobKey,
        private string $batchImmobili,
        private string $batchAnagrafeResidenza
    ) {}

    public function handle(): void
    {
        $cacheKey = "job_status_bt_{$this->jobKey}";
        Cache::put($cacheKey, ['status' => 'running', 'processate' => 0, 'totale' => 0], 14400);

        try {
            ComuneConnection::connetti($this->codiceComune);
            ComuneSchema::assicura();

            $famiglie = FamiglieNonPresentiTari::individua(
                DB::connection('pgsql')->table('bt_tari_immobili')->where('import_batch_id', $this->batchImmobili)->get(),
                DB::connection('pgsql')->table('bt_anagrafe_residenza')->where('import_batch_id', $this->batchAnagrafeResidenza)->get()
            );

            $catasto = $this->superficiCatasto($famiglie);
            $categoriaDomestica = $this->categoriaDomestica();
            $riduzioneUnicoOccupante = DB::connection('pgsql')->table('bt_riduzioni')
                ->whereRaw("LOWER(descrizione) LIKE '%unico%'")
                ->orderByDesc('anno')
                ->value('descrizione');

            $calcolatore = RecuperoCalculator::conConnessioniDefault();
            $risultati = [];
            $totale = count($famiglie);

            foreach ($famiglie as $indice => $famiglia) {
                if (($indice + 1) % 200 === 0) {
                    Cache::put($cacheKey, ['status' => 'running', 'processate' => $indice + 1, 'totale' => $totale], 14400);
                }

                $chiave = FamiglieNonPresentiTari::chiaveImmobile($famiglia['foglio'], $famiglia['particella'], $famiglia['sub']);
                $unita = $chiave !== null ? ($catasto[$chiave] ?? null) : null;

                $nota = null;
                $mqCalcolo = null;
                $esito = ['dettaglio_anni' => [], 'totale_recuperabile' => 0.0, 'totale_con_sanzioni_interessi' => 0.0];

                if ($famiglia['senza_riferimenti_catastali']) {
                    $nota = 'Senza riferimenti catastali in anagrafe: mq non determinabili';
                } elseif ($unita === null) {
                    $nota = 'Immobile non trovato nel catasto fabbricati (categoria A)';
                } elseif ($unita['superficie'] === null) {
                    $nota = 'Superficie catastale non presente';
                } elseif ($categoriaDomestica === null) {
                    $nota = 'Tariffario utenze domestiche non importato';
                } else {
                    $mqCalcolo = round($unita['superficie'] * 0.8, 2);
                    $esito = $calcolatore->calcolaUtenzaDomestica(
                        $categoriaDomestica,
                        $mqCalcolo,
                        $famiglia['n_componenti'],
                        $famiglia['date_ingresso'],
                        $famiglia['data_inizio'],
                        $riduzioneUnicoOccupante
                    );
                }

                $risultati[] = [
                    'import_batch_id' => $this->batchImmobili,
                    'codice_famiglia' => $famiglia['codice_famiglia'],
                    'intestatario' => $famiglia['intestatario'],
                    'codice_fiscale_intestatario' => $famiglia['codice_fiscale_intestatario'],
                    'n_componenti' => $famiglia['n_componenti'],
                    'indirizzo' => $famiglia['indirizzo'],
                    'data_inizio' => $famiglia['data_inizio'],
                    'foglio' => $famiglia['foglio'],
                    'particella' => $famiglia['particella'],
                    'sub' => $famiglia['sub'],
                    'categoria_catastale' => $unita['categoria'] ?? null,
                    'mq_catasto' => $unita['superficie'] ?? null,
                    'mq_calcolo' => $mqCalcolo,
                    'censito_con_precedenti' => $famiglia['censito_con_precedenti'],
                    'nota' => $nota,
                    'dettaglio_anni' => json_encode($esito['dettaglio_anni']),
                    'totale_recuperabile' => $esito['totale_recuperabile'],
                    'totale_con_sanzioni_interessi' => $esito['totale_con_sanzioni_interessi'],
                    'created_at' => now(),
                ];
            }

            DB::connection('pgsql')->table('bt_famiglie_non_tari')
                ->where('import_batch_id', $this->batchImmobili)
                ->delete();

            foreach (array_chunk($risultati, 500) as $chunk) {
                DB::connection('pgsql')->table('bt_famiglie_non_tari')->insert($chunk);
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'processate' => $totale,
                'totale' => $totale,
                'righe_risultato' => count($risultati),
            ], 14400);
        } catch (\Throwable $e) {
            Log::error('CalcolaFamiglieNonPresentiTari: '.$e->getMessage());
            Cache::put($cacheKey, ['status' => 'error', 'errore' => $e->getMessage()], 14400);
            throw $e;
        }
    }

    /**
     * Superficie e categoria della versione attuale (MAX(id) per id_immobile,
     * stesso criterio di CatastoImmobileController::getSubInfo) delle unità di
     * categoria A del comune, indicizzate per FamiglieNonPresentiTari::chiaveImmobile.
     *
     * @param  array<int,array<string,mixed>>  $famiglie
     * @return array<string,array{superficie: ?float, categoria: ?string}>
     */
    private function superficiCatasto(array $famiglie): array
    {
        $fogli = collect($famiglie)->pluck('foglio')->filter()->unique()
            ->map(fn ($f) => str_pad($f, 4, '0', STR_PAD_LEFT))
            ->values()->all();

        if ($fogli === []) {
            return [];
        }

        $segnaposti = implode(',', array_fill(0, count($fogli), '?'));
        $righe = DB::connection('pgsql2')->select("
            SELECT IDN.foglio, IDN.numero, IDN.sub, INFO.cat, INFO.superficie
            FROM c_fabb_info INFO
            INNER JOIN (
                SELECT id_immobile, MAX(id) AS max_id
                FROM c_fabb_info
                WHERE cod_com = ?
                GROUP BY id_immobile
            ) ULTIMA ON INFO.id = ULTIMA.max_id
            JOIN c_fabb_identificativi IDN ON IDN.id_fabb_info = INFO.id
            WHERE INFO.cat LIKE 'A%' AND IDN.foglio IN ({$segnaposti})
        ", array_merge([strtoupper($this->codiceComune)], $fogli));

        $superfici = [];
        foreach ($righe as $riga) {
            $chiave = FamiglieNonPresentiTari::chiaveImmobile($riga->foglio, $riga->numero, $riga->sub);
            if ($chiave === null) {
                continue;
            }

            $superficie = is_numeric($riga->superficie)
                ? (float) $riga->superficie
                : \App\Services\BoosterTributi\Coercion::decimale($riga->superficie);

            $superfici[$chiave] = [
                'superficie' => $superficie > 0 ? $superficie : null,
                'categoria' => $riga->cat,
            ];
        }

        return $superfici;
    }

    /**
     * Prefisso del codice tariffa delle utenze domestiche (es. "1" per "1.1" ...
     * "1.6"), riconosciuto dalle sottocategorie "N componenti" del File 3.
     */
    private function categoriaDomestica(): ?string
    {
        $righe = DB::connection('pgsql')->table('bt_tariffario')
            ->select('codice_tariffa', 'sottocategoria')
            ->distinct()
            ->get();

        foreach ($righe as $riga) {
            if (ComponentiFamiliari::numeroDaSottocategoria($riga->sottocategoria) !== null
                && str_contains(mb_strtolower((string) $riga->sottocategoria), 'compon')) {
                return explode('.', $riga->codice_tariffa, 2)[0];
            }
        }

        return null;
    }

    public function failed(\Throwable $e): void
    {
        Cache::put("job_status_bt_{$this->jobKey}", ['status' => 'error', 'errore' => $e->getMessage()], 14400);
    }
}
