<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PolygonLab\IdentifiantsEntreprise\IdentifiantInvalide;
use PolygonLab\IdentifiantsEntreprise\Siren;

/**
 * Numéros calculés pour respecter la clé de Luhn, sans entreprise attribuée.
 */
final class SirenTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validInputs(): iterable
    {
        yield 'compact' => ['111111118', '111111118'];
        yield 'avec espaces' => ['111 111 118', '111111118'];
        yield 'avec points' => ['111.111.118', '111111118'];
        yield 'avec tirets' => ['111-111-118', '111111118'];
        yield 'espaces autour' => ['  222222226 ', '222222226'];
        yield 'espace insécable' => ["555\u{00A0}555\u{202F}556", '555555556'];
        yield 'tabulation' => ["000\t000\n000", '000000000'];
        yield 'La Poste' => ['356000000', '356000000'];
    }

    #[DataProvider('validInputs')]
    public function testValidSirenIsNormalized(string $input, string $expected): void
    {
        $siren = Siren::fromString($input);

        self::assertSame($expected, $siren->toString());
        self::assertTrue(Siren::isValid($input));
        self::assertNotNull(Siren::tryFromString($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'vide' => ['', 'Le SIREN est vide.'];
        yield 'séparateurs seuls' => [' . - ', 'Le SIREN est vide.'];
        yield '8 chiffres' => ['11111111', 'Le SIREN doit comporter 9 chiffres, 8 reçus.'];
        yield '10 chiffres' => ['1111111180', 'Le SIREN doit comporter 9 chiffres, 10 reçus.'];
        yield '1 chiffre' => ['1', 'Le SIREN doit comporter 9 chiffres, 1 reçu.'];
        yield 'lettre' => ['11111111A', 'Le SIREN ne doit contenir que des chiffres.'];
        yield 'signe' => ['+11111118', 'Le SIREN ne doit contenir que des chiffres.'];
        yield 'chiffre arabe' => ['١١١١١١١١٨', 'Le SIREN ne doit contenir que des chiffres.'];
        yield 'UTF-8 invalide' => ["11111111\xFF", 'Le SIREN ne doit contenir que des chiffres.'];
        yield 'clé fausse' => ['111111117', 'La clé de contrôle du SIREN 111111117 est fausse (algorithme de Luhn).'];
        yield 'clé fausse, dernier chiffre +1' => ['111111119', 'La clé de contrôle du SIREN 111111119 est fausse (algorithme de Luhn).'];
        yield 'chiffres inversés' => ['111111181', 'La clé de contrôle du SIREN 111111181 est fausse (algorithme de Luhn).'];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidSirenIsRejectedWithAClearMessage(string $input, string $message): void
    {
        self::assertFalse(Siren::isValid($input));
        self::assertNull(Siren::tryFromString($input));

        $this->expectException(IdentifiantInvalide::class);
        $this->expectExceptionMessage($message);

        Siren::fromString($input);
    }

    public function testEveryWrongCheckDigitIsRejected(): void
    {
        for ($digit = 0; $digit <= 9; ++$digit) {
            self::assertSame($digit === 8, Siren::isValid('11111111' . $digit), 'clé ' . $digit);
        }
    }

    public function testExceptionIsAnInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Siren::fromString('abc');
    }

    public function testFormat(): void
    {
        self::assertSame('222 222 226', Siren::fromString('222222226')->format());
    }

    public function testEquals(): void
    {
        self::assertTrue(Siren::fromString('111 111 118')->equals(Siren::fromString('111111118')));
        self::assertFalse(Siren::fromString('111111118')->equals(Siren::fromString('222222226')));
    }
}
