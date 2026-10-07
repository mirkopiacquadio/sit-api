<?php

namespace Tests\Unit\BoosterTributi;

use App\Services\BoosterTributi\ComponentiFamiliari;
use PHPUnit\Framework\TestCase;

class ComponentiFamiliariTest extends TestCase
{
    /**
     * @dataProvider sottocategorie
     */
    public function test_numero_componenti_dalla_sottocategoria(?string $testo, ?int $atteso): void
    {
        $this->assertSame($atteso, ComponentiFamiliari::numeroDaSottocategoria($testo));
    }

    public static function sottocategorie(): array
    {
        return [
            ['3 componenti', 3],
            ['Tre componenti', 3],
            ['UN COMPONENTE', 1],
            ['Sei o più componenti', 6],
            ['Unico occupante', 1],
            ['Abitazione', null],
            ['', null],
            [null, null],
        ];
    }

    public function test_dichiarati_preferisce_sottocategoria_file2_al_file1(): void
    {
        $immobile = (object) ['componenti_residenti' => 3];

        $this->assertSame(2, ComponentiFamiliari::dichiarati((object) ['sottocategoria' => 'Due componenti', 'codice_tariffa' => '1.2'], $immobile));
        // sottocategoria illeggibile -> scaglione dal codice tariffa
        $this->assertSame(4, ComponentiFamiliari::dichiarati((object) ['sottocategoria' => 'Domestica', 'codice_tariffa' => '1.4'], $immobile));
        // nessun File 2 -> File 1
        $this->assertSame(3, ComponentiFamiliari::dichiarati(null, $immobile));
    }

    public function test_immobile_analizzabile_solo_persone_fisiche_categorie_abitative(): void
    {
        $this->assertTrue(ComponentiFamiliari::immobileAnalizzabile((object) ['tipo_persona' => 'F', 'categoria_catastale' => 'A/2']));
        $this->assertTrue(ComponentiFamiliari::immobileAnalizzabile((object) ['tipo_persona' => 'F', 'categoria_catastale' => 'C/06']));
        $this->assertFalse(ComponentiFamiliari::immobileAnalizzabile((object) ['tipo_persona' => 'F', 'categoria_catastale' => 'C/01']));
        $this->assertFalse(ComponentiFamiliari::immobileAnalizzabile((object) ['tipo_persona' => 'F', 'categoria_catastale' => 'D/1']));
        $this->assertFalse(ComponentiFamiliari::immobileAnalizzabile((object) ['tipo_persona' => 'G', 'categoria_catastale' => 'A/2']));
    }

    public function test_reali_da_file8_con_cf_non_normalizzato(): void
    {
        $componenti = new ComponentiFamiliari(
            collect(['RSSMRA80A01H501U' => (object) ['numero_famiglia' => 10, 'indirizzo_residenza' => 'VIA ROMA 1']]),
            collect([10 => (object) ['n_componenti' => 4]]),
            null
        );

        $this->assertSame(['n' => 4, 'indirizzo' => 'VIA ROMA 1'], $componenti->reali(' rssmra80a01h501u '));
        $this->assertNull($componenti->reali('XXXXXX00X00X000X'));
    }
}
