<?php

namespace App\Console\Commands;

use App\Services\BoosterTributi\ComuneSchema;
use App\Support\BoosterTributi\ComuneConnection;
use Illuminate\Console\Command;

class BoosterTributiMigrateComune extends Command
{
    protected $signature = 'booster-tributi:migrate-comune {codice : Codice del comune (es. C659)}';

    protected $description = 'Crea (se mancanti) le tabelle bt_* nel database webgis del comune indicato';

    public function handle(): int
    {
        $codice = strtoupper((string) $this->argument('codice'));

        if (! ComuneConnection::isValido($codice)) {
            $this->error("Codice comune non valido o non abilitato per BoosterTributi: {$codice}");

            return self::FAILURE;
        }

        ComuneConnection::connetti($codice);
        ComuneSchema::assicura();

        $this->info("Tabelle BoosterTributi pronte su ".ComuneConnection::nomeDatabase($codice));

        return self::SUCCESS;
    }
}
