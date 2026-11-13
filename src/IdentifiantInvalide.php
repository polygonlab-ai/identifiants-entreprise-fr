<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise;

/**
 * Levée quand une valeur n'est pas un identifiant valide.
 *
 * Le message, en français, dit précisément ce qui ne va pas : valeur vide, caractère
 * interdit, longueur fausse, format inattendu ou clé de contrôle fausse.
 */
final class IdentifiantInvalide extends \InvalidArgumentException
{
    /**
     * @param string $libelle sujet de la phrase, par exemple « Le SIREN »
     */
    public static function vide(string $libelle): self
    {
        return new self(sprintf('%s est vide.', $libelle));
    }

    /**
     * @param string $libelle sujet de la phrase, par exemple « Le SIREN »
     * @param string $attendu ce que la valeur doit contenir, par exemple « des chiffres »
     */
    public static function caracteres(string $libelle, string $attendu): self
    {
        return new self(sprintf('%s ne doit contenir que %s.', $libelle, $attendu));
    }

    /**
     * @param string $libelle sujet de la phrase, par exemple « Le SIREN »
     */
    public static function longueur(string $libelle, int $attendu, int $recu, string $unite = 'chiffres'): self
    {
        return new self(sprintf(
            '%s doit comporter %d %s, %d %s.',
            $libelle,
            $attendu,
            $unite,
            $recu,
            $recu > 1 ? 'reçus' : 'reçu',
        ));
    }

    /**
     * @param string $libelle sujet de la phrase, par exemple « Le code APE »
     * @param string $format  format attendu, par exemple « 00.00A »
     */
    public static function format(string $libelle, string $format, string $precision = ''): self
    {
        return new self(sprintf(
            '%s doit respecter le format %s%s.',
            $libelle,
            $format,
            $precision !== '' ? ' (' . $precision . ')' : '',
        ));
    }

    /**
     * @param string $libelle sujet de la phrase, par exemple « du SIREN »
     * @param string $regle   règle appliquée, par exemple « algorithme de Luhn »
     */
    public static function cle(string $libelle, string $valeur, string $regle): self
    {
        return new self(sprintf('La clé de contrôle %s %s est fausse (%s).', $libelle, $valeur, $regle));
    }
}
