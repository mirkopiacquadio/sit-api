<?php

namespace App\Services\BoosterTributi;

use Illuminate\Support\Collection;

/**
 * "FAMIGLIE NON PRESENTI IN TARI" (istruzioni cliente 2026-10-08): nuclei
 * anagrafici (File 6 "Dati anagrafici residenza") che non risultano né
 * intestatari di una scheda TARI (File 1) né familiari di un intestatario, e il
 * cui immobile di residenza (foglio/mappale/sub attuali) non è già censito in
 * TARI da qualcun altro (genitore, locatore...).
 *
 * Logica pura su collection in memoria (testata senza DB); i mq catastali e il
 * calcolo in € li aggiunge il Job CalcolaFamiglieNonPresentiTari.
 */
class FamiglieNonPresentiTari
{
    /**
     * @param  Collection<int,object>  $immobiliTari  righe bt_tari_immobili (File 1)
     * @param  Collection<int,object>  $residenza  righe bt_anagrafe_residenza (File 6)
     * @return array<int,array<string,mixed>> una voce per codice famiglia
     */
    public static function individua(Collection $immobiliTari, Collection $residenza): array
    {
        // Persone attuali: i deceduti del File 6 non sono residenti da tassare.
        $vivi = $residenza->filter(fn ($r) => empty($r->data_decesso) && $r->codice_famiglia !== null);

        $cfTari = $immobiliTari
            ->filter(fn ($i) => $i->tipo_persona === 'F')
            ->map(fn ($i) => ComponentiFamiliari::normalizzaCf($i->codice_fiscale_piva))
            ->filter()
            ->flip();

        // Famiglie di cui almeno un componente è intestatario TARI: escluse per intero.
        $famiglieTari = $vivi
            ->filter(fn ($r) => $cfTari->has(ComponentiFamiliari::normalizzaCf($r->codice_fiscale)))
            ->map(fn ($r) => self::chiaveFamiglia($r->codice_famiglia))
            ->flip();

        // Immobili già in TARI (qualsiasi utenza, anche persone giuridiche).
        $immobiliCensiti = $immobiliTari
            ->map(fn ($i) => self::chiaveImmobile($i->foglio, $i->numero, $i->subalterno))
            ->filter()
            ->flip();

        $famiglie = [];
        foreach ($vivi->groupBy(fn ($r) => self::chiaveFamiglia($r->codice_famiglia)) as $codiceFamiglia => $componenti) {
            if ($famiglieTari->has((string) $codiceFamiglia)) {
                continue;
            }

            $intestatario = $componenti->first(
                fn ($r) => ComponentiFamiliari::normalizzaCf($r->codice_fiscale) === ComponentiFamiliari::normalizzaCf($r->codice_fiscale_intestatario)
            ) ?? $componenti->first();

            $immobileAttuale = self::chiaveImmobile($intestatario->foglio_attuale, $intestatario->particella_attuale, $intestatario->sub_attuale);
            if ($immobileAttuale !== null && $immobiliCensiti->has($immobileAttuale)) {
                continue;
            }

            $immobilePrecedente = self::chiaveImmobile($intestatario->foglio_precedente, $intestatario->particella_precedente, $intestatario->sub_precedente);

            $dateIngresso = $componenti->map(fn ($r) => ComponentiFamiliari::dataIngresso($r))->filter()->values()->all();

            $famiglie[] = [
                'codice_famiglia' => (string) $codiceFamiglia,
                'intestatario' => $intestatario->intestatario_famiglia ?? $intestatario->nominativo,
                'codice_fiscale_intestatario' => $intestatario->codice_fiscale_intestatario ?? $intestatario->codice_fiscale,
                'n_componenti' => (int) ($intestatario->n_componenti ?? $componenti->count()),
                'indirizzo' => $intestatario->indirizzo_attuale,
                'date_ingresso' => $dateIngresso,
                'data_inizio' => $dateIngresso === [] ? null : min($dateIngresso),
                'foglio' => self::senzaZeri($intestatario->foglio_attuale),
                'particella' => self::senzaZeri($intestatario->particella_attuale),
                'sub' => self::senzaZeri($intestatario->sub_attuale),
                'senza_riferimenti_catastali' => $immobileAttuale === null,
                // Evidenza richiesta dal cliente: risulta censito in TARI con i
                // riferimenti catastali PRECEDENTI ma non con quelli attuali.
                'censito_con_precedenti' => $immobilePrecedente !== null && $immobiliCensiti->has($immobilePrecedente),
            ];
        }

        return $famiglie;
    }

    /**
     * "foglio|particella|sub" senza zeri iniziali (File 1 li ha numerici, File 6
     * testuali); null se mancano foglio o particella. Sub mancante = "".
     */
    public static function chiaveImmobile($foglio, $particella, $sub): ?string
    {
        $foglio = self::senzaZeri($foglio);
        $particella = self::senzaZeri($particella);

        if ($foglio === null || $particella === null) {
            return null;
        }

        return $foglio.'|'.$particella.'|'.(self::senzaZeri($sub) ?? '');
    }

    public static function senzaZeri($valore): ?string
    {
        if ($valore === null) {
            return null;
        }

        $valore = strtoupper(trim((string) $valore));
        if ($valore === '' || $valore === '0') {
            return null;
        }

        return ltrim($valore, '0') ?: null;
    }

    private static function chiaveFamiglia($codice): string
    {
        return trim((string) $codice);
    }
}
