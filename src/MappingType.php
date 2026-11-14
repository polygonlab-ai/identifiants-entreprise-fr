<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise;

/**
 * Type de lien entre un code et ses correspondants dans l'autre nomenclature, d'après la table de passage
 * officielle de l'INSEE, vu depuis le code de départ.
 */
enum MappingType: string
{
    /** Un seul code correspondant, qui ne reçoit que le code de départ : passage automatique. */
    case ONE_TO_ONE = 'unique';

    /** Plusieurs codes correspondants : le bon code dépend de l'activité réellement exercée. */
    case SPLIT = 'eclatement';

    /** Un seul code correspondant, qui regroupe aussi d'autres codes de départ : passage automatique. */
    case MERGE = 'regroupement';

    /** Aucune correspondance déterminée par l'INSEE à ce jour. */
    case NONE = 'aucune';

    /**
     * Libellé en français, prêt à afficher.
     */
    public function label(): string
    {
        return match ($this) {
            self::ONE_TO_ONE => 'correspondance unique',
            self::SPLIT => 'éclatement en plusieurs codes',
            self::MERGE => 'regroupement avec d\'autres codes',
            self::NONE => 'pas de correspondance déterminée',
        };
    }
}
