<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BoosterController;
use App\Http\Controllers\CatastoImmobileController;
use Illuminate\Support\Facades\Cache;

class AggiornaPropietariBooster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // 2 ore massimo
    public $tries = 0;

    public function __construct(
        private string $finalTable,
        private string $code_comune
    ) {}

    public function handle(): void
    {
        $cacheKey = "job_status_{$this->finalTable}";

        Cache::put($cacheKey, [
            'status' => 'running',
            'processed' => 0,
            'total' => 0,
            'started_at' => now()->toDateTimeString(),
        ], 14400);

        Log::info("JOB START: table={$this->finalTable} comune={$this->code_comune}");

        $booster = new BoosterController();

        $reflection = new \ReflectionMethod(BoosterController::class, 'setDB');
        $reflection->setAccessible(true);
        $reflection->invoke($booster, $this->code_comune);

        DB::purge('pgsql2');
        DB::reconnect('pgsql2');

        $total = DB::table($this->finalTable)
            ->whereNull('proprietario')
            ->selectRaw('COUNT(DISTINCT ("FOGLIO", "PARTICELLA")) as cnt')
            ->value('cnt');

        Cache::put($cacheKey, [
            'status' => 'running',
            'processed' => 0,
            'total' => $total,
            'started_at' => now()->toDateTimeString(),
        ], 14400);

        Log::info("JOB: totale particelle da processare = $total");

        $processed = 0;
        do {
            $records = DB::table($this->finalTable)
                ->select('FOGLIO', 'PARTICELLA')
                ->whereNull('proprietario')
                ->distinct()
                ->limit(50)
                ->get();

            if ($records->isEmpty()) break;

            $catastoController = new CatastoImmobileController();

            foreach ($records as $record) {
                try {
                    // 1. Recupera info catasto (terreni + fabbricati con sub)
                    [$terreni, $fabbricati] = $catastoController->getSubInfo(
                        $this->code_comune,
                        $record->FOGLIO,
                        $record->PARTICELLA
                    );

                    // 2. Determina catasto_tipo dalla qualità del terreno
                    $catasto_tipo = 'Terreno';
                    if (!empty($terreni)) {
                        $catqua = strtolower(trim($terreni[0]->catqua ?? ''));
                        if ($catqua !== '') {
                            $catasto_tipo = ucwords($catqua);
                        }
                    } elseif (!empty($fabbricati)) {
                        $catasto_tipo = 'Fabbricato';
                    }

                    // 3. Proprietari della particella principale (sub = '')
                    $owners = $booster->getProprietariAttuali(
                        $this->code_comune,
                        $record->FOGLIO,
                        $record->PARTICELLA
                    );
                    $proprietarioStr = !empty($owners)
                        ? implode(' | ', array_map(
                            fn($o) => "{$o['nome']} ({$o['cf']}) - Titolo: {$o['titolo']} - {$o['descrizione']}",
                            $owners
                        ))
                        : '';

                    // 4. Proprietari per ogni sub (terreni + fabbricati)
                    // Unifica i sub da entrambe le liste, evitando duplicati per stesso sub
                    $subData = [];
                    $processedSubs = [];

                    $allSubRecords = array_merge(
                        array_map(fn($t) => (object)['sub' => $t->sub, 'tipo' => 'Terreno', 'catqua' => $t->catqua ?? ''], $terreni),
                        array_map(fn($f) => (object)['sub' => $f->sub, 'tipo' => 'Fabbricato', 'catqua' => $f->catqua ?? ''], $fabbricati)
                    );

                    foreach ($allSubRecords as $item) {
                        $sub = $item->sub ?? '';
                        if (empty($sub) || isset($processedSubs[$sub])) continue;
                        $processedSubs[$sub] = true;

                        $subOwners = $booster->getProprietariAttuali(
                            $this->code_comune,
                            $record->FOGLIO,
                            $record->PARTICELLA,
                            $sub
                        );

                        $subData[] = [
                            'sub'          => $sub,
                            'tipo'         => $item->tipo,
                            'catqua'       => $item->catqua,
                            'proprietario' => !empty($subOwners)
                                ? implode(' | ', array_map(
                                    fn($o) => "{$o['nome']} ({$o['cf']}) - Titolo: {$o['titolo']}",
                                    $subOwners
                                ))
                                : '',
                        ];
                    }

                    // 5. Aggiorna la riga principale
                    DB::table($this->finalTable)
                        ->where('FOGLIO', $record->FOGLIO)
                        ->where('PARTICELLA', $record->PARTICELLA)
                        ->update([
                            'proprietario' => $proprietarioStr,
                            'catasto_tipo'  => $catasto_tipo,
                            'sub_data'      => !empty($subData) ? json_encode($subData, JSON_UNESCAPED_UNICODE) : null,
                        ]);

                    $processed++;
                } catch (\Throwable $e) {
                    Log::error("JOB ERRORE {$record->FOGLIO}/{$record->PARTICELLA}: " . $e->getMessage());
                    DB::table($this->finalTable)
                        ->where('FOGLIO', $record->FOGLIO)
                        ->where('PARTICELLA', $record->PARTICELLA)
                        ->update(['proprietario' => 'ERRORE', 'catasto_tipo' => 'ERRORE']);
                }
            }

            Cache::put($cacheKey, [
                'status'     => 'running',
                'processed'  => $processed,
                'total'      => $total,
                'started_at' => now()->toDateTimeString(),
            ], 14400);

            Log::info("JOB: processate $processed / $total");
        } while (true);

        Cache::put($cacheKey, [
            'status'       => 'completed',
            'processed'    => $processed,
            'total'        => $total,
            'completed_at' => now()->toDateTimeString(),
        ], 14400);

        Log::info("JOB COMPLETATO: table={$this->finalTable} totale=$processed");
    }

    public function failed(\Throwable $exception): void
    {
        Cache::put("job_status_{$this->finalTable}", [
            'status' => 'error',
            'error'  => $exception->getMessage(),
        ], 14400);
    }
}
