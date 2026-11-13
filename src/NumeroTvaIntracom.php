<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise;

use PolygonLab\IdentifiantsEntreprise\Internal\Normalizer;

/**
 * Numéro de TVA intracommunautaire français : « FR », une clé de 2 caractères, puis le SIREN.
 *
 * Clé numérique : (12 + 3 × (SIREN mod 97)) mod 97, écrite sur 2 chiffres. Clé
 * alphanumérique (ancien système d'attribution) : 2 caractères parmi les chiffres et les
 * lettres de A à Z hors I et O, dont au moins une lettre ; elle ne se recalcule pas à partir
 * du SIREN, le numéro est donc accepté si le SIREN est valide, et signalé par
 * estCleAncienSysteme().
 */
final class NumeroTvaIntracom
{
    public const COUNTRY_CODE = 'FR';

    public const LENGTH = 13;

    private const LABEL = 'Le numéro de TVA intracommunautaire';

    private function __construct(
        private readonly string $key,
        private readonly Siren $siren,
    ) {}

    /**
     * @throws IdentifiantInvalide si la valeur n'est pas un numéro de TVA français valide
     */
    public static function fromString(string $value): self
    {
        $compact = Normalizer::compactUpper($value);
        if ($compact === null || ($compact !== '' && preg_match('/\A[0-9A-Z]+\z/', $compact) !== 1)) {
            throw IdentifiantInvalide::caracteres(self::LABEL, 'des lettres et des chiffres');
        }
        if ($compact === '') {
            throw IdentifiantInvalide::vide(self::LABEL);
        }
        if (!str_starts_with($compact, self::COUNTRY_CODE)) {
            throw IdentifiantInvalide::format(self::LABEL, 'FR + clé de 2 caractères + SIREN', 'il doit commencer par « FR »');
        }
        if (strlen($compact) !== self::LENGTH) {
            throw IdentifiantInvalide::longueur(self::LABEL, self::LENGTH, strlen($compact), 'caractères');
        }

        $key = substr($compact, 2, 2);
        $sirenDigits = substr($compact, 4);
        if (!ctype_digit($sirenDigits)) {
            throw IdentifiantInvalide::format(self::LABEL, 'FR + clé de 2 caractères + SIREN', 'les 9 derniers caractères sont des chiffres');
        }
        if (preg_match('/\A[0-9A-HJ-NP-Z]{2}\z/', $key) !== 1) {
            throw IdentifiantInvalide::format(self::LABEL, 'FR + clé de 2 caractères + SIREN', 'la clé ne contient ni I ni O');
        }

        $siren = Siren::fromString($sirenDigits);
        if (ctype_digit($key) && $key !== self::computeKey($siren)) {
            throw IdentifiantInvalide::cle('du numéro de TVA intracommunautaire', $compact, 'clé attendue : ' . self::computeKey($siren));
        }

        return new self($key, $siren);
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
     * Calcule le numéro de TVA (clé numérique) d'un SIREN.
     *
     * @throws IdentifiantInvalide si la chaîne reçue n'est pas un SIREN valide
     */
    public static function fromSiren(Siren|string $siren): self
    {
        $siren = $siren instanceof Siren ? $siren : Siren::fromString($siren);

        return new self(self::computeKey($siren), $siren);
    }

    public function siren(): Siren
    {
        return $this->siren;
    }

    /**
     * Clé de 2 caractères : « 12 », ou « A7 » pour une clé de l'ancien système.
     */
    public function cle(): string
    {
        return $this->key;
    }

    /**
     * Vrai si la clé est alphanumérique (ancien système d'attribution, non recalculable).
     */
    public function estCleAncienSysteme(): bool
    {
        return !ctype_digit($this->key);
    }

    /**
     * Forme compacte : « FR12345678901 ».
     */
    public function toString(): string
    {
        return self::COUNTRY_CODE . $this->key . $this->siren->toString();
    }

    /**
     * Forme lisible : « FR 12 345678901 ».
     */
    public function format(): string
    {
        return self::COUNTRY_CODE . ' ' . $this->key . ' ' . $this->siren->toString();
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    private static function computeKey(Siren $siren): string
    {
        return sprintf('%02d', (12 + 3 * ((int) $siren->toString() % 97)) % 97);
    }
}
