<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PolygonLab\IdentifiantsEntreprise\NomenclatureNaf;

final class NomenclatureNafTest extends TestCase
{
    public function testValidityDates(): void
    {
        self::assertSame('2008-01-01', NomenclatureNaf::REV2->validFrom()->format('Y-m-d'));
        self::assertSame('2026-12-31', NomenclatureNaf::REV2->validUntil()?->format('Y-m-d'));
        self::assertSame('2027-01-01', NomenclatureNaf::NAF2025->validFrom()->format('Y-m-d'));
        self::assertNull(NomenclatureNaf::NAF2025->validUntil());
        self::assertSame('00:00:00 Europe/Paris', NomenclatureNaf::NAF2025->validFrom()->format('H:i:s e'));
    }

    public function testLabels(): void
    {
        self::assertSame('NAF rév. 2', NomenclatureNaf::REV2->label());
        self::assertSame('NAF 2025', NomenclatureNaf::NAF2025->label());
    }

    /**
     * @return iterable<string, array{string, NomenclatureNaf|null}>
     */
    public static function dates(): iterable
    {
        yield 'avant la NAF rév. 2' => ['2007-12-31 23:59:59 Europe/Paris', null];
        yield 'premier jour de la NAF rév. 2' => ['2008-01-01 00:00:00 Europe/Paris', NomenclatureNaf::REV2];
        yield 'veille de la bascule, dernière seconde' => ['2026-12-31 23:59:59 Europe/Paris', NomenclatureNaf::REV2];
        yield 'jour de la bascule' => ['2027-01-01 00:00:00 Europe/Paris', NomenclatureNaf::NAF2025];
        yield 'bien après' => ['2040-06-15 12:00:00 UTC', NomenclatureNaf::NAF2025];
        yield 'jour lu dans le fuseau de la date' => ['2026-12-31 23:30:00 UTC', NomenclatureNaf::REV2];
        yield 'même instant, autre fuseau' => ['2027-01-01 00:30:00 Europe/Paris', NomenclatureNaf::NAF2025];
    }

    #[DataProvider('dates')]
    public function testNomenclatureInForceOnADate(string $date, ?NomenclatureNaf $expected): void
    {
        $day = new \DateTimeImmutable($date);

        self::assertSame($expected, NomenclatureNaf::enVigueurLe($day));
        foreach (NomenclatureNaf::cases() as $nomenclature) {
            self::assertSame($nomenclature === $expected, $nomenclature->isInForceOn($day));
        }
    }
}
