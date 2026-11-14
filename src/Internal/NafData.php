<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Internal;

use PolygonLab\IdentifiantsEntreprise\CodeApe;
use PolygonLab\IdentifiantsEntreprise\IdentifiantInvalide;
use PolygonLab\IdentifiantsEntreprise\NomenclatureNaf;

/**
 * Accès aux données NAF générées depuis les fichiers officiels de l'INSEE (dossier resources/naf/).
 *
 * Chaque fichier n'est lu qu'une fois par processus.
 *
 * @internal ne fait pas partie de l'interface publique du paquet
 *
 * @phpstan-type Nomenclature array{
 *     sous_classes: array<string, array{0: string, 1: string|null, 2: string|null}>,
 *     sections: array<array-key, string>
 * }
 * @phpstan-type Passage array{
 *     liens: array<string, list<array{0: string, 1: string}>>,
 *     sans_correspondance: array<string, string>,
 *     sans_origine: array<string, string>
 * }
 */
final class NafData
{
    private const LABEL = 'Le code APE';

    public const DIRECTORY = __DIR__ . '/../../resources/naf';

    /**
     * @var array<string, Nomenclature>
     */
    private static array $nomenclatures = [];

    /**
     * @var array<string, Passage>
     */
    private static array $passages = [];

    /**
     * Nom du fichier de données d'une nomenclature.
     */
    public static function nomenclatureFile(NomenclatureNaf $nomenclature): string
    {
        return match ($nomenclature) {
            NomenclatureNaf::REV2 => 'naf-rev2.php',
            NomenclatureNaf::NAF2025 => 'naf-2025.php',
        };
    }

    /**
     * Nom du fichier de la table de passage depuis une nomenclature vers l'autre.
     */
    public static function passageFile(NomenclatureNaf $from): string
    {
        return match ($from) {
            NomenclatureNaf::REV2 => 'passage-rev2-vers-naf2025.php',
            NomenclatureNaf::NAF2025 => 'passage-naf2025-vers-rev2.php',
        };
    }

    /**
     * Sous-classes : code « 00.00A » => [intitulé complet, abrégé ou null, court ou null].
     *
     * @return array<string, array{0: string, 1: string|null, 2: string|null}>
     */
    public static function subclasses(NomenclatureNaf $nomenclature): array
    {
        return self::nomenclature($nomenclature)['sous_classes'];
    }

    /**
     * Sections : division « 00 » => lettre de la section (les divisions de « 10 » à « 99 » deviennent des
     * clés entières une fois le fichier relu : la recherche par chaîne fonctionne de la même façon).
     *
     * @return array<array-key, string>
     */
    public static function sections(NomenclatureNaf $nomenclature): array
    {
        return self::nomenclature($nomenclature)['sections'];
    }

    /**
     * Liens de la table de passage : code de départ => liste de [code d'arrivée, contenu commun].
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
    public static function links(NomenclatureNaf $from): array
    {
        return self::passage($from)['liens'];
    }

    /**
     * Codes de départ sans correspondance déterminée : code => texte de l'INSEE.
     *
     * @return array<string, string>
     */
    public static function withoutCounterpart(NomenclatureNaf $from): array
    {
        return self::passage($from)['sans_correspondance'];
    }

    /**
     * Codes d'arrivée qu'aucun code de départ n'atteint : code => texte de l'INSEE.
     *
     * @return array<string, string>
     */
    public static function withoutOrigin(NomenclatureNaf $from): array
    {
        return self::passage($from)['sans_origine'];
    }

    /**
     * Exception pour un code bien formé mais absent de la nomenclature, avec une indication
     * quand il appartient à l'autre nomenclature.
     */
    public static function unknown(CodeApe $code, NomenclatureNaf $nomenclature): IdentifiantInvalide
    {
        $other = $nomenclature === NomenclatureNaf::REV2 ? NomenclatureNaf::NAF2025 : NomenclatureNaf::REV2;

        return IdentifiantInvalide::absent(
            self::LABEL,
            $code->format(),
            $nomenclature->label(),
            isset(self::subclasses($other)[$code->format()]) ? 'c\'est un code de la ' . $other->label() : '',
        );
    }

    /**
     * @return Nomenclature
     */
    private static function nomenclature(NomenclatureNaf $nomenclature): array
    {
        if (!isset(self::$nomenclatures[$nomenclature->value])) {
            /** @var Nomenclature $data */
            $data = require self::DIRECTORY . '/' . self::nomenclatureFile($nomenclature);
            self::$nomenclatures[$nomenclature->value] = $data;
        }

        return self::$nomenclatures[$nomenclature->value];
    }

    /**
     * @return Passage
     */
    private static function passage(NomenclatureNaf $from): array
    {
        if (!isset(self::$passages[$from->value])) {
            /** @var Passage $data */
            $data = require self::DIRECTORY . '/' . self::passageFile($from);
            self::$passages[$from->value] = $data;
        }

        return self::$passages[$from->value];
    }
}
