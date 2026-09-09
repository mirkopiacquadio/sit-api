<?php

namespace App\Services\BoosterTributi;

use Illuminate\Support\Facades\DB;

/**
 * Fotografia delle anomalie sul File 1 (TARI attivi), richiesta esplicitamente da
 * Monter come riepilogo indipendente dal calcolo di recupero: individua le righe
 * con dati mancanti o incoerenti PRIMA di usarle per il matching con catasto/anagrafe.
 */
class AnomalyDetector
{
    private const CONNECTION = 'pgsql';

    /**
     * @return array{totale_righe:int, righe_con_anomalie:int, per_tipo:array<string,int>}
     */
    public function rileva(string $importBatchId): array
    {
        $righe = DB::connection(self::CONNECTION)
            ->table('bt_tari_immobili')
            ->where('import_batch_id', $importBatchId)
            ->get();

        $conteggi = [
            'senza_intestatario' => 0,
            'senza_catasto' => 0,
            'senza_indirizzo' => 0,
            'componenti_zero_sospetti' => 0,
            'senza_mq_tari' => 0,
            'deceduto' => 0,
        ];

        $daInserire = [];
        $now = now();

        foreach ($righe as $riga) {
            $tipi = [];

            if (empty($riga->denominazione)) {
                $tipi[] = 'senza_intestatario';
            }

            if (empty($riga->foglio) || empty($riga->numero)) {
                $tipi[] = 'senza_catasto';
            }

            if (empty($riga->indirizzo_residenza) && empty($riga->indirizzo_recapito)) {
                $tipi[] = 'senza_indirizzo';
            }

            if ($riga->tipo_persona === 'F'
                && (int) $riga->componenti_residenti === 0
                && (int) $riga->componenti_non_residenti === 0) {
                $tipi[] = 'componenti_zero_sospetti';
            }

            if (empty($riga->mq_tari)) {
                $tipi[] = 'senza_mq_tari';
            }

            if (! empty($riga->data_decesso)) {
                $tipi[] = 'deceduto';
            }

            if ($tipi === []) {
                continue;
            }

            foreach ($tipi as $tipo) {
                $conteggi[$tipo]++;
            }

            $daInserire[] = [
                'import_batch_id' => $importBatchId,
                'codice_utenza' => $riga->codice_utenza,
                'tipi_anomalia' => json_encode($tipi),
                'dettaglio' => json_encode([
                    'denominazione' => $riga->denominazione,
                    'foglio' => $riga->foglio,
                    'numero' => $riga->numero,
                    'mq_tari' => $riga->mq_tari,
                    'mq_catasto' => $riga->mq_catasto,
                    'componenti_residenti' => $riga->componenti_residenti,
                    'componenti_non_residenti' => $riga->componenti_non_residenti,
                    'data_decesso' => $riga->data_decesso,
                ]),
                'created_at' => $now,
            ];
        }

        DB::connection(self::CONNECTION)->table('bt_anomalie_snapshot')
            ->where('import_batch_id', $importBatchId)
            ->delete();

        foreach (array_chunk($daInserire, 500) as $chunk) {
            DB::connection(self::CONNECTION)->table('bt_anomalie_snapshot')->insert($chunk);
        }

        return [
            'totale_righe' => $righe->count(),
            'righe_con_anomalie' => count($daInserire),
            'per_tipo' => $conteggi,
        ];
    }
}
