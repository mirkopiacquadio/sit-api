<?php

namespace Tests\Unit\BoosterTributi;

use App\Services\BoosterTributi\Coercion;
use PHPUnit\Framework\TestCase;

class CoercionTest extends TestCase
{
    /**
     * Le date testuali degli export Halley sono gg/mm/aaaa: strtotime() le
     * leggerebbe all'americana (01/12/2026 -> 12 gennaio).
     *
     * @dataProvider date
     */
    public function test_data_formato_italiano(string $testo, ?string $atteso): void
    {
        $this->assertSame($atteso, Coercion::data($testo));
    }

    public static function date(): array
    {
        return [
            ['01/12/2026', '2026-12-01'],
            ['25/12/2026', '2026-12-25'],
            ['1-2-2022', '2022-02-01'],
            ['15/06/2026 00:00:00', '2026-06-15'],
            ['05.03.85', '1985-03-05'],
            ['2024-07-02', '2024-07-02'],
            ['31/02/2024', null],
        ];
    }
}
