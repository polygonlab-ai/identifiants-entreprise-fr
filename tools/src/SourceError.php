<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tools;

/**
 * Un fichier de l'INSEE ne correspond pas à ce que le générateur attend : rien n'est écrit.
 */
final class SourceError extends \RuntimeException {}
