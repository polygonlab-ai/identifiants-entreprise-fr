<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tools;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PolygonLab\IdentifiantsEntreprise\CodeApe;
use PolygonLab\IdentifiantsEntreprise\Internal\NafData;
use PolygonLab\IdentifiantsEntreprise\NomenclatureNaf;

/**
 * Lit les fichiers officiels de l'INSEE (nomenclatures NAF rév. 2 et NAF 2025, tables de passage),
 * contrôle leur structure et prépare le contenu des fichiers de données du dossier resources/naf/.
 *
 * Au moindre écart avec ce qui est attendu (fichier absent, colonne déplacée, nombre de sous-classes,
 * code en double, code de la table de passage absent d'une nomenclature...), une SourceError est levée
 * avec un message clair, et rien n'est écrit.
 *
 * @phpstan-type Row list<string|null>
 * @phpstan-type Source array{fichier: string, sha256: string, edition: string, titre: string}
 * @phpstan-type Passage array{
 *     liens: array<string, list<array{0: string, 1: string}>>,
 *     sans_correspondance: array<string, string>,
 *     sans_origine: array<string, string>,
 *     source: Source,
 *     uniques: int,
 *     multiples: int
 * }
 */
final class SourceReader
{
    /** Page de l'INSEE d'où proviennent les fichiers. */
    public const PAGE = 'https://www.insee.fr/fr/information/8181066';

    /** Licence des données publiées par l'INSEE. */
    public const LICENCE = 'Licence Ouverte / Open Licence version 2.0 (Etalab)';

    public const FILE_REV2 = 'NAFrev2_liste_sous-classes.xls';
    public const FILE_STRUCTURE = 'NAF2025_structure.xlsx';
    public const FILE_LABELS = 'NAF2025_intitules.xlsx';
    public const FILE_REV2_TO_NAF2025 = 'Correspondances_NAFrev2-NAF2025.xlsx';
    public const FILE_NAF2025_TO_REV2 = 'Correspondances_NAF2025-NAFrev2.xlsx';

    /**
     * Nombres attendus dans l'édition d'octobre 2026. Quand l'INSEE publie une nouvelle édition, le générateur
     * s'arrête sur ces contrôles : vérifier le fichier, puis mettre ces nombres à jour.
     */
    public const EXPECTED_REV2_SUBCLASSES = 732;
    public const EXPECTED_NAF2025_SUBCLASSES = 747;
    public const EXPECTED_NAF2025_SECTIONS = 22;
    public const EXPECTED_NAF2025_DIVISIONS = 87;
    public const EXPECTED_LINKS = 1241;

    private const ABBREVIATED_MAX = 65;
    private const SHORT_MAX = 40;

    /**
     * Comptes rendus des contrôles, une ligne par constat.
     *
     * @var list<string>
     */
    private array $report = [];

    public function __construct(
        private readonly string $directory,
    ) {}

    /**
     * Contenu des fichiers de données : nom du fichier => tableau à écrire.
     *
     * @return array<string, array<string, mixed>>
     *
     * @throws SourceError
     */
    public function build(): array
    {
        $this->report = [];

        [$rev2, $rev2Source] = $this->readRev2();
        [$naf2025, $sections, $naf2025Sources] = $this->readNaf2025();

        $forward = $this->readPassage(self::FILE_REV2_TO_NAF2025, NomenclatureNaf::REV2, $rev2, $naf2025);
        $reverse = $this->readPassage(self::FILE_NAF2025_TO_REV2, NomenclatureNaf::NAF2025, $naf2025, $rev2);
        $this->checkPassagesAgree($forward, $reverse);

        $common = ['page' => self::PAGE, 'licence' => self::LICENCE];

        return [
            NafData::nomenclatureFile(NomenclatureNaf::REV2) => [
                'contenu' => 'sous-classes de la NAF rév. 2 et leur intitulé complet (le fichier de l\'INSEE ne donne ni intitulé abrégé ni intitulé court).',
                ...$common,
                'sources' => [$rev2Source],
                'sous_classes' => array_map(static fn(string $label): array => [$label, null, null], $rev2),
                'sections' => [],
            ],
            NafData::nomenclatureFile(NomenclatureNaf::NAF2025) => [
                'contenu' => 'sous-classes de la NAF 2025, intitulés complet, abrégé (65 caractères) et court (40 caractères), section de chaque division.',
                ...$common,
                'sources' => $naf2025Sources,
                'sous_classes' => $naf2025,
                'sections' => $sections,
            ],
            NafData::passageFile(NomenclatureNaf::REV2) => [
                'contenu' => 'table de passage des sous-classes, de la NAF rév. 2 vers la NAF 2025.',
                ...$common,
                'sources' => [$forward['source']],
                'liens' => $forward['liens'],
                'sans_correspondance' => $forward['sans_correspondance'],
                'sans_origine' => $forward['sans_origine'],
            ],
            NafData::passageFile(NomenclatureNaf::NAF2025) => [
                'contenu' => 'table de passage inversée des sous-classes, de la NAF 2025 vers la NAF rév. 2.',
                ...$common,
                'sources' => [$reverse['source']],
                'liens' => $reverse['liens'],
                'sans_correspondance' => $reverse['sans_correspondance'],
                'sans_origine' => $reverse['sans_origine'],
            ],
        ];
    }

    /**
     * Constats des contrôles de la dernière lecture.
     *
     * @return list<string>
     */
    public function report(): array
    {
        return $this->report;
    }

    /**
     * @return array{0: array<string, string>, 1: Source}
     */
    private function readRev2(): array
    {
        $file = self::FILE_REV2;
        $book = $this->load($file);
        $rows = $this->rows($book->getSheet(0), $file, 2);

        $title = $rows[0][0] ?? '';
        if (!str_contains((string) $title, 'NAF rév. 2')) {
            throw new SourceError(sprintf('%s : titre « NAF rév. 2 » attendu en A1, « %s » lu.', $file, (string) $title));
        }
        $this->expectHeader($file, $rows[2] ?? [], ['Code', 'Libellé']);

        $subclasses = [];
        foreach (\array_slice($rows, 3, null, true) as $index => $row) {
            if ($row === [null, null]) {
                continue;
            }
            [$code, $label] = $row;
            $code = $this->code($file, $index, $code);
            $subclasses[$code] = $this->required($file, $index, 'B', $label, $subclasses, $code);
        }

        $this->expectCount($file, 'sous-classes de la NAF rév. 2', \count($subclasses), self::EXPECTED_REV2_SUBCLASSES);
        $this->note(sprintf('%s : %d sous-classes, lettres finales %s.', $file, \count($subclasses), $this->letters($subclasses)));

        return [$subclasses, $this->source($file, $book)];
    }

    /**
     * @return array{0: array<string, array{0: string, 1: string, 2: string}>, 1: array<string, string>, 2: list<Source>}
     */
    private function readNaf2025(): array
    {
        $file = self::FILE_STRUCTURE;
        $book = $this->load($file);

        // Feuille « NAF 2025 » : la hiérarchie section > division > groupe > classe > sous-classe.
        $rows = $this->rows($this->sheet($book, $file, 'NAF 2025'), $file, 3);
        $header = $rows[0] ?? [];
        if (!str_starts_with((string) $header[0], 'NACE') || !str_contains((string) $header[1], 'sous-classes')
            || $header[2] !== 'Intitulés') {
            throw new SourceError(sprintf('%s, feuille « NAF 2025 » : en-tête inattendu (%s).', $file, $this->show($header)));
        }

        $sectionLabels = [];
        $divisions = [];
        $structure = [];
        $section = null;
        $division = null;
        $class = null;
        foreach (\array_slice($rows, 1, null, true) as $index => [$level, $code, $label]) {
            if ($level === null && $code === null && $label === null) {
                continue;
            }
            if ($level !== null && preg_match('/\A[A-Z]\z/', $level) === 1) {
                $section = $level;
                $sectionLabels[$level] = $label;
            } elseif ($level !== null && preg_match('/\A[0-9]{2}\z/', $level) === 1) {
                if ($section === null || isset($divisions[$level])) {
                    throw new SourceError(sprintf('%s, ligne %d : division %s hors section ou en double.', $file, $index + 1, $level));
                }
                $division = $level;
                $divisions[$level] = $section;
            } elseif ($level !== null && preg_match('/\A[0-9]{2}\.[0-9]{2}\z/', $level) === 1) {
                $class = $level;
            } elseif ($level !== null && preg_match('/\A[0-9]{2}\.[0-9]\z/', $level) !== 1) {
                throw new SourceError(sprintf('%s, ligne %d : niveau « %s » inattendu en colonne A.', $file, $index + 1, $level));
            }

            if ($code !== null) {
                $code = $this->code($file, $index, $code);
                if ($class === null || !str_starts_with($code, $class) || $division === null || !str_starts_with($code, $division)) {
                    throw new SourceError(sprintf('%s, ligne %d : la sous-classe %s ne suit pas sa classe.', $file, $index + 1, $code));
                }
                $structure[$code] = $this->required($file, $index, 'C', $label, $structure, $code);
            }
        }

        $this->expectList($file, 'Sections', array_keys($sectionLabels), $this->firstColumn($book, $file, 'Sections', 'NAF 2025 sections'));
        $this->expectList($file, 'Divisions', array_map('strval', array_keys($divisions)), $this->firstColumn($book, $file, 'Divisions', 'NAF 2025 divisions'));
        $this->expectList($file, 'Sous-classes', array_keys($structure), $this->firstColumn($book, $file, 'Sous-classes', "NAF 2025\nsous-classes"));
        $this->expectCount($file, 'sections de la NAF 2025', \count($sectionLabels), self::EXPECTED_NAF2025_SECTIONS);
        $this->expectCount($file, 'divisions de la NAF 2025', \count($divisions), self::EXPECTED_NAF2025_DIVISIONS);
        $this->expectCount($file, 'sous-classes de la NAF 2025', \count($structure), self::EXPECTED_NAF2025_SUBCLASSES);
        $this->note(sprintf(
            '%s : %d sections (%s à %s), %d divisions, %d sous-classes, lettres finales %s.',
            $file,
            \count($sectionLabels),
            array_key_first($sectionLabels),
            array_key_last($sectionLabels),
            \count($divisions),
            \count($structure),
            $this->letters($structure),
        ));
        $sources = [$this->source($file, $book)];

        // Intitulés complets, abrégés et courts.
        $file = self::FILE_LABELS;
        $book = $this->load($file);
        $rows = $this->rows($this->sheet($book, $file, 'Intitulés des sous-classes'), $file, 4);
        $header = $rows[0] ?? [];
        foreach (['Code', 'Intitulé complet', 'Intitulé abrégé', 'Intitulé court'] as $column => $start) {
            if (!str_starts_with((string) ($header[$column] ?? ''), $start)) {
                throw new SourceError(sprintf('%s : colonne %d « %s… » attendue, « %s » lu.', $file, $column + 1, $start, (string) ($header[$column] ?? '')));
            }
        }

        $subclasses = [];
        foreach (\array_slice($rows, 1, null, true) as $index => [$code, $full, $abbreviated, $short]) {
            if ($code === null && $full === null && $abbreviated === null && $short === null) {
                continue;
            }
            $code = $this->code($file, $index, $code);
            $full = $this->required($file, $index, 'B', $full, $subclasses, $code);
            $abbreviated = $this->required($file, $index, 'C', $abbreviated, [], $code);
            $short = $this->required($file, $index, 'D', $short, [], $code);
            if (mb_strlen($abbreviated) > self::ABBREVIATED_MAX || mb_strlen($short) > self::SHORT_MAX) {
                throw new SourceError(sprintf('%s, ligne %d : intitulé abrégé ou court trop long pour %s.', $file, $index + 1, $code));
            }
            $subclasses[$code] = [$full, $abbreviated, $short];
        }

        $this->expectList($file, 'Intitulés des sous-classes', array_keys($structure), array_keys($subclasses));
        foreach ($structure as $code => $label) {
            if ($subclasses[$code][0] !== $label) {
                throw new SourceError(sprintf('%s : l\'intitulé de %s diffère de celui de %s.', $file, $code, self::FILE_STRUCTURE));
            }
        }
        $this->note(sprintf(
            '%s : %d sous-classes, intitulés identiques à ceux de %s, intitulés abrégés de %d caractères au plus, courts de %d au plus.',
            $file,
            \count($subclasses),
            self::FILE_STRUCTURE,
            array_reduce($subclasses, static fn(int $max, array $labels): int => max($max, mb_strlen($labels[1])), 0),
            array_reduce($subclasses, static fn(int $max, array $labels): int => max($max, mb_strlen($labels[2])), 0),
        ));
        $sources[] = $this->source($file, $book);

        ksort($divisions, SORT_STRING);
        $sections = [];
        foreach ($divisions as $key => $letter) {
            $sections[(string) $key] = $letter;
        }

        return [$subclasses, $sections, $sources];
    }

    /**
     * @param array<string, mixed> $fromCodes
     * @param array<string, mixed> $toCodes
     *
     * @return Passage
     */
    private function readPassage(string $file, NomenclatureNaf $from, array $fromCodes, array $toCodes): array
    {
        $to = $from === NomenclatureNaf::REV2 ? NomenclatureNaf::NAF2025 : NomenclatureNaf::REV2;
        $book = $this->load($file);
        $rows = $this->rows($book->getSheet(0), $file, 6);

        $header = $rows[0] ?? [];
        $expected = [
            0 => 'Sous-classes ' . $from->label() . "\ncodes",
            2 => "Contenu commun\ndes sous-classes en correspondance",
            3 => 'Sous-classes ' . $to->label() . "\ncodes",
            5 => 'Type de correspondance',
        ];
        foreach ($expected as $column => $text) {
            if (($header[$column] ?? null) !== $text) {
                throw new SourceError(sprintf(
                    '%s : colonne %d « %s » attendue, « %s » lu.',
                    $file,
                    $column + 1,
                    str_replace("\n", ' ', $text),
                    str_replace("\n", ' ', (string) ($header[$column] ?? '')),
                ));
            }
        }

        $links = [];
        $types = [];
        $withoutCounterpart = [];
        $withoutOrigin = [];
        $count = 0;
        foreach (\array_slice($rows, 1, null, true) as $index => [$source, , $content, $target, , $type]) {
            $line = $index + 1;
            if ($source === null && $target === null && $content === null && $type === null) {
                continue;
            }
            if ($content === null) {
                throw new SourceError(sprintf('%s, ligne %d : contenu commun absent.', $file, $line));
            }
            if ($source === null || $target === null) {
                if (($source === null) === ($target === null) || $type !== null || !str_starts_with($content, 'pas de correspondance')) {
                    throw new SourceError(sprintf('%s, ligne %d : ligne incomplète.', $file, $line));
                }
                if ($source === null) {
                    $withoutOrigin[$this->member($file, $line, $this->code($file, $index, $target), $toCodes, $to)] = $content;
                } else {
                    $withoutCounterpart[$this->member($file, $line, $this->code($file, $index, $source), $fromCodes, $from)] = $content;
                }
                continue;
            }

            $source = $this->member($file, $line, $this->code($file, $index, $source), $fromCodes, $from);
            $target = $this->member($file, $line, $this->code($file, $index, $target), $toCodes, $to);
            if ($type !== 'Unique' && $type !== 'Multiple') {
                throw new SourceError(sprintf('%s, ligne %d : type de correspondance « %s » inconnu.', $file, $line, (string) $type));
            }
            foreach ($links[$source] ?? [] as [$known]) {
                if ($known === $target) {
                    throw new SourceError(sprintf('%s, ligne %d : lien %s vers %s en double.', $file, $line, $source, $target));
                }
            }
            $links[$source][] = [$target, $content];
            $types[$source][$type] = true;
            ++$count;
        }

        $uniques = 0;
        foreach ($links as $source => $targets) {
            $expectedType = \count($targets) === 1 ? 'Unique' : 'Multiple';
            if (array_keys($types[$source]) !== [$expectedType]) {
                throw new SourceError(sprintf('%s : %s a %d correspondant(s) mais n\'est pas marqué « %s ».', $file, $source, \count($targets), $expectedType));
            }
            $uniques += \count($targets) === 1 ? 1 : 0;
        }

        foreach (array_keys($fromCodes) as $code) {
            if (isset($links[$code]) === isset($withoutCounterpart[$code])) {
                throw new SourceError(sprintf('%s : le code %s de la %s est absent de la table ou y figure deux fois.', $file, $code, $from->label()));
            }
        }
        $reached = [];
        foreach ($links as $targets) {
            foreach ($targets as [$target]) {
                $reached[$target] = true;
            }
        }
        foreach (array_keys($toCodes) as $code) {
            if (isset($reached[$code]) === isset($withoutOrigin[$code])) {
                throw new SourceError(sprintf('%s : le code %s de la %s n\'est atteint par aucune ligne, ou l\'est en plus d\'une ligne sans origine.', $file, $code, $to->label()));
            }
        }
        $this->expectCount($file, 'liens', $count, self::EXPECTED_LINKS);

        $this->note(sprintf(
            '%s : %d liens, %d codes de départ dont %d « Unique » et %d « Multiple » ; codes de départ sans correspondance : %d ; codes d\'arrivée sans origine : %d (%s).',
            $file,
            $count,
            \count($links),
            $uniques,
            \count($links) - $uniques,
            \count($withoutCounterpart),
            \count($withoutOrigin),
            implode(', ', array_keys($withoutCounterpart + $withoutOrigin)),
        ));

        return [
            'liens' => $links,
            'sans_correspondance' => $withoutCounterpart,
            'sans_origine' => $withoutOrigin,
            'source' => $this->source($file, $book),
            'uniques' => $uniques,
            'multiples' => \count($links) - $uniques,
        ];
    }

    /**
     * @param Passage $forward
     * @param Passage $reverse
     */
    private function checkPassagesAgree(array $forward, array $reverse): void
    {
        if (self::pairs($forward['liens'], false) !== self::pairs($reverse['liens'], true)) {
            throw new SourceError(sprintf('%s et %s ne contiennent pas les mêmes liens.', self::FILE_REV2_TO_NAF2025, self::FILE_NAF2025_TO_REV2));
        }
        if (array_keys($forward['sans_origine']) !== array_keys($reverse['sans_correspondance'])
            || array_keys($forward['sans_correspondance']) !== array_keys($reverse['sans_origine'])) {
            throw new SourceError(sprintf('%s et %s ne signalent pas les mêmes codes sans correspondance.', self::FILE_REV2_TO_NAF2025, self::FILE_NAF2025_TO_REV2));
        }
        $this->note('Les deux tables de passage contiennent exactement les mêmes liens, dans un sens puis dans l\'autre.');
    }

    /**
     * Liens sous la forme « départ>arrivée », triés ; $flip lit la table dans l'autre sens.
     *
     * @param array<string, list<array{0: string, 1: string}>> $links
     *
     * @return list<string>
     */
    private static function pairs(array $links, bool $flip): array
    {
        $out = [];
        foreach ($links as $source => $targets) {
            foreach ($targets as [$target]) {
                $out[] = $flip ? $target . '>' . $source : $source . '>' . $target;
            }
        }
        sort($out);

        return $out;
    }

    private function load(string $file): Spreadsheet
    {
        $path = rtrim($this->directory, '/') . '/' . $file;
        if (!is_file($path)) {
            throw new SourceError(sprintf('Fichier absent : %s (dossier %s).', $file, $this->directory));
        }

        $reader = IOFactory::createReader(str_ends_with($file, '.xlsx') ? 'Xlsx' : 'Xls');
        $reader->setReadDataOnly(true);

        return $reader->load($path);
    }

    private function sheet(Spreadsheet $book, string $file, string $name): Worksheet
    {
        return $book->getSheetByName($name)
            ?? throw new SourceError(sprintf('%s : feuille « %s » absente.', $file, $name));
    }

    /**
     * Lignes de la feuille, réduites aux colonnes utiles : textes sans espaces de début et de fin, cellules
     * vides à null. Les colonnes au-delà doivent être vides.
     *
     * @return list<Row>
     */
    private function rows(Worksheet $sheet, string $file, int $width): array
    {
        $rows = [];
        foreach (array_values($sheet->toArray(null, false, false, false)) as $index => $cells) {
            $row = [];
            foreach (array_values($cells) as $column => $value) {
                if (\is_int($value)) {
                    $value = (string) $value;
                }
                if ($value !== null && !\is_string($value)) {
                    throw new SourceError(sprintf('%s, ligne %d : valeur inattendue (%s).', $file, $index + 1, get_debug_type($value)));
                }
                $value = $value === null ? null : preg_replace('/\A\s+|\s+\z/u', '', $value);
                $value = $value === '' ? null : $value;
                if ($column >= $width) {
                    if ($value !== null) {
                        throw new SourceError(sprintf('%s, ligne %d : colonne %d inattendue (« %s »).', $file, $index + 1, $column + 1, $value));
                    }
                    continue;
                }
                $row[] = $value;
            }
            $rows[] = array_pad($row, $width, null);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function firstColumn(Spreadsheet $book, string $file, string $name, string $title): array
    {
        $rows = $this->rows($this->sheet($book, $file, $name), $file, 2);
        if (($rows[0][0] ?? null) !== $title) {
            throw new SourceError(sprintf('%s, feuille « %s » : en-tête « %s » attendu.', $file, $name, str_replace("\n", ' ', $title)));
        }

        $values = [];
        foreach (\array_slice($rows, 1) as $row) {
            if ($row[0] !== null) {
                $values[] = $row[0];
            }
        }

        return $values;
    }

    private function code(string $file, int $index, ?string $value): string
    {
        $code = CodeApe::tryFromString((string) $value);
        if ($code === null || $code->format() !== $value) {
            throw new SourceError(sprintf(
                '%s, ligne %d : « %s » n\'a pas le format d\'un code APE accepté par CodeApe (00.00A). '
                . 'Si la nomenclature a changé de format, adapter CodeApe avant de régénérer les données.',
                $file,
                $index + 1,
                (string) $value,
            ));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $seen
     */
    private function required(string $file, int $index, string $column, ?string $value, array $seen, string $code): string
    {
        if (isset($seen[$code])) {
            throw new SourceError(sprintf('%s, ligne %d : code %s en double.', $file, $index + 1, $code));
        }
        if ($value === null) {
            throw new SourceError(sprintf('%s, ligne %d : colonne %s vide pour %s.', $file, $index + 1, $column, $code));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $codes
     */
    private function member(string $file, int $line, string $code, array $codes, NomenclatureNaf $nomenclature): string
    {
        if (!isset($codes[$code])) {
            throw new SourceError(sprintf('%s, ligne %d : le code %s est absent de la %s.', $file, $line, $code, $nomenclature->label()));
        }

        return $code;
    }

    /**
     * @param Row $header
     * @param list<string> $expected
     */
    private function expectHeader(string $file, array $header, array $expected): void
    {
        if ($header !== $expected) {
            throw new SourceError(sprintf('%s : en-tête %s attendu, %s lu.', $file, $this->show($expected), $this->show($header)));
        }
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     */
    private function expectList(string $file, string $sheet, array $expected, array $actual): void
    {
        if ($expected !== $actual) {
            $missing = array_diff($expected, $actual);
            $extra = array_diff($actual, $expected);
            throw new SourceError(sprintf(
                '%s, feuille « %s » : liste différente de la hiérarchie (absents : %s ; en trop : %s ; ou ordre différent).',
                $file,
                $sheet,
                $missing === [] ? 'aucun' : implode(', ', $missing),
                $extra === [] ? 'aucun' : implode(', ', $extra),
            ));
        }
    }

    private function expectCount(string $file, string $what, int $actual, int $expected): void
    {
        if ($actual !== $expected) {
            throw new SourceError(sprintf(
                '%s : %d %s lues, %d attendues. Vérifier le fichier, puis mettre à jour le nombre attendu dans %s.',
                $file,
                $actual,
                $what,
                $expected,
                self::class,
            ));
        }
    }

    /**
     * @return Source
     */
    private function source(string $file, Spreadsheet $book): array
    {
        $hash = hash_file('sha256', rtrim($this->directory, '/') . '/' . $file);
        \assert(\is_string($hash));

        return [
            'fichier' => $file,
            'sha256' => $hash,
            'edition' => gmdate('Y-m-d', (int) $book->getProperties()->getModified()),
            'titre' => trim($book->getProperties()->getTitle()),
        ];
    }

    /**
     * @param array<string, mixed> $codes
     */
    private function letters(array $codes): string
    {
        $letters = [];
        foreach (array_keys($codes) as $code) {
            $letters[substr($code, -1)] = true;
        }
        ksort($letters);

        return implode(', ', array_keys($letters));
    }

    /**
     * @param array<array-key, string|null> $values
     */
    private function show(array $values): string
    {
        return '[' . implode(' | ', array_map(static fn(?string $value): string => str_replace("\n", ' ', (string) $value), $values)) . ']';
    }

    private function note(string $line): void
    {
        $this->report[] = $line;
    }
}
