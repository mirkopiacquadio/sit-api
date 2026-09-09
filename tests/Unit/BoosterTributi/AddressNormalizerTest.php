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
}
