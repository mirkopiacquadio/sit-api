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

class AggiornaPropietariBooster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // 2 ore massimo
    public $tries = 1;

    public function __construct(
        private string $finalTable,
        private string $code_comune
    ) {}

    public function handle(): void
    {
        Log::info("JOB START: table={$this->finalTable} comune={$this->code_comune}");

        $booster = new BoosterController();

        // Chiama setDB tramite reflection perché è private
        $reflection = new \ReflectionMethod(BoosterController::class, 'setDB');
        $reflection->setAccessible(true);
        $reflection->invoke($booster, $this->code_comune);

        DB::purge('pgsql2');
        DB::reconnect('pgsql2');

        $totale = DB::table($this->finalTable)
            ->whereNull('proprietario')
            ->selectRaw('COUNT(DISTINCT ("FOGLIO", "PARTICELLA")) as cnt')
            ->value('cnt');

        Log::info("JOB: totale particelle da processare = $totale");

        $processed = 0;
        do {
            $records = DB::table($this->finalTable)
                ->select('FOGLIO', 'PARTICELLA')
                ->whereNull('proprietario')
                ->distinct()
                ->limit(50)
                ->get();

            if ($records->isEmpty()) break;

            foreach ($records as $record) {
                try {
                    $owners = $booster->getProprietariAttuali(
                        $this->code_comune,
                        $record->FOGLIO,
                        $record->PARTICELLA
                    );

                    if (!empty($owners)) {
                        $proprietarioStr = implode(' | ', array_map(
                            fn($o) => "{$o['nome']} ({$o['cf']}) - Titolo: {$o['titolo']} - {$o['descrizione']}",
                            $owners
                        ));
                        DB::table($this->finalTable)
                            ->where('FOGLIO', $record->FOGLIO)
                            ->where('PARTICELLA', $record->PARTICELLA)
                            ->update(['proprietario' => $proprietarioStr]);
                    } else {
                        DB::table($this->finalTable)
                            ->where('FOGLIO', $record->FOGLIO)
                            ->where('PARTICELLA', $record->PARTICELLA)
                            ->update(['proprietario' => '']);
                    }
                    $processed++;
                } catch (\Throwable $e) {
                    Log::error("JOB ERRORE particella {$record->FOGLIO}/{$record->PARTICELLA}: " . $e->getMessage());
                    // Marca come processata per non bloccarsi in loop
                    DB::table($this->finalTable)
                        ->where('FOGLIO', $record->FOGLIO)
                        ->where('PARTICELLA', $record->PARTICELLA)
                        ->update(['proprietario' => 'ERRORE']);
                }
            }

            Log::info("JOB: processate $processed / $totale");

        } while (true);

        Log::info("JOB COMPLETATO: table={$this->finalTable} totale processate=$processed");
    }
}