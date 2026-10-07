<?php

namespace App\Services\BoosterTributi;

use Illuminate\Support\Facades\DB;

/**
 * Fotografia delle anomalie sul File 1 (TARI attivi), richiesta esplicitamente da
 * Monter come riepilogo indipendente dal calcolo di recupero: individua le righe
 * con dati mancanti o incoerenti PRIMA di usarle per il matching con catasto/anagrafe.
 *
 * Include anche le differenze "a favore del comune" (annotazioni cliente
 * 2026-10-05, punto 7): immobili dove mq TARI > 80% mq catasto o componenti
 * dichiarati > componenti anagrafici. Solo conteggio, niente €: servono a mostrare
 * quanto la banca dati TARI è soggetta a rettifiche/ricorsi. Il conteggio
 * componenti richiede File 7 + File 6/8 (e File 2 per i dichiarati): se mancano
 * viene semplicemente saltato.
 */
class AnomalyDetector
{
    private const CONNECTION = 'pgsql';

    /**
     * @return array{batch_id:string, totale_righe:int, righe_con_anomalie:int, per_tipo:array<string,int>}
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
            'mq_tari_in_eccesso' => 0,
            'componenti_tari_in_eccesso' => 0,
        ];

        $dettagli = ($batch = $this->ultimoBatch('file2_dettaglio_sottocategoria'))
            ? DB::connection(self::CONNECTION)->table('bt_tari_dettaglio_sottocategoria')
                ->where('import_batch_id', $batch)
                ->get()
                ->keyBy('codice_utenza')
            : collect();

        $batchResidenti = $this->ultimoBatch('file7_anagrafe_residenti');
        $batchFamiglie = $this->ultimoBatch('file6_anagrafe_famiglie');
        $batchGruppi = $this->ultimoBatch('file8_gruppi_famiglia');
        $componenti = $batchResidenti && ($batchFamiglie || $batchGruppi)
            ? ComponentiFamiliari::daBatch($batchResidenti, $batchFamiglie, $batchGruppi)
            : null;

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

            $mqEccesso = null;
            if ((float) $riga->mq_catasto > 0 && $riga->mq_tari !== null) {
                $mqDiff = round((float) $riga->mq_catasto * 0.8 - (float) $riga->mq_tari, 2);
                if ($mqDiff < 0) {
                    $mqEccesso = -$mqDiff;
                    $tipi[] = 'mq_tari_in_eccesso';
                }
            }

            $componentiEccesso = null;
            if ($componenti !== null && ComponentiFamiliari::immobileAnalizzabile($riga)) {
                $reali = $componenti->reali($riga->codice_fiscale_piva);
                if ($reali !== null) {
                    $diff = $reali['n'] - ComponentiFamiliari::dichiarati($dettagli->get($riga->codice_utenza), $riga);
                    if ($diff < 0) {
                        $componentiEccesso = -$diff;
                        $tipi[] = 'componenti_tari_in_eccesso';
                    }
                }
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
                    'mq_in_eccesso' => $mqEccesso,
                    'componenti_in_eccesso' => $componentiEccesso,
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
            'batch_id' => $importBatchId,
            'totale_righe' => $righe->count(),
            'righe_con_anomalie' => count($daInserire),
            'per_tipo' => $conteggi,
        ];
    }

    private function ultimoBatch(string $tipoFile): ?string
    {
        return DB::connection(self::CONNECTION)->table('bt_import_batch')
            ->where('tipo_file', $tipoFile)
            ->orderByDesc('imported_at')
            ->value('id');
    }
}
