<?php

namespace Tests\Unit\BoosterTributi;

use App\Services\BoosterTributi\RecuperoCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Test puri (nessun DB) sul motore di calcolo recupero: le dipendenze
 * (tariffario/riduzioni/interessi) sono fixture in memoria, così questi test
 * girano anche senza un Postgres raggiungibile (vedi memoria di progetto:
 * la pipeline reale la testa l'utente dal vivo dopo il deploy).
 */
class RecuperoCalculatorTest extends TestCase
{
    private const RATE_INTERESSE = [2021 => 0.0001, 2022 => 0.0125, 2023 => 0.05, 2024 => 0.025, 2025 => 0.02, 2026 => 0.016];

    private function calcolatore(array $tariffe, array $riduzioni = [], int $annoCorrente = 2026): RecuperoCalculator
    {
        return new RecuperoCalculator(
            fn (int $anno, string $codice, string $quota) => $tariffe[$anno][$codice][$quota] ?? null,
            function (int $anno, string $nome, string $quota) use ($riduzioni) {
                foreach ($riduzioni as $r) {
                    if ($r['anno'] === $anno && mb_strtolower($r['descrizione']) === mb_strtolower($nome)) {
                        $applicazione = mb_strtolower($r['applicazione']);
                        if (str_contains($applicazione, $quota) || (str_contains($applicazione, 'fissa') && str_contains($applicazione, 'variabile'))) {
                            return $r['percentuale'] / 100;
                        }
                    }
                }

                return 0.0;
            },
            fn (int $anno) => self::RATE_INTERESSE[$anno] ?? 0.0,
            $annoCorrente
        );
    }

    public function test_anno_corrente_ha_sanzione_e_interessi_dell_anno_stesso(): void
    {
        $calc = $this->calcolatore(tariffe: [2026 => ['1.1' => ['fissa' => 10.0]]], annoCorrente: 2026);

        $esito = $calc->calcola(10.0, '1.1', '2026-01-01', 'fissa');

        // dovuto 100, sanzione 30, interessi 2026 1,6% = 1,6
        $this->assertSame(['giorni' => 365, 'dovuto' => 100.0, 'sanzione' => 30.0, 'interessi' => 1.6, 'riduzione_applicata' => 0.0, 'totale' => 131.6], $esito['dettaglio_anni'][2026]);
        $this->assertSame(100.0, $esito['totale_recuperabile']);
        $this->assertSame(131.6, $esito['totale_con_sanzioni_interessi']);
    }

    /**
     * Esempio del cliente (annotazioni 2026-10-05, punto 2): inizio validità
     * 01/01/2022 -> per il 2022 interessi 2022..2026 = 1,25+5+2,5+2+1,6 = 12,35%,
     * più sanzione 30% = +42,35%; per il 2023 solo interessi 2023..2026.
     */
    public function test_sanzione_e_interessi_cumulati_fino_all_anno_corrente_incluso(): void
    {
        $tariffe = [];
        foreach ([2022, 2023, 2024, 2025, 2026] as $anno) {
            $tariffe[$anno]['1.1']['fissa'] = 1.0;
        }
        $calc = $this->calcolatore($tariffe, annoCorrente: 2026);

        $esito = $calc->calcola(100.0, '1.1', '2022-01-01', 'fissa');

        $this->assertEqualsWithDelta(142.35, $esito['dettaglio_anni'][2022]['totale'], 0.001);
        // 2023: 30% + 5+2,5+2+1,6 = 41,1%
        $this->assertEqualsWithDelta(141.10, $esito['dettaglio_anni'][2023]['totale'], 0.001);
        // 2026: 30% + 1,6%
        $this->assertEqualsWithDelta(131.60, $esito['dettaglio_anni'][2026]['totale'], 0.001);

        $this->assertEqualsWithDelta(500.0, $esito['totale_recuperabile'], 0.001);
        $this->assertEqualsWithDelta(142.35 + 141.10 + 136.10 + 133.60 + 131.60, $esito['totale_con_sanzioni_interessi'], 0.001);
        $this->assertSame([], $esito['anni_mancanti']);
    }

    /**
     * Annotazioni 2026-10-05, punto 1: inizio validità 01/12/2026 -> per il 2026
     * conta solo 31 giorni su 365, anche per sanzione e interessi.
     */
    public function test_pro_rata_giornaliero_nell_anno_di_inizio_validita(): void
    {
        $calc = $this->calcolatore(tariffe: [2026 => ['1.1' => ['fissa' => 365.0]]], annoCorrente: 2026);

        $esito = $calc->calcola(1.0, '1.1', '2026-12-01', 'fissa');

        $anno = $esito['dettaglio_anni'][2026];
        $this->assertSame(31, $anno['giorni']);
        $this->assertEqualsWithDelta(31.0, $anno['dovuto'], 0.001);
        $this->assertEqualsWithDelta(9.3, $anno['sanzione'], 0.001);
        // interessi 1,6% * 31/365 sul dovuto già proporzionato: 31 * 0.016 * 31/365 = 0,042 -> 0,04
        $this->assertEqualsWithDelta(0.04, $anno['interessi'], 0.001);
    }

    public function test_pro_rata_solo_sul_primo_anno_e_interessi_del_primo_anno_proporzionati(): void
    {
        $tariffe = [2025 => ['1.1' => ['fissa' => 365.0]], 2026 => ['1.1' => ['fissa' => 365.0]]];
        $calc = $this->calcolatore($tariffe, annoCorrente: 2026);

        // dal 02/07/2025: 183 giorni nel 2025
        $esito = $calc->calcola(1.0, '1.1', '2025-07-02', 'fissa');

        $this->assertSame(183, $esito['dettaglio_anni'][2025]['giorni']);
        $this->assertEqualsWithDelta(183.0, $esito['dettaglio_anni'][2025]['dovuto'], 0.001);
        // interessi 2025: 183 * (2% * 183/365 + 1,6%) = 183 * 0.026027... = 4,76
        $this->assertEqualsWithDelta(4.76, $esito['dettaglio_anni'][2025]['interessi'], 0.001);
        $this->assertSame(365, $esito['dettaglio_anni'][2026]['giorni']);
        $this->assertEqualsWithDelta(365.0, $esito['dettaglio_anni'][2026]['dovuto'], 0.001);
    }

    public function test_inizio_validita_prima_della_finestra_conta_anno_pieno(): void
    {
        $tariffe = [];
        for ($anno = 2021; $anno <= 2026; $anno++) {
            $tariffe[$anno]['1.1']['fissa'] = 365.0;
        }
        $calc = $this->calcolatore($tariffe, annoCorrente: 2026);

        $esito = $calc->calcola(1.0, '1.1', '2015-12-01', 'fissa');

        $this->assertSame(365, $esito['dettaglio_anni'][2021]['giorni']);
        $this->assertEqualsWithDelta(365.0, $esito['dettaglio_anni'][2021]['dovuto'], 0.001);
    }

    public function test_riduzione_applicabile_riduce_il_dovuto(): void
    {
        $tariffe = [2026 => ['1.1' => ['fissa' => 2.0]]];
        $riduzioni = [['anno' => 2026, 'descrizione' => 'Non residente', 'percentuale' => 20.0, 'applicazione' => 'Tariffa fissa']];
        $calc = $this->calcolatore($tariffe, $riduzioni, annoCorrente: 2026);

        $esito = $calc->calcola(5.0, '1.1', '2026-01-01', 'fissa', ['Non residente']);

        // dovuto = 5 * 2 * (1 - 0.20) = 8
        $this->assertEqualsWithDelta(8.0, $esito['totale_recuperabile'], 0.001);
    }

    public function test_riduzione_non_applicabile_alla_quota_viene_ignorata(): void
    {
        $tariffe = [2026 => ['1.1' => ['fissa' => 2.0]]];
        // riduzione esiste ma si applica solo alla quota variabile: sulla quota fissa non deve valere.
        $riduzioni = [['anno' => 2026, 'descrizione' => 'Non residente', 'percentuale' => 20.0, 'applicazione' => 'Tariffa variabile']];
        $calc = $this->calcolatore($tariffe, $riduzioni, annoCorrente: 2026);

        $esito = $calc->calcola(5.0, '1.1', '2026-01-01', 'fissa', ['Non residente']);

        $this->assertEqualsWithDelta(10.0, $esito['totale_recuperabile'], 0.001);
    }

    public function test_anno_senza_tariffario_viene_segnalato_e_non_blocca_gli_altri(): void
    {
        // manca il 2024 in tariffario
        $tariffe = [2023 => ['1.1' => ['fissa' => 1.0]], 2025 => ['1.1' => ['fissa' => 1.0]], 2026 => ['1.1' => ['fissa' => 1.0]]];
        $calc = $this->calcolatore($tariffe, annoCorrente: 2026);

        $esito = $calc->calcola(10.0, '1.1', '2023-01-01', 'fissa');

        $this->assertArrayHasKey('errore', $esito['dettaglio_anni'][2024]);
        $this->assertSame([2024], $esito['anni_mancanti']);
        $this->assertArrayNotHasKey('errore', $esito['dettaglio_anni'][2023]);
        $this->assertArrayNotHasKey('errore', $esito['dettaglio_anni'][2025]);
    }

    public function test_accertamento_limitato_a_5_anni_indietro_rispetto_a_oggi(): void
    {
        $tariffe = [];
        for ($anno = 2015; $anno <= 2026; $anno++) {
            $tariffe[$anno]['1.1']['fissa'] = 1.0;
        }
        $calc = $this->calcolatore($tariffe, annoCorrente: 2026);

        // data_inizio_validita molto vecchia (2015): deve comunque partire da 2026-5=2021.
        $esito = $calc->calcola(1.0, '1.1', '2015-06-01', 'fissa');

        $this->assertSame([2021, 2022, 2023, 2024, 2025, 2026], array_keys($esito['dettaglio_anni']));
    }

    public function test_codice_tariffa_mancante_esclude_tutti_gli_anni(): void
    {
        $calc = $this->calcolatore(tariffe: [], annoCorrente: 2026);

        $esito = $calc->calcola(10.0, null, '2026-01-01', 'fissa');

        $this->assertSame(0.0, $esito['totale_recuperabile']);
        $this->assertSame([2026], $esito['anni_mancanti']);
    }

    /**
     * Caso reale segnalato dal cliente (annotazioni 2026-09-27): passaggio da 1 a 3
     * componenti, tariffario File 3 vero (scaglioni non lineari). Il dovuto deve
     * essere la differenza tra gli scaglioni, non componenti_diff × tariffa di uno
     * scaglione: 398,126299 - 156,171089 = 241,95521.
     */
    public function test_calcola_componenti_usa_la_differenza_tra_scaglioni_non_lineare(): void
    {
        $tariffe = [2026 => [
            '1.1' => ['variabile' => 156.171089],
            '1.2' => ['variabile' => 307.942993],
            '1.3' => ['variabile' => 398.126299],
        ]];
        $calc = $this->calcolatore($tariffe, annoCorrente: 2026);

        $esito = $calc->calcolaComponenti('1.1', 3, '2026-01-01');

        // arrotondato a 2 decimali (valuta), come tutti gli altri importi della classe.
        $this->assertEqualsWithDelta(241.96, $esito['totale_recuperabile'], 0.001);
    }

    public function test_calcola_componenti_esclude_riduzione_unico_occupante_se_componenti_reali_maggiori_di_uno(): void
    {
        $tariffe = [2026 => [
            '1.1' => ['variabile' => 100.0],
            '1.3' => ['variabile' => 300.0],
        ]];
        $riduzioni = [
            ['anno' => 2026, 'descrizione' => 'Unico occupante', 'percentuale' => 20.0, 'applicazione' => 'Tariffa variabile'],
            ['anno' => 2026, 'descrizione' => 'Compostaggio', 'percentuale' => 30.0, 'applicazione' => 'Tariffa variabile'],
        ];
        $calc = $this->calcolatore($tariffe, $riduzioni, annoCorrente: 2026);

        $esito = $calc->calcolaComponenti('1.1', 3, '2026-01-01', ['Unico occupante', 'Compostaggio']);

        // dovuto = (300 - 100) * (1 - 0.30) = 140: "Unico occupante" esclusa, "Compostaggio" resta applicata.
        $this->assertEqualsWithDelta(140.0, $esito['totale_recuperabile'], 0.001);
    }

    public function test_calcola_componenti_scende_di_scaglione_se_componenti_reali_superano_il_massimo_importato(): void
    {
        // Tariffario importato solo fino a 6 componenti ("sei o più"): 8 reali deve ricadere sullo scaglione 6.
        $tariffe = [2026 => [
            '1.1' => ['variabile' => 100.0],
            '1.6' => ['variabile' => 500.0],
        ]];
        $calc = $this->calcolatore($tariffe, annoCorrente: 2026);

        $esito = $calc->calcolaComponenti('1.1', 8, '2026-01-01');

        $this->assertEqualsWithDelta(400.0, $esito['totale_recuperabile'], 0.001);
    }

    public function test_calcola_componenti_codice_dichiarato_mancante_esclude_tutti_gli_anni(): void
    {
        $calc = $this->calcolatore(tariffe: [], annoCorrente: 2026);

        $esito = $calc->calcolaComponenti(null, 3, '2026-01-01');

        $this->assertSame(0.0, $esito['totale_recuperabile']);
        $this->assertSame([2026], $esito['anni_mancanti']);
    }
}
