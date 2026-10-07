<?php

namespace App\Services\BoosterTributi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mappatura ed inserimento nelle tabelle bt_* delle righe estratte dagli export
 * Halley (File 1-8, vedi docs/PIANO_AMMODERNAMENTO... per il contesto generale e
 * il piano BoosterTributi per la spiegazione di ogni file).
 *
 * Presuppone che ComuneConnection::connetti($codice) sia già stato chiamato dal
 * chiamante (Controller/Command) — questa classe scrive sempre sulla connessione
 * 'pgsql' corrente.
 */
class TariImportService
{
    private const CONNECTION = 'pgsql';

    public function importaFile1Immobili(string $path): array
    {
        $righe = ExcelImportReader::righeDati($path);
        $batchId = $this->creaBatch('file1_immobili');

        $insert = [];
        foreach ($righe as $riga) {
            $codiceUtenza = Coercion::intero($riga[17] ?? null);
            if ($codiceUtenza === null) {
                continue;
            }

            $now = now();
            $insert[] = [
                'import_batch_id' => $batchId,
                'codice_utenza' => $codiceUtenza,
                'denominazione' => Coercion::stringa($riga[0] ?? null),
                'tipo_persona' => Coercion::stringa($riga[1] ?? null),
                'codice_fiscale_piva' => Coercion::stringa($riga[2] ?? null),
                'data_decesso' => Coercion::data($riga[3] ?? null),
                'indirizzo_residenza' => Coercion::stringa($riga[4] ?? null),
                'indirizzo_recapito' => Coercion::stringa($riga[5] ?? null),
                'indirizzo_immobile' => Coercion::stringa($riga[6] ?? null),
                'foglio' => Coercion::intero($riga[7] ?? null),
                'numero' => Coercion::intero($riga[8] ?? null),
                'subalterno' => Coercion::intero($riga[9] ?? null),
                'categoria_catastale' => Coercion::stringa($riga[10] ?? null),
                'immobile_accessorio' => Coercion::booleano($riga[11] ?? null),
                'componenti_residenti' => Coercion::intero($riga[12] ?? null) ?? 0,
                'componenti_non_residenti' => Coercion::intero($riga[13] ?? null) ?? 0,
                'data_inizio_validita' => Coercion::data($riga[14] ?? null),
                'mq_tari' => Coercion::decimale($riga[15] ?? null),
                'mq_catasto' => Coercion::decimale($riga[16] ?? null),
                'importo_dovuto' => Coercion::decimale($riga[18] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->inserisci('bt_tari_immobili', $insert);
        $this->chiudiBatch($batchId, count($insert));

        return ['batch_id' => $batchId, 'righe' => count($insert)];
    }

    public function importaFile2Dettaglio(string $path): array
    {
        $righe = ExcelImportReader::righeDati($path);
        $batchId = $this->creaBatch('file2_dettaglio_sottocategoria');

        $insert = [];
        foreach ($righe as $riga) {
            $codiceUtenza = Coercion::intero($riga[22] ?? null);
            if ($codiceUtenza === null) {
                continue;
            }

            $now = now();
            $insert[] = [
                'import_batch_id' => $batchId,
                'codice_utenza' => $codiceUtenza,
                'codice_tariffa' => Coercion::stringa($riga[15] ?? null),
                'sottocategoria' => Coercion::stringa($riga[16] ?? null),
                'mq' => Coercion::decimale($riga[14] ?? null),
                'tariffa_fissa' => Coercion::decimale($riga[17] ?? null),
                'tariffa_variabile' => Coercion::decimale($riga[18] ?? null),
                'riduzione_1' => Coercion::stringa($riga[19] ?? null),
                'riduzione_2' => Coercion::stringa($riga[20] ?? null),
                'riduzione_3' => Coercion::stringa($riga[21] ?? null),
                'data_inizio_validita' => Coercion::data($riga[13] ?? null),
                'importo_dovuto' => Coercion::decimale($riga[23] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->inserisci('bt_tari_dettaglio_sottocategoria', $insert);
        $this->chiudiBatch($batchId, count($insert));

        return ['batch_id' => $batchId, 'righe' => count($insert)];
    }

    public function importaFile3Tariffario(string $path, int $anno): array
    {
        $righe = ExcelImportReader::righeDati($path);
        $batchId = $this->creaBatch('file3_tariffario', $anno);

        $insert = [];
        $categoriaCorrente = null;
        foreach ($righe as $riga) {
            $categoriaNum = Coercion::stringa($riga[0] ?? null);
            $sottocategoriaNum = Coercion::stringa($riga[2] ?? null);
            if ($categoriaNum === null || $sottocategoriaNum === null) {
                continue;
            }

            // Le colonne "categoria" sono valorizzate solo sulla prima riga del
            // gruppo nell'export Halley (celle unite) -> forward-fill.
            if (Coercion::stringa($riga[1] ?? null) !== null) {
                $categoriaCorrente = Coercion::stringa($riga[1]);
            }

            $now = now();
            $insert[] = [
                'anno' => $anno,
                'codice_tariffa' => $categoriaNum.'.'.$sottocategoriaNum,
                'categoria' => $categoriaCorrente,
                'sottocategoria' => Coercion::stringa($riga[3] ?? null),
                'tipo_utenza' => Coercion::stringa($riga[4] ?? null),
                'tariffa_fissa' => Coercion::decimale($riga[5] ?? null),
                'tariffa_variabile' => Coercion::decimale($riga[6] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::connection(self::CONNECTION)->table('bt_tariffario')->where('anno', $anno)->delete();
        $this->inserisci('bt_tariffario', $insert);
        $this->chiudiBatch($batchId, count($insert));

        return ['batch_id' => $batchId, 'righe' => count($insert)];
    }

    public function importaFile4Riduzioni(string $path, int $anno): array
    {
        $righe = ExcelImportReader::righeDati($path);
        $batchId = $this->creaBatch('file4_riduzioni', $anno);

        $insert = [];
        foreach ($righe as $riga) {
            $codice = Coercion::stringa($riga[0] ?? null);
            if ($codice === null) {
                continue;
            }

            $now = now();
            $insert[] = [
                'anno' => $anno,
                'codice' => $codice,
                'descrizione' => Coercion::stringa($riga[2] ?? null),
                'percentuale' => Coercion::decimale($riga[3] ?? null),
                'applicazione' => Coercion::stringa($riga[4] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::connection(self::CONNECTION)->table('bt_riduzioni')->where('anno', $anno)->delete();
        $this->inserisci('bt_riduzioni', $insert);
        $this->chiudiBatch($batchId, count($insert));

        return ['batch_id' => $batchId, 'righe' => count($insert)];
    }

    /**
     * "File 6 - Dati anagrafici residenza", colonne A-S: codice utente,
     * cognome e nome, CF, data nascita, data decesso, data immigrazione,
     * indirizzo attuale, data variazione indirizzo, indirizzo precedente,
     * foglio/particella/sub attuali, foglio/particella/sub precedenti, codice
     * famiglia, intestatario scheda famiglia, n. componenti, CF intestatario.
     */
    public function importaFile6AnagrafeResidenza(string $path): array
    {
        $righe = ExcelImportReader::righeDati($path);
        $batchId = $this->creaBatch('file6_anagrafe_residenza');

        $insert = [];
        foreach ($righe as $riga) {
            $codiceFiscale = Coercion::stringa($riga[2] ?? null);
            $codiceFamiglia = Coercion::stringa($riga[15] ?? null);
            if ($codiceFiscale === null && $codiceFamiglia === null) {
                continue;
            }

            $now = now();
            $insert[] = [
                'import_batch_id' => $batchId,
                'codice_utente' => Coercion::stringa($riga[0] ?? null),
                'nominativo' => Coercion::stringa($riga[1] ?? null),
                'codice_fiscale' => $codiceFiscale,
                'data_nascita' => Coercion::data($riga[3] ?? null),
                'data_decesso' => Coercion::data($riga[4] ?? null),
                'data_immigrazione' => Coercion::data($riga[5] ?? null),
                'indirizzo_attuale' => Coercion::stringa($riga[6] ?? null),
                'data_variazione_indirizzo' => Coercion::data($riga[7] ?? null),
                'indirizzo_precedente' => Coercion::stringa($riga[8] ?? null),
                'foglio_attuale' => Coercion::stringa($riga[9] ?? null),
                'particella_attuale' => Coercion::stringa($riga[10] ?? null),
                'sub_attuale' => Coercion::stringa($riga[11] ?? null),
                'foglio_precedente' => Coercion::stringa($riga[12] ?? null),
                'particella_precedente' => Coercion::stringa($riga[13] ?? null),
                'sub_precedente' => Coercion::stringa($riga[14] ?? null),
                'codice_famiglia' => $codiceFamiglia,
                'intestatario_famiglia' => Coercion::stringa($riga[16] ?? null),
                'n_componenti' => Coercion::intero($riga[17] ?? null),
                'codice_fiscale_intestatario' => Coercion::stringa($riga[18] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->inserisci('bt_anagrafe_residenza', $insert);
        $this->chiudiBatch($batchId, count($insert));

        return ['batch_id' => $batchId, 'righe' => count($insert)];
    }

    private function creaBatch(string $tipoFile, ?int $anno = null): string
    {
        $id = (string) Str::uuid();

        DB::connection(self::CONNECTION)->table('bt_import_batch')->insert([
            'id' => $id,
            'tipo_file' => $tipoFile,
            'anno' => $anno,
            'imported_at' => now(),
            'righe_importate' => 0,
            'esito' => 'ok',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function chiudiBatch(string $batchId, int $righe): void
    {
        DB::connection(self::CONNECTION)->table('bt_import_batch')
            ->where('id', $batchId)
            ->update(['righe_importate' => $righe, 'updated_at' => now()]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $righe
     */
    private function inserisci(string $tabella, array $righe): void
    {
        foreach (array_chunk($righe, 500) as $chunk) {
            DB::connection(self::CONNECTION)->table($tabella)->insert($chunk);
        }
    }
}
