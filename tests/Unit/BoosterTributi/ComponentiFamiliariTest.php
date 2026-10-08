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
            ['Sei o piu` componenti', 6],
            ['Abitazione', null],
            ['', null],
            [null, null],
        ];
    }

    public function test_dichiarati_solo_da_sottocategoria_file2(): void
    {
        $this->assertSame(6, ComponentiFamiliari::dichiarati((object) ['sottocategoria' => 'Sei o piu` componenti']));
        // utenza non domestica: non confrontabile (niente ripiego sul codice tariffa "2.18" -> 18)
        $this->assertNull(ComponentiFamiliari::dichiarati((object) ['sottocategoria' => 'Uffici,agenzie']));
        $this->assertNull(ComponentiFamiliari::dichiarati(null));
    }

    public function test_immobile_analizzabile_solo_persone_fisiche_categorie_abitative(): void
    {
        $this->assertTrue(ComponentiFamiliari::immobileAnalizzabile((object) ['tipo_persona' => 'F', 'categoria_catastale' => 'A/2']));
        $this->assertTrue(ComponentiFamiliari::immobileAnalizzabile((object) ['tipo_persona' => 'F', 'categoria_catastale' => 'C/06']));
        $this->assertFalse(ComponentiFamiliari::immobileAnalizzabile((object) ['tipo_persona' => 'F', 'categoria_catastale' => 'C/01']));
        $this->assertFalse(ComponentiFamiliari::immobileAnalizzabile((object) ['tipo_persona' => 'F', 'categoria_catastale' => 'D/1']));
        $this->assertFalse(ComponentiFamiliari::immobileAnalizzabile((object) ['tipo_persona' => 'G', 'categoria_catastale' => 'A/2']));
    }

    private function residente(array $campi): object
    {
        return (object) array_merge([
            'codice_fiscale' => null, 'codice_famiglia' => null, 'n_componenti' => null, 'indirizzo_attuale' => null,
            'data_nascita' => null, 'data_decesso' => null, 'data_immigrazione' => null, 'data_variazione_indirizzo' => null,
        ], $campi);
    }

    public function test_reali_dal_file6_con_cf_non_normalizzato_e_data_piu_recente_del_nucleo(): void
    {
        $componenti = new ComponentiFamiliari(collect([
            $this->residente(['codice_fiscale' => 'RSSMRA80A01H501U', 'codice_famiglia' => '10', 'n_componenti' => 3, 'indirizzo_attuale' => 'VIA ROMA 1', 'data_nascita' => '1980-01-01', 'data_immigrazione' => '2015-03-10']),
            $this->residente(['codice_fiscale' => 'BNCLRA82B41H501X', 'codice_famiglia' => '10', 'n_componenti' => 3, 'data_nascita' => '1982-02-01', 'data_variazione_indirizzo' => '2019-06-01']),
            // figlio nato nel 2023: è la variazione più recente del nucleo
            $this->residente(['codice_fiscale' => 'RSSLCU23C10H501Z', 'codice_famiglia' => '10', 'n_componenti' => 3, 'data_nascita' => '2023-03-10']),
            // altra famiglia: non deve contare
            $this->residente(['codice_fiscale' => 'VRDGPP90A01H501K', 'codice_famiglia' => '11', 'n_componenti' => 1, 'data_immigrazione' => '2025-01-01']),
        ]));

        $this->assertSame(
            ['n' => 3, 'indirizzo' => 'VIA ROMA 1', 'date_ingresso' => ['2015-03-10', '2019-06-01', '2023-03-10'], 'data_variazione_nucleo' => '2023-03-10'],
            $componenti->reali(' rssmra80a01h501u ')
        );
        $this->assertNull($componenti->reali('XXXXXX00X00X000X'));
    }

    public function test_residente_deceduto_non_viene_confrontato(): void
    {
        $componenti = new ComponentiFamiliari(collect([
            $this->residente(['codice_fiscale' => 'RSSMRA40A01H501U', 'codice_famiglia' => '5', 'n_componenti' => 2, 'data_decesso' => '2024-05-01']),
        ]));

        $this->assertNull($componenti->reali('RSSMRA40A01H501U'));
    }

    public function test_data_ingresso_del_componente(): void
    {
        $this->assertSame('2010-05-05', ComponentiFamiliari::dataIngresso($this->residente(['data_nascita' => '1950-01-01', 'data_immigrazione' => '2010-05-05'])));
        $this->assertSame('2023-03-10', ComponentiFamiliari::dataIngresso($this->residente(['data_nascita' => '2023-03-10'])));
        $this->assertNull(ComponentiFamiliari::dataIngresso($this->residente(['data_nascita' => '1940-01-01', 'data_decesso' => '2024-02-01'])));
    }
}
