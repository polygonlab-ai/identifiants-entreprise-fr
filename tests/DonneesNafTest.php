<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PolygonLab\IdentifiantsEntreprise\Internal\NafData;
use PolygonLab\IdentifiantsEntreprise\NomenclatureNaf;
use PolygonLab\IdentifiantsEntreprise\Tools\DataRenderer;
use PolygonLab\IdentifiantsEntreprise\Tools\SourceReader;

/**
 * Les fichiers de resources/naf/ sont exactement ceux que produit le générateur.
 *
 * - Toujours : chaque fichier, relu puis réécrit par le générateur, redonne le même fichier octet pour octet
 *   (aucune modification à la main).
 * - Si la variable d'environnement NAF_SOURCES_INSEE désigne le dossier des fichiers de l'INSEE : la
 *   régénération complète depuis ces fichiers redonne les mêmes fichiers. Les fichiers de l'INSEE ne sont pas
 *   dans le dépôt : sans cette variable, ce test est ignoré.
 */
final class DonneesNafTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function files(): iterable
    {
        foreach (NomenclatureNaf::cases() as $nomenclature) {
            yield NafData::nomenclatureFile($nomenclature) => [NafData::nomenclatureFile($nomenclature)];
            yield NafData::passageFile($nomenclature) => [NafData::passageFile($nomenclature)];
        }
    }

    #[DataProvider('files')]
    public function testFileIsExactlyWhatTheGeneratorWrites(string $file): void
    {
        $path = NafData::DIRECTORY . '/' . $file;
        $data = require $path;
        self::assertIsArray($data);

        self::assertSame(file_get_contents($path), DataRenderer::render($data));
    }

    #[DataProvider('files')]
    public function testFileCarriesItsOrigin(string $file): void
    {
        $content = (string) file_get_contents(NafData::DIRECTORY . '/' . $file);
        $data = require NafData::DIRECTORY . '/' . $file;
        self::assertIsArray($data);

        self::assertStringContainsString('ne pas modifier à la main', $content);
        self::assertSame(SourceReader::PAGE, $data['page']);
        self::assertSame(SourceReader::LICENCE, $data['licence']);
        self::assertIsArray($data['sources']);
        self::assertNotEmpty($data['sources']);
        foreach ($data['sources'] as $source) {
            self::assertIsArray($source);
            self::assertIsString($source['fichier']);
            self::assertIsString($source['sha256']);
            self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $source['sha256']);
            self::assertIsString($source['edition']);
            self::assertMatchesRegularExpression('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $source['edition']);
            self::assertStringContainsString('// Fichier d\'origine : ' . $source['fichier'], $content);
            self::assertStringContainsString('//   SHA-256 : ' . $source['sha256'], $content);
        }
    }

    public function testFullRegenerationFromTheInseeFilesGivesTheSameFiles(): void
    {
        $directory = getenv('NAF_SOURCES_INSEE');
        if (!\is_string($directory) || $directory === '') {
            self::markTestSkipped('NAF_SOURCES_INSEE non définie : fichiers de l\'INSEE non disponibles.');
        }

        $files = (new SourceReader($directory))->build();

        self::assertSame(
            ['naf-rev2.php', 'naf-2025.php', 'passage-rev2-vers-naf2025.php', 'passage-naf2025-vers-rev2.php'],
            array_keys($files),
        );
        foreach ($files as $name => $data) {
            self::assertSame(file_get_contents(NafData::DIRECTORY . '/' . $name), DataRenderer::render($data), $name);
        }
    }
}
