# Journal des changements

Toutes les évolutions notables de ce dépôt sont décrites ici.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet respecte
le [versionnage sémantique](https://semver.org/lang/fr/).

## [0.2.1] - 2026-11-14 - Description du dépôt et nettoyage

### Modifié

- Description du dépôt et du paquet : elle mentionne désormais les nomenclatures NAF rév. 2 et NAF 2025.

### Retiré

- Copie de sauvegarde du README publiée par erreur avec la v0.2.0. Aucun changement de code.

## [0.2.0] - 2026-11-14 - Nomenclatures NAF rév. 2 et NAF 2025, table de passage officielle

### Ajouté

- Nomenclatures officielles de l'INSEE embarquées : les 732 sous-classes de la NAF rév. 2 et les 747 sous-classes
  de la NAF 2025, avec leurs intitulés (complet, abrégé et court pour la NAF 2025) et les sections de la NAF 2025.
- `NomenclatureNaf` : `REV2` et `NAF2025`, dates de validité (NAF rév. 2 jusqu'au 31/12/2026, NAF 2025 à partir
  du 01/01/2027), `enVigueurLe()` pour la nomenclature en vigueur à une date.
- `Naf` : `existe()`, `intitule()`, `section()` et liste des sous-classes de chaque nomenclature.
- `CorrespondanceNaf` : `versNaf2025()` et `versNafRev2()` d'après la table de passage officielle (édition
  d'octobre 2026), avec le type de passage (`MappingType` : correspondance unique, regroupement, éclatement,
  aucune correspondance déterminée), le « contenu commun » écrit par l'INSEE pour chaque lien, les autres codes
  regroupés, et le signalement des cas où le choix dépend de l'activité réellement exercée.
- `CodeApe::existe()` : existence dans une nomenclature donnée. `fromString()` reste une validation de format.
- `IdentifiantInvalide::absent()` : message pour un code bien formé absent de la nomenclature demandée, qui
  signale quand il appartient à l'autre nomenclature.
- Générateur `tools/generer-donnees-naf.php` (outil de développement) : lit les fichiers de l'INSEE, contrôle leur
  structure et régénère les données du dossier `resources/naf/`. Un test vérifie que les données sont exactement
  celles qu'il produit.

### Modifié

- README : partie « NAF 2025 et bascule du 1er janvier 2027 », limites mises à jour.
- Aucune dépendance d'exécution ajoutée ; `phpoffice/phpspreadsheet` en dépendance de développement seulement.
  PHPStan 2.3.5. Mots-clés `naf-2025` et `code-ape`.

## [0.1.1] - 2026-11-13 - Mots-clés de recherche

### Modifié

- Mots-clés du paquet et sujets du dépôt complétés (`ape`, `sirene`, `insee`, `numero-tva`,
  `entreprise`, `vat`) pour faciliter la recherche sur Packagist et GitHub. Aucun changement de code.

## [0.1.0] - 2026-11-13 - Validation SIREN, SIRET, TVA intracommunautaire et code APE

### Ajouté

- `Siren` : contrôle de la longueur et de la clé de Luhn, mise en forme `123 456 789`.
- `Siret` : contrôle de la clé de Luhn et de la règle de La Poste, accès au SIREN et au NIC, mise en forme.
- `NumeroTvaIntracom` : contrôle de la clé, calcul depuis un SIREN, clés alphanumériques de l'ancien système signalées.
- `CodeApe` : contrôle du format NAF rév. 2, normalisation, division et section.
- Fabriques `fromString()`, `tryFromString()` et `isValid()` sur les quatre classes.
- Exception `IdentifiantInvalide` avec un message en français.
- Saisie tolérante : espaces, points et tirets ignorés, minuscules acceptées.

[0.2.1]: https://github.com/polygonlab-ai/identifiants-entreprise-fr/releases/tag/v0.2.1
[0.2.0]: https://github.com/polygonlab-ai/identifiants-entreprise-fr/releases/tag/v0.2.0
[0.1.1]: https://github.com/polygonlab-ai/identifiants-entreprise-fr/releases/tag/v0.1.1
[0.1.0]: https://github.com/polygonlab-ai/identifiants-entreprise-fr/releases/tag/v0.1.0

---
— Conçu et développé par PolygonLab · polygonlab.ai · Licence MIT
