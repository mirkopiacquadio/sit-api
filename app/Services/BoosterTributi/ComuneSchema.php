<?php

namespace App\Services\BoosterTributi;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea (in modo idempotente) le tabelle bt_* nel database webgis del comune
 * correntemente selezionato tramite ComuneConnection::connetti(). Va invocata
 * dopo aver puntato la connessione 'pgsql' al comune giusto.
 */
class ComuneSchema
{
    private const CONNECTION = 'pgsql';

    public static function assicura(): void
    {
        self::importBatch();
        self::tariImmobili();
        self::tariDettaglioSottocategoria();
        self::tariffario();
        self::riduzioni();
        self::anagrafeResidenza();
        self::anomalieSnapshot();
        self::recuperoMq();
        self::recuperoComponenti();
        self::colonneRecupero();
    }

    /**
     * Colonne aggiunte dopo il primo rilascio (annotazioni cliente 2026-10-05):
     * le tabelle bt_recupero_* esistono già sui DB dei comuni, quindi vanno
     * aggiunte con ALTER e non solo nel create().
     */
    private static function colonneRecupero(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        foreach (['bt_recupero_mq', 'bt_recupero_componenti'] as $tabella) {
            if (! $schema->hasColumn($tabella, 'totale_con_sanzioni_interessi')) {
                $schema->table($tabella, function (Blueprint $table) {
                    $table->decimal('totale_con_sanzioni_interessi', 12, 2)->nullable();
                });
            }

            if (! $schema->hasColumn($tabella, 'data_inizio_validita')) {
                $schema->table($tabella, function (Blueprint $table) {
                    $table->date('data_inizio_validita')->nullable();
                });
            }
        }

        foreach (['data_variazione_nucleo', 'data_inizio_recupero'] as $colonna) {
            if (! $schema->hasColumn('bt_recupero_componenti', $colonna)) {
                $schema->table('bt_recupero_componenti', function (Blueprint $table) use ($colonna) {
                    $table->date($colonna)->nullable();
                });
            }
        }

        if (! $schema->hasColumn('bt_recupero_componenti', 'componenti_dichiarati')) {
            $schema->table('bt_recupero_componenti', function (Blueprint $table) {
                $table->unsignedInteger('componenti_dichiarati')->nullable();
            });
        }
    }

    private static function importBatch(): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable('bt_import_batch')) {
            return;
        }

        Schema::connection(self::CONNECTION)->create('bt_import_batch', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tipo_file', 40);
            $table->unsignedSmallInteger('anno')->nullable();
            $table->timestamp('imported_at');
            $table->unsignedInteger('righe_importate')->default(0);
            $table->string('esito', 20)->default('ok');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    private static function tariImmobili(): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable('bt_tari_immobili')) {
            return;
        }

        Schema::connection(self::CONNECTION)->create('bt_tari_immobili', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_batch_id')->index();
            $table->unsignedBigInteger('codice_utenza')->index();
            $table->string('denominazione')->nullable();
            $table->char('tipo_persona', 1)->nullable();
            $table->string('codice_fiscale_piva', 20)->nullable()->index();
            $table->date('data_decesso')->nullable();
            $table->string('indirizzo_residenza')->nullable();
            $table->string('indirizzo_recapito')->nullable();
            $table->string('indirizzo_immobile')->nullable();
            $table->unsignedInteger('foglio')->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->unsignedInteger('subalterno')->nullable();
            $table->string('categoria_catastale', 10)->nullable();
            $table->boolean('immobile_accessorio')->default(false);
            $table->unsignedInteger('componenti_residenti')->default(0);
            $table->unsignedInteger('componenti_non_residenti')->default(0);
            $table->date('data_inizio_validita')->nullable();
            $table->decimal('mq_tari', 10, 2)->nullable();
            $table->decimal('mq_catasto', 10, 2)->nullable();
            $table->decimal('importo_dovuto', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'codice_utenza']);
        });
    }

    private static function tariDettaglioSottocategoria(): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable('bt_tari_dettaglio_sottocategoria')) {
            return;
        }

        Schema::connection(self::CONNECTION)->create('bt_tari_dettaglio_sottocategoria', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_batch_id')->index();
            $table->unsignedBigInteger('codice_utenza')->index();
            $table->string('codice_tariffa', 20)->nullable();
            $table->string('sottocategoria')->nullable();
            $table->decimal('mq', 10, 2)->nullable();
            $table->decimal('tariffa_fissa', 12, 6)->nullable();
            $table->decimal('tariffa_variabile', 12, 6)->nullable();
            $table->string('riduzione_1')->nullable();
            $table->string('riduzione_2')->nullable();
            $table->string('riduzione_3')->nullable();
            $table->date('data_inizio_validita')->nullable();
            $table->decimal('importo_dovuto', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'codice_utenza']);
        });
    }

    private static function tariffario(): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable('bt_tariffario')) {
            return;
        }

        Schema::connection(self::CONNECTION)->create('bt_tariffario', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anno')->index();
            $table->string('codice_tariffa', 20);
            $table->string('categoria')->nullable();
            $table->string('sottocategoria')->nullable();
            $table->string('tipo_utenza', 30)->nullable();
            $table->decimal('tariffa_fissa', 12, 6)->nullable();
            $table->decimal('tariffa_variabile', 12, 6)->nullable();
            $table->timestamps();

            $table->unique(['anno', 'codice_tariffa']);
        });
    }

    private static function riduzioni(): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable('bt_riduzioni')) {
            return;
        }

        Schema::connection(self::CONNECTION)->create('bt_riduzioni', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anno')->index();
            $table->string('codice', 20);
            $table->string('descrizione')->nullable();
            $table->decimal('percentuale', 6, 2)->nullable();
            $table->string('applicazione', 30)->nullable();
            $table->timestamps();

            $table->unique(['anno', 'codice']);
        });
    }

    /**
     * "File 6 - Dati anagrafici residenza" (annotazioni cliente 2026-10-07):
     * sostituisce i vecchi File 6/7/8 (bt_anagrafe_famiglie/residenti/
     * gruppi_famiglia, lasciate sui DB già esistenti ma non più usate). Una riga
     * per residente, con codice famiglia e n. componenti del nucleo.
     */
    private static function anagrafeResidenza(): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable('bt_anagrafe_residenza')) {
            return;
        }

        Schema::connection(self::CONNECTION)->create('bt_anagrafe_residenza', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_batch_id')->index();
            $table->string('codice_utente', 30)->nullable();
            $table->string('nominativo')->nullable();
            $table->string('codice_fiscale', 20)->nullable()->index();
            $table->date('data_nascita')->nullable();
            $table->date('data_decesso')->nullable();
            $table->date('data_immigrazione')->nullable();
            $table->string('indirizzo_attuale')->nullable();
            $table->date('data_variazione_indirizzo')->nullable();
            $table->string('indirizzo_precedente')->nullable();
            $table->string('foglio_attuale', 20)->nullable();
            $table->string('particella_attuale', 20)->nullable();
            $table->string('sub_attuale', 20)->nullable();
            $table->string('foglio_precedente', 20)->nullable();
            $table->string('particella_precedente', 20)->nullable();
            $table->string('sub_precedente', 20)->nullable();
            $table->string('codice_famiglia', 30)->nullable()->index();
            $table->string('intestatario_famiglia')->nullable();
            $table->unsignedInteger('n_componenti')->nullable();
            $table->string('codice_fiscale_intestatario', 20)->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'codice_famiglia']);
        });
    }

    private static function anomalieSnapshot(): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable('bt_anomalie_snapshot')) {
            return;
        }

        Schema::connection(self::CONNECTION)->create('bt_anomalie_snapshot', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_batch_id')->index();
            $table->unsignedBigInteger('codice_utenza')->index();
            $table->json('tipi_anomalia');
            $table->json('dettaglio')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private static function recuperoMq(): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable('bt_recupero_mq')) {
            return;
        }

        Schema::connection(self::CONNECTION)->create('bt_recupero_mq', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_batch_id')->index();
            $table->unsignedBigInteger('codice_utenza')->index();
            $table->decimal('mq_diff', 10, 2);
            $table->json('dettaglio_anni');
            $table->decimal('totale_recuperabile', 12, 2);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private static function recuperoComponenti(): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable('bt_recupero_componenti')) {
            return;
        }

        Schema::connection(self::CONNECTION)->create('bt_recupero_componenti', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_batch_id')->index();
            $table->unsignedBigInteger('codice_utenza')->index();
            $table->integer('componenti_diff');
            $table->boolean('match_residenza_ubicazione')->nullable();
            $table->json('dettaglio_anni');
            $table->decimal('totale_recuperabile', 12, 2);
            $table->timestamp('created_at')->useCurrent();
        });
    }
}
