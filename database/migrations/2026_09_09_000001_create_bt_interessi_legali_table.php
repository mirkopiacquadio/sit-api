<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella nazionale dei tassi di interesse legale (fonte: File 5 - Tabella interessi
 * legali fornita da Monter), usata dal motore di calcolo recupero BoosterTributi.
 * Non è comune-specifica: vive su 'info-generali' come le altre tabelle anagrafiche
 * condivise (ana_comuni, nome_piani).
 *
 * Seed limitato al 1991-2026: gli accertamenti TARI coperti dal modulo guardano al
 * massimo 5 anni indietro rispetto all'anno corrente, quindi i periodi ante-1991
 * (con cambi di aliquota infra-annuali) sono fuori portata e volutamente omessi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('info-generali')->create('bt_interessi_legali', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anno')->unique();
            $table->decimal('percentuale', 6, 4);
            $table->timestamps();
        });

        $tassi = [
            1991 => 10.0000, 1992 => 10.0000, 1993 => 10.0000, 1994 => 10.0000,
            1995 => 10.0000, 1996 => 10.0000,
            1997 => 5.0000, 1998 => 5.0000,
            1999 => 2.5000, 2000 => 2.5000,
            2001 => 3.5000,
            2002 => 3.0000, 2003 => 3.0000,
            2004 => 2.5000, 2005 => 2.5000, 2006 => 2.5000, 2007 => 2.5000,
            2008 => 3.0000, 2009 => 3.0000,
            2010 => 1.0000,
            2011 => 1.5000,
            2012 => 2.5000, 2013 => 2.5000,
            2014 => 1.0000,
            2015 => 0.5000,
            2016 => 0.2000,
            2017 => 0.1000,
            2018 => 0.3000,
            2019 => 0.8000,
            2020 => 0.0500,
            2021 => 0.0100,
            2022 => 1.2500,
            2023 => 5.0000,
            2024 => 2.5000,
            2025 => 2.0000,
            2026 => 1.6000,
        ];

        $now = now();
        $rows = [];
        foreach ($tassi as $anno => $percentuale) {
            $rows[] = [
                'anno' => $anno,
                'percentuale' => $percentuale,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Schema::connection('info-generali')->getConnection()->table('bt_interessi_legali')->insert($rows);
    }

    public function down(): void
    {
        Schema::connection('info-generali')->dropIfExists('bt_interessi_legali');
    }
};
