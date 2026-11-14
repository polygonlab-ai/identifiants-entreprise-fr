<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise;

/**
 * Nomenclature d'activités française (NAF) à laquelle appartient un code APE.
 *
 * - NAF rév. 2 : en vigueur du 1er janvier 2008 au 31 décembre 2026.
 * - NAF 2025 (décret n° 2025-736 du 31 juillet 2025) : en vigueur à partir du 1er janvier 2027.
 *
 * Les dates de validité sont des jours civils, bornes incluses.
 */
enum NomenclatureNaf: string
{
    case REV2 = 'rev2';
    case NAF2025 = 'naf2025';

    private const TIMEZONE = 'Europe/Paris';

    /**
     * Nom usuel : « NAF rév. 2 » ou « NAF 2025 ».
     */
    public function label(): string
    {
        return match ($this) {
            self::REV2 => 'NAF rév. 2',
            self::NAF2025 => 'NAF 2025',
        };
    }

    /**
     * Premier jour de validité (à minuit, heure de Paris).
     */
    public function validFrom(): \DateTimeImmutable
    {
        return self::day(match ($this) {
            self::REV2 => '2008-01-01',
            self::NAF2025 => '2027-01-01',
        });
    }

    /**
     * Dernier jour de validité, inclus (à minuit, heure de Paris), ou null si aucune fin n'est fixée.
     */
    public function validUntil(): ?\DateTimeImmutable
    {
        return match ($this) {
            self::REV2 => self::day('2026-12-31'),
            self::NAF2025 => null,
        };
    }

    /**
     * La nomenclature est-elle en vigueur ce jour-là ?
     *
     * Seul le jour civil de la date compte, lu dans le fuseau horaire de la date elle-même.
     */
    public function isInForceOn(\DateTimeImmutable $date): bool
    {
        $day = $date->format('Y-m-d');
        $until = $this->validUntil();

        return $day >= $this->validFrom()->format('Y-m-d')
            && ($until === null || $day <= $until->format('Y-m-d'));
    }

    /**
     * Nomenclature en vigueur à une date : NAF rév. 2 jusqu'au 31/12/2026, NAF 2025 à partir du 01/01/2027.
     *
     * Retourne null avant le 1er janvier 2008 (NAF rév. 1 et antérieures, non prises en charge).
     */
    public static function enVigueurLe(\DateTimeImmutable $date): ?self
    {
        foreach (self::cases() as $nomenclature) {
            if ($nomenclature->isInForceOn($date)) {
                return $nomenclature;
            }
        }

        return null;
    }

    private static function day(string $date): \DateTimeImmutable
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone(self::TIMEZONE));
        \assert($day instanceof \DateTimeImmutable);

        return $day;
    }
}
