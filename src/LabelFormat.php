<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise;

/**
 * Forme de l'intitulé d'une sous-classe, telle que publiée par l'INSEE.
 */
enum LabelFormat: string
{
    /** Intitulé complet. */
    case FULL = 'complet';

    /** Intitulé abrégé, 65 caractères au plus. */
    case ABBREVIATED = 'abrege';

    /** Intitulé court, 40 caractères au plus. */
    case SHORT = 'court';
}
