# Journal des changements

Toutes les évolutions notables de ce dépôt sont décrites ici.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet respecte
le [versionnage sémantique](https://semver.org/lang/fr/).

## [0.1.0] - 2026-11-13 - Validation SIREN, SIRET, TVA intracommunautaire et code APE

### Ajouté

- `Siren` : contrôle de la longueur et de la clé de Luhn, mise en forme `123 456 789`.
- `Siret` : contrôle de la clé de Luhn et de la règle de La Poste, accès au SIREN et au NIC, mise en forme.
- `NumeroTvaIntracom` : contrôle de la clé, calcul depuis un SIREN, clés alphanumériques de l'ancien système signalées.
- `CodeApe` : contrôle du format NAF rév. 2, normalisation, division et section.
- Fabriques `fromString()`, `tryFromString()` et `isValid()` sur les quatre classes.
- Exception `IdentifiantInvalide` avec un message en français.
- Saisie tolérante : espaces, points et tirets ignorés, minuscules acceptées.

[0.1.0]: https://github.com/polygonlab-ai/identifiants-entreprise-fr/releases/tag/v0.1.0

---
— Conçu et développé par PolygonLab · polygonlab.ai · Licence MIT
