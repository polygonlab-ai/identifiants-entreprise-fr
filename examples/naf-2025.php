<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PolygonLab\IdentifiantsEntreprise\CodeApe;
use PolygonLab\IdentifiantsEntreprise\CorrespondanceNaf;
use PolygonLab\IdentifiantsEntreprise\Naf;
use PolygonLab\IdentifiantsEntreprise\NomenclatureNaf;

// Nomenclature en vigueur à une date.
echo NomenclatureNaf::enVigueurLe(new DateTimeImmutable('2027-01-01'))?->label(), "\n";

// Passage automatique : un seul code NAF 2025 correspond.
foreach (['62.01Z', '62.02B'] as $ancien) {
    $passage = CorrespondanceNaf::versNaf2025(CodeApe::fromString($ancien));
    $nouveau = $passage->uniqueCode();
    if ($nouveau !== null) {
        $intitule = Naf::intitule($nouveau, NomenclatureNaf::NAF2025);
        echo $ancien, ' -> ', $nouveau->format(), ' ', $intitule, ' (', $passage->type()->label(), ")\n";
    }
}

// Éclatement : le bon code dépend de l'activité réellement exercée, la bibliothèque ne choisit pas.
$passage = CorrespondanceNaf::versNaf2025(CodeApe::fromString('63.11Z'));
if ($passage->dependsOnActivity()) {
    echo '63.11Z : ', $passage->type()->label(), ", choix selon l'activité réellement exercée :\n";
    foreach ($passage->links() as $lien) {
        echo '  ', $lien->code()->format(), ' ', $lien->intitule(), "\n";
    }
}

// Un code de l'ancienne nomenclature n'existe pas dans la nouvelle.
echo CodeApe::fromString('62.01Z')->existe(NomenclatureNaf::NAF2025) ? 'existe' : 'n\'existe pas', " en NAF 2025\n";
