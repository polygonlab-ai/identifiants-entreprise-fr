<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise;

use PolygonLab\IdentifiantsEntreprise\Internal\NafData;

/**
 * Tables officielles des sous-classes de la NAF rév. 2 et de la NAF 2025, publiées par l'INSEE.
 *
 * Les données sont embarquées dans le paquet (dossier resources/naf/) : aucun accès réseau.
 */
final class Naf
{
    private function __construct() {}

    /**
     * Le code est-il une sous-classe de cette nomenclature ?
     */
    public static function existe(CodeApe $code, NomenclatureNaf $nomenclature): bool
    {
        return isset(NafData::subclasses($nomenclature)[$code->format()]);
    }

    /**
     * Intitulé officiel de la sous-classe.
     *
     * Retourne null si l'INSEE ne publie pas cette forme d'intitulé pour la nomenclature.
     *
     * @throws IdentifiantInvalide si le code n'existe pas dans la nomenclature
     */
    public static function intitule(
        CodeApe $code,
        NomenclatureNaf $nomenclature,
        LabelFormat $format = LabelFormat::FULL,
    ): ?string {
        $labels = NafData::subclasses($nomenclature)[$code->format()] ?? null;
        if ($labels === null) {
            throw NafData::unknown($code, $nomenclature);
        }

        return match ($format) {
            LabelFormat::FULL => $labels[0],
            LabelFormat::ABBREVIATED => $labels[1],
            LabelFormat::SHORT => $labels[2],
        };
    }

    /**
     * Section de la sous-classe dans cette nomenclature (lettre de A à U en NAF rév. 2, de A à V en NAF 2025).
     *
     * À préférer à CodeApe::section(), qui suit toujours le découpage de la NAF rév. 2.
     *
     * @throws IdentifiantInvalide si le code n'existe pas dans la nomenclature
     */
    public static function section(CodeApe $code, NomenclatureNaf $nomenclature): string
    {
        if (!self::existe($code, $nomenclature)) {
            throw NafData::unknown($code, $nomenclature);
        }

        // Le fichier de la NAF rév. 2 ne donne pas les sections : celles de CodeApe::section() s'appliquent.
        $section = $nomenclature === NomenclatureNaf::REV2
            ? $code->section()
            : NafData::sections($nomenclature)[$code->division()] ?? null;
        \assert($section !== null, 'chaque division de la nomenclature appartient à une section');

        return $section;
    }

    /**
     * Toutes les sous-classes de la nomenclature, dans l'ordre officiel.
     *
     * @return list<CodeApe>
     */
    public static function subclasses(NomenclatureNaf $nomenclature): array
    {
        $codes = [];
        foreach (array_keys(NafData::subclasses($nomenclature)) as $code) {
            $codes[] = CodeApe::fromString($code);
        }

        return $codes;
    }
}
