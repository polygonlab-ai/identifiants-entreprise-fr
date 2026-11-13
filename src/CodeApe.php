<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise;

use PolygonLab\IdentifiantsEntreprise\Internal\Normalizer;

/**
 * Code APE (activité principale exercée), selon la nomenclature NAF rév. 2.
 *
 * Format : 2 chiffres, un point, 2 chiffres, une lettre de A à Z hors I et O (« 62.01Z »).
 * Saisie tolérée sans point, avec espaces ou en minuscules : elle est normalisée.
 * La validation porte sur le format seulement : le paquet n'embarque pas la table
 * officielle des codes NAF, un code bien formé peut donc ne pas exister.
 */
final class CodeApe
{
    private const LABEL = 'Le code APE';

    private const FORMAT = '00.00A';

    /**
     * Sections de la NAF rév. 2 : lettre => [première division, dernière division].
     */
    private const SECTIONS = [
        'A' => [1, 3],
        'B' => [5, 9],
        'C' => [10, 33],
        'D' => [35, 35],
        'E' => [36, 39],
        'F' => [41, 43],
        'G' => [45, 47],
        'H' => [49, 53],
        'I' => [55, 56],
        'J' => [58, 63],
        'K' => [64, 66],
        'L' => [68, 68],
        'M' => [69, 75],
        'N' => [77, 82],
        'O' => [84, 84],
        'P' => [85, 85],
        'Q' => [86, 88],
        'R' => [90, 93],
        'S' => [94, 96],
        'T' => [97, 98],
        'U' => [99, 99],
    ];

    /**
     * @param string $value 4 chiffres et une lettre, sans point : « 6201Z »
     */
    private function __construct(
        private readonly string $value,
    ) {}

    /**
     * @throws IdentifiantInvalide si la valeur n'a pas le format d'un code APE
     */
    public static function fromString(string $value): self
    {
        $compact = Normalizer::compactUpper($value);
        if ($compact === null || ($compact !== '' && preg_match('/\A[0-9A-Z]+\z/', $compact) !== 1)) {
            throw IdentifiantInvalide::caracteres(self::LABEL, 'des chiffres et une lettre');
        }
        if ($compact === '') {
            throw IdentifiantInvalide::vide(self::LABEL);
        }
        if (strlen($compact) !== 5) {
            throw IdentifiantInvalide::longueur(self::LABEL, 5, strlen($compact), 'caractères hors point');
        }
        if (preg_match('/\A[0-9]{4}[A-Z]\z/', $compact) !== 1) {
            throw IdentifiantInvalide::format(self::LABEL, self::FORMAT, '4 chiffres puis une lettre');
        }
        if ($compact[4] === 'I' || $compact[4] === 'O') {
            throw IdentifiantInvalide::format(self::LABEL, self::FORMAT, 'la lettre finale ne peut être ni I ni O');
        }

        return new self($compact);
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
     * Division : les 2 premiers chiffres (« 62 »).
     */
    public function division(): string
    {
        return substr($this->value, 0, 2);
    }

    /**
     * Section de la NAF rév. 2 (lettre de A à U) déduite de la division, ou null si
     * la division n'appartient à aucune section (« 00 », « 04 », « 34 »...).
     */
    public function section(): ?string
    {
        $division = (int) $this->division();
        foreach (self::SECTIONS as $section => [$first, $last]) {
            if ($division >= $first && $division <= $last) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Forme normalisée : « 62.01Z ».
     */
    public function format(): string
    {
        return substr($this->value, 0, 2) . '.' . substr($this->value, 2);
    }

    /**
     * Identique à format() : « 62.01Z ».
     */
    public function toString(): string
    {
        return $this->format();
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
