<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Internal;

/**
 * Calculs de clé de contrôle sur une suite de chiffres déjà validée.
 *
 * @internal ne fait pas partie de l'interface publique du paquet
 */
final class Checksum
{
    /**
     * Algorithme de Luhn : en partant de la droite, un chiffre sur deux est doublé
     * (9 retranché si le double dépasse 9) ; la somme doit être un multiple de 10.
     *
     * @param string $digits chiffres uniquement
     */
    public static function luhn(string $digits): bool
    {
        $sum = 0;
        $double = false;
        for ($i = strlen($digits) - 1; $i >= 0; --$i) {
            $digit = (int) $digits[$i];
            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $double = !$double;
        }

        return $sum % 10 === 0;
    }

    /**
     * Somme simple des chiffres.
     *
     * @param string $digits chiffres uniquement
     */
    public static function digitSum(string $digits): int
    {
        return array_sum(array_map(intval(...), str_split($digits)));
    }
}
