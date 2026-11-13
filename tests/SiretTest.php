<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PolygonLab\IdentifiantsEntreprise\IdentifiantInvalide;
use PolygonLab\IdentifiantsEntreprise\Siret;

final class SiretTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validInputs(): iterable
    {
        yield 'compact' => ['11111111800019', '11111111800019'];
        yield 'formaté' => ['111 111 118 00019', '11111111800019'];
        yield 'second établissement' => ['222.222.226-00029', '22222222600029'];
        yield 'La Poste, somme multiple de 5 (clé de Luhn fausse)' => ['35600000000001', '35600000000001'];
        yield 'La Poste, somme multiple de 5' => ['356 000 000 00010', '35600000000010'];
        yield 'La Poste, siège (clé de Luhn)' => ['35600000000048', '35600000000048'];
    }

    #[DataProvider('validInputs')]
    public function testValidSiretIsNormalized(string $input, string $expected): void
    {
        self::assertSame($expected, Siret::fromString($input)->toString());
        self::assertTrue(Siret::isValid($input));
        self::assertNotNull(Siret::tryFromString($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'vide' => ['', 'Le SIRET est vide.'];
        yield '13 chiffres' => ['1111111180001', 'Le SIRET doit comporter 14 chiffres, 13 reçus.'];
        yield '15 chiffres' => ['111111118000190', 'Le SIRET doit comporter 14 chiffres, 15 reçus.'];
        yield 'SIREN seul' => ['111111118', 'Le SIRET doit comporter 14 chiffres, 9 reçus.'];
        yield 'lettre' => ['1111111180001O', 'Le SIRET ne doit contenir que des chiffres.'];
        yield 'clé fausse' => ['11111111800018', 'La clé de contrôle du SIRET 11111111800018 est fausse (algorithme de Luhn).'];
        yield 'La Poste, somme non multiple de 5' => ['35600000000002', 'La clé de contrôle du SIRET 35600000000002 est fausse (La Poste : la somme des chiffres doit être un multiple de 5).'];
        yield 'La Poste, siège altéré' => ['35600000000049', 'La clé de contrôle du SIRET 35600000000049 est fausse (La Poste : la somme des chiffres doit être un multiple de 5).'];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidSiretIsRejectedWithAClearMessage(string $input, string $message): void
    {
        self::assertFalse(Siret::isValid($input));
        self::assertNull(Siret::tryFromString($input));

        $this->expectException(IdentifiantInvalide::class);
        $this->expectExceptionMessage($message);

        Siret::fromString($input);
    }

    public function testLuhnRuleStillAppliesOutsideLaPoste(): void
    {
        // Somme des chiffres multiple de 5, mais clé de Luhn fausse : refusé hors La Poste.
        self::assertFalse(Siret::isValid('11111111800005'));
    }

    public function testEveryWrongCheckDigitIsRejected(): void
    {
        for ($digit = 0; $digit <= 9; ++$digit) {
            self::assertSame($digit === 9, Siret::isValid('1111111180001' . $digit), 'clé ' . $digit);
        }
    }

    public function testSirenAndNic(): void
    {
        $siret = Siret::fromString('22222222600029');

        self::assertSame('222222226', $siret->siren()->toString());
        self::assertSame('00029', $siret->nic());
        self::assertSame('356000000', Siret::fromString('35600000000001')->siren()->toString());
    }

    public function testFormat(): void
    {
        self::assertSame('111 111 118 00019', Siret::fromString('11111111800019')->format());
    }

    public function testEquals(): void
    {
        self::assertTrue(Siret::fromString('111 111 118 00019')->equals(Siret::fromString('11111111800019')));
        self::assertFalse(Siret::fromString('22222222600011')->equals(Siret::fromString('22222222600029')));
    }
}
