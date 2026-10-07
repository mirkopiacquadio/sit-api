<?php

namespace Tests\Unit\BoosterTributi;

use App\Services\BoosterTributi\AddressNormalizer;
use PHPUnit\Framework\TestCase;

class AddressNormalizerTest extends TestCase
{
    /**
     * Caso reale citato nelle ISTRUZIONI Monter: stesso immobile, scritto in modo
     * diverso nell'export TARI (File 1) e in quello Anagrafe (File 7).
     */
    public function test_riconosce_lo_stesso_indirizzo_scritto_diversamente(): void
    {
        $this->assertTrue(AddressNormalizer::corrispondono(
            'Contrada Longano n. 48',
            'CONTRADA LONGANO 48'
        ));
    }

    public function test_riconosce_indirizzi_uguali_con_interno_diversamente_scritto(): void
    {
        $this->assertTrue(AddressNormalizer::corrispondono(
            'Viale Vittorio Emanuele III Nr. 28 Int. 5',
            'VIALE VITTORIO EMANUELE III 28 I. 5'
        ));
    }

    public function test_indirizzi_diversi_non_corrispondono(): void
    {
        $this->assertFalse(AddressNormalizer::corrispondono(
            'Via Roma 12',
            'Contrada Piano del Mondo 4'
        ));
    }

    public function test_indirizzo_mancante_non_corrisponde(): void
    {
        $this->assertFalse(AddressNormalizer::corrispondono(null, 'Via Roma 12'));
        $this->assertFalse(AddressNormalizer::corrispondono('', 'Via Roma 12'));
    }

    /**
     * Casi reali Sant'Agata de' Goti (File 1 vs File 6 "Dati anagrafici residenza").
     */
    public function test_casi_reali_piano_interno_scala_e_snc(): void
    {
        $this->assertTrue(AddressNormalizer::corrispondono('Via Domenico mustilli n. 14 p. 2 i. 3', 'VIA DOMENICO MUSTILLI 14 I. 3 P. 2'));
        $this->assertTrue(AddressNormalizer::corrispondono('Via Domenico mustilli n. 2 s. A p. 1 i. 03', 'VIA DOMENICO MUSTILLI 2 I. 03 P. 1 S. A'));
        $this->assertTrue(AddressNormalizer::corrispondono('Via Santisi', 'VIA SANTISI SNC'));
        $this->assertTrue(AddressNormalizer::corrispondono('Via Pennino', 'VIA PENNINO 42'));
        $this->assertTrue(AddressNormalizer::corrispondono('Via S.antonio abate n. 13', "VIA SANT'ANTONIO ABATE 13"));
        $this->assertTrue(AddressNormalizer::corrispondono('Via 4 novembre n. 12', 'VIA 4 NOVEMBRE 12'));
    }

    public function test_stessa_via_civico_diverso_non_corrisponde(): void
    {
        $this->assertFalse(AddressNormalizer::corrispondono('Via Domenico mustilli n. 30 p. S1', 'VIA DOMENICO MUSTILLI 14'));
        $this->assertFalse(AddressNormalizer::corrispondono('Piazza Trento n. 12', 'VIA LUIGI EINAUDI 17'));
    }
}
