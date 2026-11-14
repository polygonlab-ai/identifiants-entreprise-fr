<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tests\Tools;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PolygonLab\IdentifiantsEntreprise\Tools\SourceError;
use PolygonLab\IdentifiantsEntreprise\Tools\SourceReader;

/**
 * Le générateur s'arrête avec un message clair dès qu'un fichier ne ressemble pas à ce qu'il attend.
 *
 * Les premiers tests fabriquent de faux fichiers. Les derniers altèrent une copie des vrais fichiers de l'INSEE,
 * si la variable d'environnement NAF_SOURCES_INSEE désigne leur dossier (ils ne sont pas dans le dépôt).
 */
final class SourceReaderTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/naf-sources-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testMissingFileIsReported(): void
    {
        $this->expectException(SourceError::class);
        $this->expectExceptionMessage('Fichier absent : NAFrev2_liste_sous-classes.xls');

        (new SourceReader($this->directory))->build();
    }

    /**
     * @return iterable<string, array{list<list<string|null>>, string}>
     */
    public static function brokenRev2Files(): iterable
    {
        $title = ['NAF rév. 2, 2008 - Niveau 5 - Liste des sous-classes', null];
        $header = ['Code', 'Libellé'];

        yield 'titre absent' => [[['Autre chose', null], [null, null], $header], 'titre « NAF rév. 2 » attendu en A1'];
        yield 'colonnes inversées' => [[$title, [null, null], ['Libellé', 'Code']], 'en-tête [Code | Libellé] attendu'];
        yield 'code en double' => [
            [$title, [null, null], $header, ['01.11Z', 'Culture'], ['01.11Z', 'Culture bis']],
            'ligne 5 : code 01.11Z en double',
        ];
        yield 'intitulé absent' => [[$title, [null, null], $header, ['01.11Z', null]], 'colonne B vide pour 01.11Z'];
        yield 'code mal formé' => [
            [$title, [null, null], $header, ['01.11', 'Culture']],
            '« 01.11 » n\'a pas le format d\'un code APE accepté par CodeApe',
        ];
        yield 'colonne en trop' => [
            [$title, [null, null], [...$header, 'Note'], ['01.11Z', 'Culture']],
            'colonne 3 inattendue (« Note »)',
        ];
        yield 'nombre de sous-classes' => [
            [$title, [null, null], $header, ['01.11Z', 'Culture'], ['01.12Z', 'Riz']],
            '2 sous-classes de la NAF rév. 2 lues, 732 attendues',
        ];
    }

    /**
     * @param list<list<string|null>> $rows
     */
    #[DataProvider('brokenRev2Files')]
    public function testBrokenRev2FileIsRefused(array $rows, string $message): void
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray($rows, null, 'A1', true);
        IOFactory::createWriter($book, 'Xls')->save($this->directory . '/' . SourceReader::FILE_REV2);

        $this->expectException(SourceError::class);
        $this->expectExceptionMessage($message);

        (new SourceReader($this->directory))->build();
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function alteredInseeFiles(): iterable
    {
        yield 'code de la table absent de la nomenclature' => [
            SourceReader::FILE_REV2_TO_NAF2025,
            'D2',
            '01.11L',
            'Correspondances_NAFrev2-NAF2025.xlsx, ligne 2 : le code 01.11L est absent de la NAF 2025.',
        ];
        yield 'type de correspondance faux' => [
            SourceReader::FILE_REV2_TO_NAF2025,
            'F2',
            'Multiple',
            'Correspondances_NAFrev2-NAF2025.xlsx : 01.11Z a 1 correspondant(s) mais n\'est pas marqué « Unique ».',
        ];
        yield 'code de la nomenclature absent de la table' => [
            SourceReader::FILE_NAF2025_TO_REV2,
            'D2',
            '01.12Z',
            'Correspondances_NAF2025-NAFrev2.xlsx : le code 01.11Z de la NAF rév. 2 n\'est atteint par aucune ligne',
        ];
        yield 'intitulé différent entre structure et intitulés' => [
            SourceReader::FILE_LABELS,
            'B2',
            'Autre intitulé',
            'NAF2025_intitules.xlsx : l\'intitulé de 01.11Y diffère de celui de NAF2025_structure.xlsx.',
        ];
    }

    #[DataProvider('alteredInseeFiles')]
    public function testAlteredInseeFileIsRefused(string $file, string $cell, string $value, string $message): void
    {
        $sources = getenv('NAF_SOURCES_INSEE');
        if (!\is_string($sources) || $sources === '') {
            self::markTestSkipped('NAF_SOURCES_INSEE non définie : fichiers de l\'INSEE non disponibles.');
        }

        foreach ([SourceReader::FILE_REV2, SourceReader::FILE_STRUCTURE, SourceReader::FILE_LABELS, SourceReader::FILE_REV2_TO_NAF2025, SourceReader::FILE_NAF2025_TO_REV2] as $name) {
            copy($sources . '/' . $name, $this->directory . '/' . $name);
        }
        $path = $this->directory . '/' . $file;
        $book = IOFactory::load($path);
        $sheet = $file === SourceReader::FILE_LABELS ? $book->getSheetByName('Intitulés des sous-classes') : $book->getSheet(0);
        self::assertNotNull($sheet);
        $sheet->setCellValue($cell, $value);
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        $reader = new SourceReader($this->directory);

        $this->expectException(SourceError::class);
        $this->expectExceptionMessage($message);

        $reader->build();
    }

    public function testCommandLineChecksTheDataWithoutWriting(): void
    {
        $sources = getenv('NAF_SOURCES_INSEE');
        if (!\is_string($sources) || $sources === '') {
            self::markTestSkipped('NAF_SOURCES_INSEE non définie : fichiers de l\'INSEE non disponibles.');
        }

        exec(\PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../tools/generer-donnees-naf.php') . ' ' . escapeshellarg($sources) . ' --verifier', $output, $code);

        self::assertSame(0, $code, implode("\n", $output));
        self::assertContains('À jour : resources/naf/passage-rev2-vers-naf2025.php', $output);
    }

    public function testCommandLineUsage(): void
    {
        exec(\PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../tools/generer-donnees-naf.php') . ' 2>&1', $output, $code);

        self::assertSame(2, $code);
        self::assertStringStartsWith('Usage : php tools/generer-donnees-naf.php', implode("\n", $output));
    }

    public function testCommandLineStopsOnABrokenSource(): void
    {
        exec(\PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../tools/generer-donnees-naf.php') . ' ' . escapeshellarg($this->directory) . ' 2>&1', $output, $code);

        self::assertSame(1, $code);
        self::assertSame(
            ['Arrêt : Fichier absent : NAFrev2_liste_sous-classes.xls (dossier ' . $this->directory . ').', 'Aucun fichier n\'a été écrit.'],
            $output,
        );
    }
}
