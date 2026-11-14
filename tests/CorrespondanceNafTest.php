<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tests;

use PHPUnit\Framework\TestCase;
use PolygonLab\IdentifiantsEntreprise\CodeApe;
use PolygonLab\IdentifiantsEntreprise\CorrespondanceNaf;
use PolygonLab\IdentifiantsEntreprise\IdentifiantInvalide;
use PolygonLab\IdentifiantsEntreprise\MappingType;
use PolygonLab\IdentifiantsEntreprise\Naf;
use PolygonLab\IdentifiantsEntreprise\NafLink;
use PolygonLab\IdentifiantsEntreprise\NomenclatureNaf;

/**
 * Codes réels de la table de passage officielle de l'INSEE (édition d'octobre 2026).
 */
final class CorrespondanceNafTest extends TestCase
{
    public function testCodeThatDoesNotChange(): void
    {
        // 62.01Z Programmation informatique : un seul correspondant, qui ne reçoit que lui.
        $passage = CorrespondanceNaf::versNaf2025(CodeApe::fromString('62.01Z'));

        self::assertSame('62.01Z', $passage->source()->format());
        self::assertSame(NomenclatureNaf::REV2, $passage->from());
        self::assertSame(NomenclatureNaf::NAF2025, $passage->to());
        self::assertSame(MappingType::ONE_TO_ONE, $passage->type());
        self::assertTrue($passage->isAutomatic());
        self::assertFalse($passage->dependsOnActivity());
        self::assertFalse($passage->hasNoCorrespondence());
        self::assertNull($passage->note());
        self::assertSame('62.10Y', $passage->uniqueCode()?->format());
        self::assertSame(['62.10Y'], self::formats($passage->codes()));

        $link = $passage->links()[0];
        self::assertSame('Activités de programmation informatique', $link->intitule());
        self::assertSame('tout le contenu de la sous-classe NAF rév. 2', $link->commonContent());
        self::assertSame([], $link->otherSources());

        $back = CorrespondanceNaf::versNafRev2(CodeApe::fromString('62.10Y'));
        self::assertSame(MappingType::ONE_TO_ONE, $back->type());
        self::assertSame('62.01Z', $back->uniqueCode()?->format());
        self::assertSame('toute la sous-classe NAF 2025', $back->links()[0]->commonContent());
    }

    public function testConsultingCodeKeepsItsOwnCorrespondent(): void
    {
        // 62.02A Conseil en systèmes et logiciels informatiques devient 62.20G.
        $passage = CorrespondanceNaf::versNaf2025(CodeApe::fromString('62.02A'));

        self::assertSame(MappingType::ONE_TO_ONE, $passage->type());
        self::assertSame('62.20G', $passage->uniqueCode()?->format());
        self::assertSame('Conseil en systèmes et logiciels informatiques', $passage->links()[0]->intitule());
    }

    public function testSplitIntoSeveralCodesCannotBeChosenAutomatically(): void
    {
        // 63.11Z Traitement de données, hébergement et activités connexes : trois correspondants.
        $passage = CorrespondanceNaf::versNaf2025(CodeApe::fromString('63.11Z'));

        self::assertSame(MappingType::SPLIT, $passage->type());
        self::assertSame('éclatement en plusieurs codes', $passage->type()->label());
        self::assertFalse($passage->isAutomatic());
        self::assertTrue($passage->dependsOnActivity());
        self::assertNull($passage->uniqueCode());
        self::assertSame(['60.20H', '60.39Y', '63.10Y'], self::formats($passage->codes()));

        $links = $passage->links();
        self::assertStringStartsWith('- l\'exploitation de sites de diffusion en continu de jeux vidéo', $links[0]->commonContent());
        self::assertSame(['60.20A', '60.20B'], self::formats($links[0]->otherSources()));
        self::assertSame(['63.12Z'], self::formats($links[1]->otherSources()));
        self::assertSame([], $links[2]->otherSources());
    }

    public function testMergeIntoACodeThatAlsoReceivesOtherCodes(): void
    {
        // 62.02B Tierce maintenance et 62.03Z Gestion d'installations informatiques se regroupent en 62.20H.
        foreach (['62.02B' => '62.03Z', '62.03Z' => '62.02B'] as $code => $other) {
            $passage = CorrespondanceNaf::versNaf2025(CodeApe::fromString($code));

            self::assertSame(MappingType::MERGE, $passage->type());
            self::assertTrue($passage->isAutomatic());
            self::assertSame('62.20H', $passage->uniqueCode()?->format());
            self::assertSame([$other], self::formats($passage->links()[0]->otherSources()));
        }

        // En sens inverse, 62.20H vient de deux codes : le choix dépend de l'activité.
        $back = CorrespondanceNaf::versNafRev2(CodeApe::fromString('62.20H'));
        self::assertSame(MappingType::SPLIT, $back->type());
        self::assertSame(['62.02B', '62.03Z'], self::formats($back->codes()));
    }

    public function testRemovedCodeIsAbsorbedByAnother(): void
    {
        // 33.20A disparaît : avec 33.20B, 33.20C et 33.20D, il forme la seule sous-classe 33.20Y.
        $code = CodeApe::fromString('33.20A');
        $passage = CorrespondanceNaf::versNaf2025($code);

        self::assertTrue($code->existe(NomenclatureNaf::REV2));
        self::assertFalse($code->existe(NomenclatureNaf::NAF2025));
        self::assertSame(MappingType::MERGE, $passage->type());
        self::assertSame('33.20Y', $passage->uniqueCode()?->format());
        self::assertSame(['33.20B', '33.20C', '33.20D'], self::formats($passage->links()[0]->otherSources()));
    }

    public function testNewCodeWithoutDeterminedOrigin(): void
    {
        $code = CodeApe::fromString('46.89Y');
        $passage = CorrespondanceNaf::versNafRev2($code);

        self::assertTrue($code->existe(NomenclatureNaf::NAF2025));
        self::assertSame(MappingType::NONE, $passage->type());
        self::assertSame('pas de correspondance déterminée', $passage->type()->label());
        self::assertTrue($passage->hasNoCorrespondence());
        self::assertFalse($passage->isAutomatic());
        self::assertFalse($passage->dependsOnActivity());
        self::assertNull($passage->uniqueCode());
        self::assertSame([], $passage->links());
        self::assertSame(
            'pas de correspondance déterminée à ce jour depuis cette nouvelle sous-classe NAF 2025 vers la NAF rév. 2',
            $passage->note(),
        );
        self::assertSame(['46.89Y'], self::formats(CorrespondanceNaf::newCodesWithoutOrigin()));
    }

    public function testUnknownCodeIsRefused(): void
    {
        $this->expectException(IdentifiantInvalide::class);
        $this->expectExceptionMessage('Le code APE 00.00A n\'existe pas dans la NAF rév. 2.');

        CorrespondanceNaf::versNaf2025(CodeApe::fromString('00.00A'));
    }

    public function testCodeOfTheWrongNomenclatureIsRefused(): void
    {
        $this->expectException(IdentifiantInvalide::class);
        $this->expectExceptionMessage('Le code APE 62.01Z n\'existe pas dans la NAF 2025 (c\'est un code de la NAF rév. 2).');

        CorrespondanceNaf::versNafRev2(CodeApe::fromString('62.01Z'));
    }

    public function testTypeLabels(): void
    {
        self::assertSame('correspondance unique', MappingType::ONE_TO_ONE->label());
        self::assertSame('regroupement avec d\'autres codes', MappingType::MERGE->label());
    }

    public function testEveryCodeHasAConsistentPassage(): void
    {
        $counts = ['rev2' => [], 'naf2025' => []];
        foreach (NomenclatureNaf::cases() as $from) {
            foreach (Naf::subclasses($from) as $code) {
                $passage = $from === NomenclatureNaf::REV2
                    ? CorrespondanceNaf::versNaf2025($code)
                    : CorrespondanceNaf::versNafRev2($code);
                $counts[$from->value][$passage->type()->value] = ($counts[$from->value][$passage->type()->value] ?? 0) + 1;

                foreach ($passage->links() as $link) {
                    self::assertTrue(Naf::existe($link->code(), $passage->to()));
                    self::assertSame(Naf::intitule($link->code(), $passage->to()), $link->intitule());

                    // Le lien existe dans la table inversée.
                    $back = $from === NomenclatureNaf::REV2
                        ? CorrespondanceNaf::versNafRev2($link->code())
                        : CorrespondanceNaf::versNaf2025($link->code());
                    self::assertContains($code->format(), self::formats($back->codes()));
                }
            }
        }

        self::assertSame(['unique' => 361, 'eclatement' => 216, 'regroupement' => 155], self::sorted($counts['rev2']));
        self::assertSame(['unique' => 361, 'eclatement' => 231, 'regroupement' => 154, 'aucune' => 1], self::sorted($counts['naf2025']));
    }

    /**
     * @param list<CodeApe> $codes
     *
     * @return list<string>
     */
    private static function formats(array $codes): array
    {
        return array_map(static fn(CodeApe $code): string => $code->format(), $codes);
    }

    /**
     * @param array<string, int> $counts
     *
     * @return array<string, int>
     */
    private static function sorted(array $counts): array
    {
        $order = array_flip(['unique', 'eclatement', 'regroupement', 'aucune']);
        uksort($counts, static fn(string $a, string $b): int => $order[$a] <=> $order[$b]);

        return $counts;
    }

    public function testLinkIsAPlainValueObject(): void
    {
        $link = new NafLink(CodeApe::fromString('62.10Y'), 'Intitulé', 'Contenu', []);

        self::assertSame('62.10Y', $link->code()->format());
        self::assertSame('Intitulé', $link->intitule());
        self::assertSame('Contenu', $link->commonContent());
    }
}
