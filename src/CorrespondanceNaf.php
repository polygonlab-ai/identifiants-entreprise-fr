<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise;

use PolygonLab\IdentifiantsEntreprise\Internal\NafData;

/**
 * Passage d'un code APE d'une nomenclature à l'autre, d'après la table de passage officielle de l'INSEE
 * (NAF rév. 2 vers NAF 2025, et table inversée).
 *
 * Le résultat dit si le passage est automatique (un seul code correspondant) ou si le choix dépend de
 * l'activité réellement exercée (éclatement en plusieurs codes) : dans ce cas, la bibliothèque ne choisit pas.
 */
final class CorrespondanceNaf
{
    /**
     * @param list<NafLink> $links
     */
    private function __construct(
        private readonly CodeApe $source,
        private readonly NomenclatureNaf $from,
        private readonly MappingType $type,
        private readonly array $links,
        private readonly ?string $note,
    ) {}

    /**
     * Codes NAF 2025 correspondant à un code NAF rév. 2.
     *
     * @throws IdentifiantInvalide si le code n'existe pas dans la NAF rév. 2
     */
    public static function versNaf2025(CodeApe $code): self
    {
        return self::lookup($code, NomenclatureNaf::REV2);
    }

    /**
     * Codes NAF rév. 2 correspondant à un code NAF 2025.
     *
     * @throws IdentifiantInvalide si le code n'existe pas dans la NAF 2025
     */
    public static function versNafRev2(CodeApe $code): self
    {
        return self::lookup($code, NomenclatureNaf::NAF2025);
    }

    /**
     * Codes NAF 2025 nouveaux auxquels l'INSEE n'a, à ce jour, rattaché aucun code NAF rév. 2.
     *
     * @return list<CodeApe>
     */
    public static function newCodesWithoutOrigin(): array
    {
        $codes = [];
        foreach (array_keys(NafData::withoutOrigin(NomenclatureNaf::REV2)) as $code) {
            $codes[] = CodeApe::fromString($code);
        }

        return $codes;
    }

    /**
     * Code de départ.
     */
    public function source(): CodeApe
    {
        return $this->source;
    }

    /**
     * Nomenclature du code de départ.
     */
    public function from(): NomenclatureNaf
    {
        return $this->from;
    }

    /**
     * Nomenclature des codes correspondants.
     */
    public function to(): NomenclatureNaf
    {
        return self::other($this->from);
    }

    public function type(): MappingType
    {
        return $this->type;
    }

    /**
     * Codes correspondants, avec la part d'activité reprise par chacun, dans l'ordre de la table.
     *
     * @return list<NafLink>
     */
    public function links(): array
    {
        return $this->links;
    }

    /**
     * Codes correspondants seuls, dans l'ordre de la table.
     *
     * @return list<CodeApe>
     */
    public function codes(): array
    {
        return array_map(static fn(NafLink $link): CodeApe => $link->code(), $this->links);
    }

    /**
     * Vrai si le passage est automatique : un seul code correspondant (correspondance unique ou regroupement).
     */
    public function isAutomatic(): bool
    {
        return \count($this->links) === 1;
    }

    /**
     * Vrai si plusieurs codes correspondent : le bon code dépend de l'activité réellement exercée et ne peut
     * pas être choisi automatiquement. Les textes de NafLink::commonContent() aident à le choisir.
     */
    public function dependsOnActivity(): bool
    {
        return \count($this->links) > 1;
    }

    /**
     * Le code correspondant quand le passage est automatique, sinon null.
     */
    public function uniqueCode(): ?CodeApe
    {
        return $this->isAutomatic() ? $this->links[0]->code() : null;
    }

    /**
     * Vrai si l'INSEE n'a déterminé aucune correspondance à ce jour : en partant de la NAF 2025, c'est un
     * code nouveau sans origine déterminée dans la NAF rév. 2.
     */
    public function hasNoCorrespondence(): bool
    {
        return $this->type === MappingType::NONE;
    }

    /**
     * Texte de l'INSEE quand aucune correspondance n'est déterminée, sinon null.
     */
    public function note(): ?string
    {
        return $this->note;
    }

    private static function lookup(CodeApe $code, NomenclatureNaf $from): self
    {
        if (!Naf::existe($code, $from)) {
            throw NafData::unknown($code, $from);
        }

        $key = $code->format();
        $to = self::other($from);
        $reverse = NafData::links($to);
        $targetLabels = NafData::subclasses($to);

        $links = [];
        foreach (NafData::links($from)[$key] ?? [] as [$target, $content]) {
            $others = [];
            foreach ($reverse[$target] ?? [] as [$origin]) {
                if ($origin !== $key) {
                    $others[] = CodeApe::fromString($origin);
                }
            }
            $links[] = new NafLink(CodeApe::fromString($target), $targetLabels[$target][0], $content, $others);
        }

        $type = match (true) {
            $links === [] => MappingType::NONE,
            \count($links) > 1 => MappingType::SPLIT,
            $links[0]->otherSources() !== [] => MappingType::MERGE,
            default => MappingType::ONE_TO_ONE,
        };

        return new self($code, $from, $type, $links, NafData::withoutCounterpart($from)[$key] ?? null);
    }

    private static function other(NomenclatureNaf $nomenclature): NomenclatureNaf
    {
        return $nomenclature === NomenclatureNaf::REV2 ? NomenclatureNaf::NAF2025 : NomenclatureNaf::REV2;
    }
}
