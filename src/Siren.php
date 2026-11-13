<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise;

use PolygonLab\IdentifiantsEntreprise\Internal\Checksum;
use PolygonLab\IdentifiantsEntreprise\Internal\Normalizer;

/**
 * Numéro SIREN : identifiant à 9 chiffres d'une entreprise, attribué par l'INSEE.
 *
 * Règles : 9 chiffres, clé de contrôle selon l'algorithme de Luhn. Les espaces, points
 * et tirets de la saisie sont ignorés. Objet valeur immuable : une instance est toujours valide.
 */
final class Siren
{
    public const LENGTH = 9;

    private const LABEL = 'Le SIREN';

    private function __construct(
        private readonly string $value,
    ) {}

    /**
     * @throws IdentifiantInvalide si la valeur n'est pas un SIREN valide
     */
    public static function fromString(string $value): self
    {
        $digits = Normalizer::compact($value);
        if ($digits === null || ($digits !== '' && !ctype_digit($digits))) {
            throw IdentifiantInvalide::caracteres(self::LABEL, 'des chiffres');
        }
        if ($digits === '') {
            throw IdentifiantInvalide::vide(self::LABEL);
        }
        if (strlen($digits) !== self::LENGTH) {
            throw IdentifiantInvalide::longueur(self::LABEL, self::LENGTH, strlen($digits));
        }
        if (!Checksum::luhn($digits)) {
            throw IdentifiantInvalide::cle('du SIREN', $digits, 'algorithme de Luhn');
        }

        return new self($digits);
    }

    public static function tryFromString(string $value): ?self
    {
        try {
            return self::fromString($value);
        } catch (IdentifiantInvalide) {
            return null;
        }
    }

    public static function isValid(string $value): bool
    {
        return self::tryFromString($value) !== null;
    }

    /**
     * Les 9 chiffres, sans espace : « 123456782 ».
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Forme lisible, par groupes de 3 : « 123 456 782 ».
     */
    public function format(): string
    {
        return implode(' ', str_split($this->value, 3));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
