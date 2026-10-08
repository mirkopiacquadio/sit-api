<?php

namespace Tests\Unit\BoosterTributi;

use App\Services\BoosterTributi\FamiglieNonPresentiTari;
use PHPUnit\Framework\TestCase;

class FamiglieNonPresentiTariTest extends TestCase
{
    private function immobile(array $campi): object
    {
        return (object) array_merge(['tipo_persona' => 'F', 'codice_fiscale_piva' => null, 'foglio' => null, 'numero' => null, 'subalterno' => null], $campi);
    }

    private function residente(array $campi): object
    {
        return (object) array_merge([
            'codice_fiscale' => null, 'nominativo' => null, 'codice_famiglia' => null, 'n_componenti' => 1,
            'intestatario_famiglia' => null, 'codice_fiscale_intestatario' => null, 'indirizzo_attuale' => null,
            'data_nascita' => null, 'data_decesso' => null, 'data_immigrazione' => null, 'data_variazione_indirizzo' => null,
            'foglio_attuale' => null, 'particella_attuale' => null, 'sub_attuale' => null,
            'foglio_precedente' => null, 'particella_precedente' => null, 'sub_precedente' => null,
        ], $campi);
    }

    public function test_esclude_intestatari_tari_familiari_e_immobili_gia_censiti(): void
    {
        $tari = collect([
            $this->immobile(['codice_fiscale_piva' => 'AAA', 'foglio' => 39, 'numero' => 427, 'subalterno' => 11]),
            $this->immobile(['tipo_persona' => 'G', 'codice_fiscale_piva' => '01234567890', 'foglio' => 3, 'numero' => 1032, 'subalterno' => 3]),
            $this->immobile(['codice_fiscale_piva' => 'ZZZ', 'foglio' => 7, 'numero' => 50, 'subalterno' => null]),
        ]);

        $residenza = collect([
            // famiglia 1: l'intestatario TARI e il figlio -> esclusa per intero
            $this->residente(['codice_fiscale' => 'AAA', 'codice_famiglia' => '1', 'codice_fiscale_intestatario' => 'AAA']),
            $this->residente(['codice_fiscale' => 'AAB', 'codice_famiglia' => '1', 'codice_fiscale_intestatario' => 'AAA']),
            // famiglia 2: non in TARI ma l'immobile (3/1032/3) lo paga già qualcuno -> esclusa
            $this->residente(['codice_fiscale' => 'BBB', 'codice_famiglia' => '2', 'codice_fiscale_intestatario' => 'BBB', 'foglio_attuale' => '0003', 'particella_attuale' => '01032', 'sub_attuale' => '0003']),
            // famiglia 3: non in TARI, immobile non censito -> trovata; censita con i precedenti (7/50)
            $this->residente(['codice_fiscale' => 'CCC', 'codice_famiglia' => '3', 'codice_fiscale_intestatario' => 'CCC', 'intestatario_famiglia' => 'ROSSI MARIO', 'n_componenti' => 2,
                'foglio_attuale' => '12', 'particella_attuale' => '345', 'sub_attuale' => '6', 'foglio_precedente' => '7', 'particella_precedente' => '50',
                'data_nascita' => '1970-01-01', 'data_immigrazione' => '2018-04-01']),
            $this->residente(['codice_fiscale' => 'CCD', 'codice_famiglia' => '3', 'codice_fiscale_intestatario' => 'CCC', 'n_componenti' => 2, 'data_nascita' => '2023-06-01']),
            // famiglia 4: senza riferimenti catastali -> trovata, da segnalare
            $this->residente(['codice_fiscale' => 'DDD', 'codice_famiglia' => '4', 'codice_fiscale_intestatario' => 'DDD']),
            // famiglia 5: solo un deceduto -> ignorata
            $this->residente(['codice_fiscale' => 'EEE', 'codice_famiglia' => '5', 'data_decesso' => '2020-01-01']),
        ]);

        $famiglie = collect(FamiglieNonPresentiTari::individua($tari, $residenza))->keyBy('codice_famiglia');

        $this->assertSame(['3', '4'], $famiglie->pluck('codice_famiglia')->values()->all());

        $f3 = $famiglie['3'];
        $this->assertSame('ROSSI MARIO', $f3['intestatario']);
        $this->assertSame(2, $f3['n_componenti']);
        $this->assertSame(['12', '345', '6'], [$f3['foglio'], $f3['particella'], $f3['sub']]);
        $this->assertTrue($f3['censito_con_precedenti']);
        $this->assertFalse($f3['senza_riferimenti_catastali']);
        $this->assertSame('2018-04-01', $f3['data_inizio']);
        $this->assertSame(['2018-04-01', '2023-06-01'], $f3['date_ingresso']);

        $this->assertTrue($famiglie['4']['senza_riferimenti_catastali']);
    }

    public function test_chiave_immobile_ignora_zeri_iniziali(): void
    {
        $this->assertSame('39|427|11', FamiglieNonPresentiTari::chiaveImmobile('0039', '00427', '0011'));
        $this->assertSame('39|427|11', FamiglieNonPresentiTari::chiaveImmobile(39, 427, 11));
        $this->assertSame('39|427|', FamiglieNonPresentiTari::chiaveImmobile(39, 427, null));
        $this->assertNull(FamiglieNonPresentiTari::chiaveImmobile(null, 427, 1));
    }
}
