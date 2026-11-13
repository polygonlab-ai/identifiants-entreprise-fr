<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PolygonLab\IdentifiantsEntreprise\CodeApe;
use PolygonLab\IdentifiantsEntreprise\IdentifiantInvalide;
use PolygonLab\IdentifiantsEntreprise\NumeroTvaIntracom;
use PolygonLab\IdentifiantsEntreprise\Siren;
use PolygonLab\IdentifiantsEntreprise\Siret;

// SIREN saisi avec des espaces : il est nettoyé puis contrôlé.
$siren = Siren::fromString('111 111 118');
echo $siren->format(), "\n";

// SIRET : SIREN + NIC.
$siret = Siret::fromString('11111111800019');
echo $siret->format(), ' (NIC ', $siret->nic(), ")\n";

// Numéro de TVA intracommunautaire calculé depuis le SIREN.
$tva = NumeroTvaIntracom::fromSiren($siren);
echo $tva->format(), "\n";

// Code APE saisi sans point et en minuscule.
$ape = CodeApe::fromString('6201z');
echo $ape->format(), ' (section ', $ape->section(), ")\n";

// Contrôle sans exception.
echo Siren::isValid('111111117') ? 'valide' : 'invalide', "\n";

// Message d'erreur en français.
try {
    Siren::fromString('11111111');
} catch (IdentifiantInvalide $e) {
    echo $e->getMessage(), "\n";
}
