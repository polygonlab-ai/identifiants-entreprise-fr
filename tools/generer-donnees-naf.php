<?php

/**
 * Génère les fichiers de données NAF (dossier resources/naf/) à partir des fichiers officiels de l'INSEE.
 *
 * Usage :
 *   php tools/generer-donnees-naf.php <dossier>              lit, contrôle, puis écrit les fichiers de données
 *   php tools/generer-donnees-naf.php <dossier> --verifier   lit, contrôle et compare, sans rien écrire
 *
 * <dossier> contient les cinq fichiers téléchargés depuis https://www.insee.fr/fr/information/8181066,
 * sous leur nom d'origine (voir README, partie « NAF 2025 et bascule du 1er janvier 2027 »).
 *
 * Codes de sortie : 0 succès (ou données à jour), 1 fichier source refusé ou données différentes, 2 usage.
 * Outil de développement : il utilise phpoffice/phpspreadsheet, dépendance de développement seulement.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PolygonLab\IdentifiantsEntreprise\Internal\NafData;
use PolygonLab\IdentifiantsEntreprise\Tools\DataRenderer;
use PolygonLab\IdentifiantsEntreprise\Tools\SourceError;
use PolygonLab\IdentifiantsEntreprise\Tools\SourceReader;

$arguments = array_slice($argv, 1);
$check = in_array('--verifier', $arguments, true);
$directories = array_values(array_filter($arguments, static fn(string $argument): bool => $argument !== '--verifier'));

if (count($directories) !== 1 || !is_dir($directories[0])) {
    fwrite(STDERR, "Usage : php tools/generer-donnees-naf.php <dossier des fichiers de l'INSEE> [--verifier]\n");
    exit(2);
}

$reader = new SourceReader($directories[0]);
try {
    $files = $reader->build();
} catch (SourceError $error) {
    fwrite(STDERR, 'Arrêt : ' . $error->getMessage() . "\nAucun fichier n'a été écrit.\n");
    exit(1);
}

foreach ($reader->report() as $line) {
    echo $line, "\n";
}

$different = 0;
foreach ($files as $name => $data) {
    $path = NafData::DIRECTORY . '/' . $name;
    $content = DataRenderer::render($data);
    $current = is_file($path) ? file_get_contents($path) : false;

    if ($check) {
        $same = $current === $content;
        $different += $same ? 0 : 1;
        echo $same ? 'À jour : ' : 'Différent : ', 'resources/naf/', $name, "\n";
        continue;
    }

    if ($current !== $content && file_put_contents($path, $content) !== strlen($content)) {
        fwrite(STDERR, 'Écriture impossible : resources/naf/' . $name . "\n");
        exit(1);
    }
    echo $current === $content ? 'Inchangé : ' : 'Écrit : ', 'resources/naf/', $name, ' (', strlen($content), " octets)\n";
}

exit($different === 0 ? 0 : 1);
