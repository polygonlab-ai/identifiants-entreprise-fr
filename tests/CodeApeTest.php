<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PolygonLab\IdentifiantsEntreprise\CodeApe;
use PolygonLab\IdentifiantsEntreprise\IdentifiantInvalide;

final class CodeApeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validInputs(): iterable
    {
        yield 'format officiel' => ['62.01Z', '62.01Z'];
        yield 'sans point' => ['6201Z', '62.01Z'];
        yield 'minuscule' => ['62.01z', '62.01Z'];
        yield 'espaces' => [' 62 01 z ', '62.01Z'];
        yield 'tiret' => ['47-11F', '47.11F'];
        yield 'lettre A' => ['01.11A', '01.11A'];
    }

    #[DataProvider('validInputs')]
    public function testValidCodeIsNormalized(string $input, string $expected): void
    {
        $code = CodeApe::fromString($input);

        self::assertSame($expected, $code->format());
        self::assertSame($expected, $code->toString());
        self::assertTrue(CodeApe::isValid($input));
        self::assertNotNull(CodeApe::tryFromString($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'vide' => ['', 'Le code APE est vide.'];
        yield 'trop court' => ['62.0Z', 'Le code APE doit comporter 5 caractères hors point, 4 reçus.'];
        yield 'trop long' => ['62.011Z', 'Le code APE doit comporter 5 caractères hors point, 6 reçus.'];
        yield 'sans lettre' => ['62.012', 'Le code APE doit respecter le format 00.00A (4 chiffres puis une lettre).'];
        yield 'lettre au début' => ['Z6201', 'Le code APE doit respecter le format 00.00A (4 chiffres puis une lettre).'];
        yield 'lettre I' => ['62.01I', 'Le code APE doit respecter le format 00.00A (la lettre finale ne peut être ni I ni O).'];
        yield 'lettre O' => ['62.01o', 'Le code APE doit respecter le format 00.00A (la lettre finale ne peut être ni I ni O).'];
        yield 'caractère interdit' => ['62/01Z', 'Le code APE ne doit contenir que des chiffres et une lettre.'];
        yield 'UTF-8 invalide' => ["6201Z\xFF", 'Le code APE ne doit contenir que des chiffres et une lettre.'];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidCodeIsRejectedWithAClearMessage(string $input, string $message): void
    {
        self::assertFalse(CodeApe::isValid($input));
        self::assertNull(CodeApe::tryFromString($input));

        $this->expectException(IdentifiantInvalide::class);
        $this->expectExceptionMessage($message);

        CodeApe::fromString($input);
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function divisionsAndSections(): iterable
    {
        yield 'agriculture' => ['01.11A', '01', 'A'];
        yield 'fin de A' => ['03.22Z', '03', 'A'];
        yield 'industrie extractive' => ['05.10Z', '05', 'B'];
        yield 'industrie manufacturière' => ['10.11Z', '10', 'C'];
        yield 'électricité' => ['35.11Z', '35', 'D'];
        yield 'commerce' => ['47.11F', '47', 'G'];
        yield 'information et communication' => ['62.01Z', '62', 'J'];
        yield 'immobilier' => ['68.20A', '68', 'L'];
        yield 'administration publique' => ['84.11Z', '84', 'O'];
        yield 'enseignement' => ['85.59A', '85', 'P'];
        yield 'extraterritorial' => ['99.00Z', '99', 'U'];
        yield 'division 00 hors nomenclature' => ['00.00A', '00', null];
        yield 'division 04 hors nomenclature' => ['04.00Z', '04', null];
        yield 'division 34 hors nomenclature' => ['34.00Z', '34', null];
    }

    #[DataProvider('divisionsAndSections')]
    public function testDivisionAndSection(string $input, string $division, ?string $section): void
    {
        $code = CodeApe::fromString($input);

        self::assertSame($division, $code->division());
        self::assertSame($section, $code->section());
    }

    public function testEquals(): void
    {
        self::assertTrue(CodeApe::fromString('6201z')->equals(CodeApe::fromString('62.01Z')));
        self::assertFalse(CodeApe::fromString('62.01Z')->equals(CodeApe::fromString('62.02A')));
    }
}
