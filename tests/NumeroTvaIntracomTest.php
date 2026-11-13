<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PolygonLab\IdentifiantsEntreprise\IdentifiantInvalide;
use PolygonLab\IdentifiantsEntreprise\NumeroTvaIntracom;
use PolygonLab\IdentifiantsEntreprise\Siren;

final class NumeroTvaIntracomTest extends TestCase
{
    /**
     * Clés calculées : (12 + 3 × (SIREN mod 97)) mod 97.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function sirenAndKey(): iterable
    {
        yield '111111118' => ['111111118', '44'];
        yield '222222226' => ['222222226', '46'];
        yield '555555556' => ['555555556', '70'];
        yield '000000000 (clé sur 2 chiffres)' => ['000000000', '12'];
        yield 'La Poste' => ['356000000', '39'];
    }

    #[DataProvider('sirenAndKey')]
    public function testKeyComputedFromSirenThenReadBack(string $siren, string $key): void
    {
        $vat = NumeroTvaIntracom::fromSiren($siren);

        self::assertSame($key, $vat->cle());
        self::assertSame('FR' . $key . $siren, $vat->toString());
        self::assertFalse($vat->estCleAncienSysteme());

        $readBack = NumeroTvaIntracom::fromString($vat->format());
        self::assertTrue($readBack->equals($vat));
        self::assertSame($siren, $readBack->siren()->toString());
    }

    public function testFromSirenObject(): void
    {
        $vat = NumeroTvaIntracom::fromSiren(Siren::fromString('111 111 118'));

        self::assertSame('FR 44 111111118', $vat->format());
    }

    public function testFromSirenRejectsAnInvalidSiren(): void
    {
        $this->expectException(IdentifiantInvalide::class);
        $this->expectExceptionMessage('La clé de contrôle du SIREN 111111111 est fausse (algorithme de Luhn).');

        NumeroTvaIntracom::fromSiren('111111111');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validInputs(): iterable
    {
        yield 'compact' => ['FR44111111118', 'FR44111111118'];
        yield 'formaté' => ['FR 44 111111118', 'FR44111111118'];
        yield 'minuscules et points' => ['fr.46.222.222.226', 'FR46222222226'];
        yield 'tirets' => ['FR-70-555-555-556', 'FR70555555556'];
    }

    #[DataProvider('validInputs')]
    public function testValidNumberIsNormalized(string $input, string $expected): void
    {
        self::assertSame($expected, NumeroTvaIntracom::fromString($input)->toString());
        self::assertTrue(NumeroTvaIntracom::isValid($input));
        self::assertNotNull(NumeroTvaIntracom::tryFromString($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function legacyKeys(): iterable
    {
        yield 'lettre puis chiffre' => ['FRA7111111118'];
        yield 'chiffre puis lettre' => ['FR7A111111118'];
        yield 'deux lettres' => ['FRZZ222222226'];
    }

    #[DataProvider('legacyKeys')]
    public function testAlphanumericKeyIsAcceptedAndFlagged(string $input): void
    {
        $vat = NumeroTvaIntracom::fromString($input);

        self::assertTrue($vat->estCleAncienSysteme());
        self::assertSame(substr($input, 2, 2), $vat->cle());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'vide' => ['', 'Le numéro de TVA intracommunautaire est vide.'];
        yield 'autre pays' => ['DE123456789', 'Le numéro de TVA intracommunautaire doit respecter le format FR + clé de 2 caractères + SIREN (il doit commencer par « FR »).'];
        yield 'SIREN seul' => ['111111118', 'Le numéro de TVA intracommunautaire doit respecter le format FR + clé de 2 caractères + SIREN (il doit commencer par « FR »).'];
        yield 'trop court' => ['FR4411111111', 'Le numéro de TVA intracommunautaire doit comporter 13 caractères, 12 reçus.'];
        yield 'trop long' => ['FR441111111180', 'Le numéro de TVA intracommunautaire doit comporter 13 caractères, 14 reçus.'];
        yield 'caractère interdit' => ['FR44_111111118', 'Le numéro de TVA intracommunautaire ne doit contenir que des lettres et des chiffres.'];
        yield 'lettre accentuée' => ['FRÉ4111111118', 'Le numéro de TVA intracommunautaire ne doit contenir que des lettres et des chiffres.'];
        yield 'lettre dans le SIREN' => ['FR4411111111A', 'Le numéro de TVA intracommunautaire doit respecter le format FR + clé de 2 caractères + SIREN (les 9 derniers caractères sont des chiffres).'];
        yield 'clé avec O' => ['FRO4111111118', 'Le numéro de TVA intracommunautaire doit respecter le format FR + clé de 2 caractères + SIREN (la clé ne contient ni I ni O).'];
        yield 'clé avec I' => ['FR4I111111118', 'Le numéro de TVA intracommunautaire doit respecter le format FR + clé de 2 caractères + SIREN (la clé ne contient ni I ni O).'];
        yield 'clé numérique fausse d\'un chiffre' => ['FR45111111118', 'La clé de contrôle du numéro de TVA intracommunautaire FR45111111118 est fausse (clé attendue : 44).'];
        yield 'SIREN faux' => ['FR44111111119', 'La clé de contrôle du SIREN 111111119 est fausse (algorithme de Luhn).'];
        yield 'clé alphanumérique et SIREN faux' => ['FRA7111111119', 'La clé de contrôle du SIREN 111111119 est fausse (algorithme de Luhn).'];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidNumberIsRejectedWithAClearMessage(string $input, string $message): void
    {
        self::assertFalse(NumeroTvaIntracom::isValid($input));
        self::assertNull(NumeroTvaIntracom::tryFromString($input));

        $this->expectException(IdentifiantInvalide::class);
        $this->expectExceptionMessage($message);

        NumeroTvaIntracom::fromString($input);
    }

    public function testInvalidUtf8IsRejected(): void
    {
        self::assertFalse(NumeroTvaIntracom::isValid("FR44111111118\xFF"));
    }
}
