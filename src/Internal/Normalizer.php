<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Internal;

/**
 * Nettoyage de la saisie avant la validation.
 *
 * @internal ne fait pas partie de l'interface publique du paquet
 */
final class Normalizer
{
    /**
     * Retire les espaces (y compris insécables), les points et les tirets.
     *
     * Retourne null si la chaîne n'est pas de l'UTF-8 valide.
     */
    public static function compact(string $value): ?string
    {
        return preg_replace('/[\s.\-\x{00A0}\x{202F}]+/u', '', $value);
    }

    /**
     * Comme compact(), puis passage en majuscules (lettres ASCII seulement).
     */
    public static function compactUpper(string $value): ?string
    {
        $compact = self::compact($value);

        return $compact === null ? null : strtoupper($compact);
    }
}
