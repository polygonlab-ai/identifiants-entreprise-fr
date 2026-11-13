```
       ▄▄▀▀▄▄        ____       _                         _          _
    ▄▀▀  ▄▄  ▀▀▄    |  _ \ ___ | |_   _  __ _  ___  _ __ | |    __ _| |__
    █  ▄▀  ▀▄  █    | |_) / _ \| | | | |/ _` |/ _ \| '_ \| |   / _` | '_ \
    █  ▀▄  ▄▀  █    |  __/ (_) | | |_| | (_| | (_) | | | | |__| (_| | |_) |
    ▀▄▄  ▀▀  ▄▄▀    |_|   \___/|_|\__, |\__, |\___/|_| |_|_____\__,_|_.__/
       ▀▀▄▄▀▀                     |___/ |___/
                                   polygonlab.ai
```

# identifiants-entreprise-fr

> Validation et formatage des SIREN, SIRET, numéros de TVA intracommunautaire et codes APE français, pour les applications PHP.

[![CI](https://github.com/polygonlab-ai/identifiants-entreprise-fr/actions/workflows/ci.yml/badge.svg)](https://github.com/polygonlab-ai/identifiants-entreprise-fr/actions/workflows/ci.yml)
[![Version](https://img.shields.io/packagist/v/polygonlab/identifiants-entreprise-fr)](https://packagist.org/packages/polygonlab/identifiants-entreprise-fr)
[![Licence](https://img.shields.io/badge/licence-MIT-blue)](LICENSE)
[![PHP](https://img.shields.io/packagist/dependency-v/polygonlab/identifiants-entreprise-fr/php)](composer.json)

## Pourquoi

Un formulaire d'inscription, une facture ou un import de fichier reçoit des identifiants d'entreprise
saisis à la main : espaces, points, minuscules, chiffres inversés. Cette bibliothèque les nettoie,
vérifie leur clé de contrôle et les met en forme, sans aucune dépendance. Chaque identifiant devient un
objet immuable : une fois créé, il est forcément valide. Les erreurs sont expliquées en français
(« Le SIREN doit comporter 9 chiffres, 8 reçus. »), prêtes à afficher à l'utilisateur.

## Fonctionnalités

- SIREN : 9 chiffres, clé de Luhn, mise en forme `123 456 789`.
- SIRET : 14 chiffres, clé de Luhn, règle particulière de La Poste, accès au SIREN et au NIC.
- Numéro de TVA intracommunautaire : contrôle de la clé, calcul depuis un SIREN, clés de l'ancien système signalées.
- Code APE (NAF rév. 2) : contrôle du format, normalisation, division et section.
- Saisie tolérante : espaces, points et tirets ignorés, minuscules acceptées.
- Trois façons de valider : `fromString()` (exception), `tryFromString()` (`null`), `isValid()` (booléen).
- PHP 8.2 ou plus, aucune dépendance d'exécution.

## Installation

```bash
composer require polygonlab/identifiants-entreprise-fr
```

## Démarrage rapide

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

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
```

Sortie attendue (ce script est aussi dans [`examples/demarrage-rapide.php`](examples/demarrage-rapide.php), et un test vérifie qu'il produit exactement cette sortie) :

```text
111 111 118
111 111 118 00019 (NIC 00019)
FR 44 111111118
62.01Z (section J)
invalide
Le SIREN doit comporter 9 chiffres, 8 reçus.
```

## Utilisation

### Les trois façons de valider

Les quatre classes offrent les mêmes fabriques :

| Méthode | Valeur invalide | Usage |
|---|---|---|
| `fromString(string)` | lève `IdentifiantInvalide` | Traitement qui doit s'arrêter sur une erreur |
| `tryFromString(string)` | renvoie `null` | Import de fichier, ligne ignorée ou signalée |
| `isValid(string)` (statique) | renvoie `false` | Contrôle rapide d'un formulaire |

`IdentifiantInvalide` étend `InvalidArgumentException` : son message, en français, dit ce qui ne va pas.

```php
use PolygonLab\IdentifiantsEntreprise\IdentifiantInvalide;
use PolygonLab\IdentifiantsEntreprise\Siret;

try {
    $siret = Siret::fromString($_POST['siret'] ?? '');
} catch (IdentifiantInvalide $e) {
    echo $e->getMessage(); // Le SIRET doit comporter 14 chiffres, 13 reçus.
}

$siret = Siret::tryFromString('111 111 118 00019'); // Siret, ou null si invalide
Siret::isValid('11111111800018');                     // false : clé fausse
```

### Siren

```php
use PolygonLab\IdentifiantsEntreprise\Siren;

$siren = Siren::fromString('222.222.226');
$siren->toString(); // '222222226'
$siren->format();   // '222 222 226'
$siren->equals(Siren::fromString('222222226')); // true
```

### Siret

```php
use PolygonLab\IdentifiantsEntreprise\Siret;

$siret = Siret::fromString('222 222 226 00029');
$siret->siren()->toString(); // '222222226' (objet Siren)
$siret->nic();               // '00029'
$siret->format();            // '222 222 226 00029'

// La Poste (SIREN 356000000) : la somme des chiffres est un multiple de 5.
Siret::isValid('35600000000001'); // true, bien que la clé de Luhn soit fausse
```

### NumeroTvaIntracom

```php
use PolygonLab\IdentifiantsEntreprise\NumeroTvaIntracom;

$tva = NumeroTvaIntracom::fromSiren('555555556');
$tva->format();     // 'FR 70 555555556'
$tva->toString();   // 'FR70555555556'
$tva->cle();        // '70'
$tva->siren();      // objet Siren

$relu = NumeroTvaIntracom::fromString('fr 70 555 555 556');
$relu->equals($tva); // true

// Clé alphanumérique de l'ancien système : acceptée si le SIREN est valide, et signalée.
NumeroTvaIntracom::fromString('FRA7111111118')->estCleAncienSysteme(); // true
```

### CodeApe

```php
use PolygonLab\IdentifiantsEntreprise\CodeApe;

$ape = CodeApe::fromString('4711f');
$ape->format();   // '47.11F'
$ape->division(); // '47'
$ape->section();  // 'G' (null si la division n'appartient à aucune section)
```

### Règles appliquées

| Identifiant | Saisie acceptée | Règle de contrôle | Forme `format()` |
|---|---|---|---|
| SIREN | 9 chiffres ; espaces, points et tirets ignorés | Clé de Luhn | `123 456 789` |
| SIRET | 14 chiffres ; espaces, points et tirets ignorés | Clé de Luhn. La Poste (SIREN `356000000`) : somme des chiffres multiple de 5, sauf le siège (NIC `00048`), contrôlé par la clé de Luhn | `123 456 789 00012` |
| TVA intracommunautaire | `FR` + clé de 2 caractères + SIREN ; minuscules, espaces, points et tirets acceptés | SIREN valide. Clé numérique : `(12 + 3 × (SIREN mod 97)) mod 97`, sur 2 chiffres. Clé alphanumérique (ancien système, chiffres et lettres hors I et O, au moins une lettre) : acceptée sans recalcul, signalée par `estCleAncienSysteme()` | `FR 12 345678901` |
| Code APE | `00.00A`, ou sans point, ou en minuscules | 4 chiffres puis une lettre de A à Z hors I et O. Format seulement | `62.01Z` |

### Limites

- **Aucune vérification d'existence** : un numéro valide peut ne correspondre à aucune entreprise, ou à une entreprise
  fermée. La bibliothèque ne contacte ni l'INSEE (répertoire SIRENE) ni aucun autre service. Pour savoir si une
  entreprise existe, interrogez l'API SIRENE de l'INSEE.
- **Code APE : format seulement**. Cette version n'embarque pas la table officielle des codes NAF : `00.00A` est bien
  formé mais n'existe pas. La section est déduite de la division ; une division hors nomenclature donne `null`.
- **NAF rév. 2** uniquement : la nomenclature NAF 2025 n'est pas encore prise en charge.
- **Numéros de TVA français** uniquement (`FR`) : les autres pays de l'Union européenne ne sont pas contrôlés.

## Compatibilité

PHP 8.2, 8.3 et 8.4, testés par l'intégration continue. Aucune extension PHP requise en dehors de `ctype` et `pcre`, présentes par défaut.

## Tests

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan, niveau maximal
composer style     # PHP-CS-Fixer, PER Coding Style
```

## Contribuer

Voir [CONTRIBUTING.md](https://github.com/polygonlab-ai/.github/blob/main/CONTRIBUTING.md).

## Sécurité

Une faille se signale en privé, jamais dans un ticket public : voir
[SECURITY.md](https://github.com/polygonlab-ai/.github/blob/main/SECURITY.md).

## Licence

Licence MIT : voir [LICENSE](LICENSE).

---
— Conçu et développé par PolygonLab · polygonlab.ai · Licence MIT
