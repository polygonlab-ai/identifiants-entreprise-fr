<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise;

use PolygonLab\IdentifiantsEntreprise\Internal\Checksum;
use PolygonLab\IdentifiantsEntreprise\Internal\Normalizer;

/**
 * Numéro SIRET : identifiant à 14 chiffres d'un établissement, formé du SIREN (9 chiffres)
 * et du NIC (5 chiffres).
 *
 * Règles : 14 chiffres, clé de contrôle selon l'algorithme de Luhn. Exception publique de
 * La Poste (SIREN 356000000) : ses établissements ont trop de numéros pour la clé de Luhn,
 * la somme des chiffres du SIRET doit alors être un multiple de 5 ; seul le siège
 * (NIC 00048) suit la règle générale.
 */
final class Siret
{
    public const LENGTH = 14;

    /** SIREN de La Poste, soumis à la règle de la somme des chiffres. */
    public const SIREN_LA_POSTE = '356000000';

    /** NIC du siège de La Poste, contrôlé par l'algorithme de Luhn comme les autres SIRET. */
    private const NIC_SIEGE_LA_POSTE = '00048';

    private const LABEL = 'Le SIRET';

    private function __construct(
        private readonly string $value,
    ) {}

    /**
     * @throws IdentifiantInvalide si la valeur n'est pas un SIRET valide
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
        if (self::isLaPosteEstablishment($digits)) {
            if (Checksum::digitSum($digits) % 5 !== 0) {
                throw IdentifiantInvalide::cle('du SIRET', $digits, 'La Poste : la somme des chiffres doit être un multiple de 5');
            }
        } elseif (!Checksum::luhn($digits)) {
            throw IdentifiantInvalide::cle('du SIRET', $digits, 'algorithme de Luhn');
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
     * SIREN de l'entreprise (9 premiers chiffres).
     *
     * Pour La Poste, le SIREN 356000000 est lui-même valide selon l'algorithme de Luhn.
     */
    public function siren(): Siren
    {
        return Siren::fromString(substr($this->value, 0, Siren::LENGTH));
    }

    /**
     * NIC, numéro interne de classement de l'établissement (5 derniers chiffres).
     */
    public function nic(): string
    {
        return substr($this->value, Siren::LENGTH);
    }

    /**
     * Les 14 chiffres, sans espace : « 12345678200017 ».
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Forme lisible : « 123 456 782 00017 ».
     */
    public function format(): string
    {
        return implode(' ', str_split(substr($this->value, 0, Siren::LENGTH), 3)) . ' ' . $this->nic();
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function isLaPosteEstablishment(string $digits): bool
    {
        return str_starts_with($digits, self::SIREN_LA_POSTE)
            && substr($digits, Siren::LENGTH) !== self::NIC_SIEGE_LA_POSTE;
    }
}
