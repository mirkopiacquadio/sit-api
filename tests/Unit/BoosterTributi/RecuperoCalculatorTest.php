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
    private const RATE_INTERESSE = [2021 => 0.0001, 2022 => 0.0125, 2023 => 0.05, 2024 => 0.025, 2025 => 0.02];

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

    public function test_anno_corrente_non_ha_sanzioni_ne_interessi(): void
    {
        $calc = $this->calcolatore(tariffe: [2026 => ['1.1' => ['fissa' => 1.0]]], annoCorrente: 2026);

        $esito = $calc->calcola(10.0, '1.1', '2026-01-01', 'fissa');

        $this->assertSame(['dovuto' => 10.0, 'sanzione' => 0.0, 'interessi' => 0.0, 'riduzione_applicata' => 0.0, 'totale' => 10.0, 'tipo' => 'ruolo'], $esito['dettaglio_anni'][2026]);
        $this->assertSame(10.0, $esito['totale_recuperabile']);
    }

    public function test_accertamento_multi_anno_con_sanzione_e_interessi_cumulati(): void
    {
        $tariffe = [];
        foreach ([2023, 2024, 2025, 2026] as $anno) {
            $tariffe[$anno]['1.1']['fissa'] = 1.0;
        }
        $calc = $this->calcolatore($tariffe, annoCorrente: 2026);

        $esito = $calc->calcola(10.0, '1.1', '2023-01-01', 'fissa');

        // 2023: sanzione 30% + interessi 2023+2024+2025 = 5%+2.5%+2% = 9.5% di 10 = 0.95
        $this->assertEqualsWithDelta(13.95, $esito['dettaglio_anni'][2023]['totale'], 0.001);
        // 2024: interessi 2024+2025 = 4.5% di 10 = 0.45
        $this->assertEqualsWithDelta(13.45, $esito['dettaglio_anni'][2024]['totale'], 0.001);
        // 2025: interessi solo 2025 = 2% di 10 = 0.2
        $this->assertEqualsWithDelta(13.20, $esito['dettaglio_anni'][2025]['totale'], 0.001);
        // 2026 (ruolo): nessuna sanzione/interesse
        $this->assertEqualsWithDelta(10.0, $esito['dettaglio_anni'][2026]['totale'], 0.001);

        $this->assertEqualsWithDelta(50.60, $esito['totale_recuperabile'], 0.001);
        $this->assertSame([], $esito['anni_mancanti']);
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
}
