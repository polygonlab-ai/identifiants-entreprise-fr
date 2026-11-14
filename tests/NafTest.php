<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PolygonLab\IdentifiantsEntreprise\CodeApe;
use PolygonLab\IdentifiantsEntreprise\IdentifiantInvalide;
use PolygonLab\IdentifiantsEntreprise\LabelFormat;
use PolygonLab\IdentifiantsEntreprise\Naf;
use PolygonLab\IdentifiantsEntreprise\NomenclatureNaf;

/**
 * Codes réels des nomenclatures officielles (données publiques de l'INSEE).
 */
final class NafTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function codes(): iterable
    {
        yield 'NAF rév. 2 : programmation informatique' => ['62.01Z', true, false];
        yield 'NAF rév. 2 : conseil en systèmes et logiciels' => ['62.02A', true, false];
        yield 'NAF 2025 : programmation informatique' => ['62.10Y', false, true];
        yield 'NAF 2025 : conseil en systèmes et logiciels' => ['62.20G', false, true];
        yield 'bien formé mais inexistant' => ['00.00A', false, false];
        yield 'classe existante, lettre inconnue' => ['62.01A', false, false];
    }

    #[DataProvider('codes')]
    public function testExistence(string $code, bool $inRev2, bool $inNaf2025): void
    {
        $ape = CodeApe::fromString($code);

        self::assertSame($inRev2, Naf::existe($ape, NomenclatureNaf::REV2));
        self::assertSame($inNaf2025, Naf::existe($ape, NomenclatureNaf::NAF2025));
        self::assertSame($inRev2, $ape->existe(NomenclatureNaf::REV2));
        self::assertSame($inNaf2025, $ape->existe(NomenclatureNaf::NAF2025));
    }

    public function testSubclassCountsAndOrder(): void
    {
        $rev2 = Naf::subclasses(NomenclatureNaf::REV2);
        $naf2025 = Naf::subclasses(NomenclatureNaf::NAF2025);

        self::assertCount(732, $rev2);
        self::assertCount(747, $naf2025);
        self::assertSame('01.11Z', $rev2[0]->format());
        self::assertSame('99.00Z', $rev2[731]->format());
        self::assertSame('01.11Y', $naf2025[0]->format());
        self::assertSame('99.00Y', $naf2025[746]->format());
    }

    public function testTheTwoNomenclaturesShareNoCode(): void
    {
        foreach (Naf::subclasses(NomenclatureNaf::REV2) as $code) {
            self::assertFalse(Naf::existe($code, NomenclatureNaf::NAF2025), $code->format());
        }
    }

    public function testEveryCodeIsAcceptedByCodeApe(): void
    {
        foreach (NomenclatureNaf::cases() as $nomenclature) {
            foreach (Naf::subclasses($nomenclature) as $code) {
                self::assertTrue(CodeApe::isValid($code->format()));
                self::assertSame($code->format(), CodeApe::fromString(str_replace('.', '', strtolower($code->format())))->format());
            }
        }
    }

    public function testLabelsOfTheNaf2025(): void
    {
        $code = CodeApe::fromString('62.20G');

        self::assertSame('Conseil en systèmes et logiciels informatiques', Naf::intitule($code, NomenclatureNaf::NAF2025));
        self::assertSame(
            'Conseil en systèmes et logiciels informatiques',
            Naf::intitule($code, NomenclatureNaf::NAF2025, LabelFormat::ABBREVIATED),
        );
        self::assertSame('Conseil en système & logiciel informati.', Naf::intitule($code, NomenclatureNaf::NAF2025, LabelFormat::SHORT));

        $long = CodeApe::fromString('01.11Y');
        self::assertSame(
            'Culture de céréales, à l’exception du riz, de légumineuses et de graines oléagineuses',
            Naf::intitule($long, NomenclatureNaf::NAF2025, LabelFormat::FULL),
        );
        self::assertSame(
            'Culture de céréales, légumineuses, graines oléagineuses, sauf riz',
            Naf::intitule($long, NomenclatureNaf::NAF2025, LabelFormat::ABBREVIATED),
        );
        self::assertSame('Cult céréa., légumin., gr. oléa., sf riz', Naf::intitule($long, NomenclatureNaf::NAF2025, LabelFormat::SHORT));
    }

    public function testAbbreviatedAndShortLabelsRespectTheirLength(): void
    {
        foreach (Naf::subclasses(NomenclatureNaf::NAF2025) as $code) {
            self::assertLessThanOrEqual(65, mb_strlen((string) Naf::intitule($code, NomenclatureNaf::NAF2025, LabelFormat::ABBREVIATED)));
            self::assertLessThanOrEqual(40, mb_strlen((string) Naf::intitule($code, NomenclatureNaf::NAF2025, LabelFormat::SHORT)));
        }
    }

    public function testLabelsOfTheRev2(): void
    {
        $code = CodeApe::fromString('62.01Z');

        self::assertSame('Programmation informatique', Naf::intitule($code, NomenclatureNaf::REV2));
        self::assertNull(Naf::intitule($code, NomenclatureNaf::REV2, LabelFormat::ABBREVIATED));
        self::assertNull(Naf::intitule($code, NomenclatureNaf::REV2, LabelFormat::SHORT));
        self::assertSame('Conseil en systèmes et logiciels informatiques', Naf::intitule(CodeApe::fromString('62.02A'), NomenclatureNaf::REV2));
    }

    public function testSections(): void
    {
        // La programmation informatique passe de la section J (NAF rév. 2) à la section K (NAF 2025).
        self::assertSame('J', Naf::section(CodeApe::fromString('62.01Z'), NomenclatureNaf::REV2));
        self::assertSame('K', Naf::section(CodeApe::fromString('62.10Y'), NomenclatureNaf::NAF2025));
        self::assertSame('J', CodeApe::fromString('62.10Y')->section(), 'CodeApe::section() reste celle de la NAF rév. 2');
        self::assertSame('A', Naf::section(CodeApe::fromString('01.11Y'), NomenclatureNaf::NAF2025));
        self::assertSame('V', Naf::section(CodeApe::fromString('99.00Y'), NomenclatureNaf::NAF2025));

        $letters = [];
        foreach (Naf::subclasses(NomenclatureNaf::NAF2025) as $code) {
            $letters[Naf::section($code, NomenclatureNaf::NAF2025)] = true;
        }
        self::assertSame(range('A', 'V'), array_keys($letters));
    }

    /**
     * @return iterable<string, array{string, NomenclatureNaf, string}>
     */
    public static function unknownCodes(): iterable
    {
        yield 'inexistant' => ['00.00A', NomenclatureNaf::NAF2025, 'Le code APE 00.00A n\'existe pas dans la NAF 2025.'];
        yield 'code NAF rév. 2 cherché en NAF 2025' => [
            '62.01Z',
            NomenclatureNaf::NAF2025,
            'Le code APE 62.01Z n\'existe pas dans la NAF 2025 (c\'est un code de la NAF rév. 2).',
        ];
        yield 'code NAF 2025 cherché en NAF rév. 2' => [
            '62.10Y',
            NomenclatureNaf::REV2,
            'Le code APE 62.10Y n\'existe pas dans la NAF rév. 2 (c\'est un code de la NAF 2025).',
        ];
    }

    #[DataProvider('unknownCodes')]
    public function testLabelOfAnUnknownCodeIsRefused(string $code, NomenclatureNaf $nomenclature, string $message): void
    {
        $this->expectException(IdentifiantInvalide::class);
        $this->expectExceptionMessage($message);

        Naf::intitule(CodeApe::fromString($code), $nomenclature);
    }

    #[DataProvider('unknownCodes')]
    public function testSectionOfAnUnknownCodeIsRefused(string $code, NomenclatureNaf $nomenclature, string $message): void
    {
        $this->expectException(IdentifiantInvalide::class);
        $this->expectExceptionMessage($message);

        Naf::section(CodeApe::fromString($code), $nomenclature);
    }
}
