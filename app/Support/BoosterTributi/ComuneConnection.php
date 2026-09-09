<?php

namespace App\Support\BoosterTributi;

use Illuminate\Support\Facades\DB;

/**
 * Risoluzione comune -> database "{comune}-webgis" per il modulo BoosterTributi.
 *
 * Mappa duplicata volutamente rispetto a BoosterController::$nomiDb: i due moduli
 * sono indipendenti e la centralizzazione delle mappe comuni è già tracciata come
 * debito tecnico in docs/PIANO_AMMODERNAMENTO_CATASTO_URBANISTICA.md (Fase 3).
 */
class ComuneConnection
{
    /** @var array<string,string> codice comune => nome database webgis */
    private const DB_MAP = [
        'C659' => 'chiusanosandomenico-webgis',
        'C245' => 'castelpagano-webgis',
        'I197' => 'santagatadegoti-webgis',
    ];

    /** @var array<string,string> codice comune => nome leggibile, per il selettore UI */
    private const NOMI = [
        'C659' => 'Chiusano San Domenico',
        'C245' => 'Castelpagano',
        'I197' => 'Sant\'Agata dei Goti',
    ];

    /**
     * @return array<string,string>
     */
    public static function elenco(): array
    {
        return self::NOMI;
    }

    public static function isValido(string $codiceComune): bool
    {
        return array_key_exists(strtoupper($codiceComune), self::DB_MAP);
    }

    public static function nomeDatabase(string $codiceComune): string
    {
        $codiceComune = strtoupper($codiceComune);

        if (! array_key_exists($codiceComune, self::DB_MAP)) {
            throw new \InvalidArgumentException("Codice comune non valido: {$codiceComune}");
        }

        return self::DB_MAP[$codiceComune];
    }

    /**
     * Punta la connessione 'pgsql' al database webgis del comune indicato.
     */
    public static function connetti(string $codiceComune): void
    {
        $dbName = self::nomeDatabase($codiceComune);

        DB::purge('pgsql');
        config(['database.connections.pgsql.database' => $dbName]);
        DB::reconnect('pgsql');
    }
}
