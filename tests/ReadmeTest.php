<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Le démarrage rapide du README doit être le script d'exemple, et produire la sortie annoncée.
 */
final class ReadmeTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    private const EXAMPLE = self::ROOT . '/examples/demarrage-rapide.php';

    public function testQuickStartCodeIsTheExampleScript(): void
    {
        $example = str_replace(
            "require __DIR__ . '/../vendor/autoload.php';",
            "require __DIR__ . '/vendor/autoload.php';",
            self::read(self::EXAMPLE),
        );

        self::assertSame(rtrim($example, "\n"), self::block('php'));
    }

    public function testQuickStartOutputIsTheExpectedOutput(): void
    {
        ob_start();
        try {
            require self::EXAMPLE;
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertSame(self::block('text'), rtrim($output, "\n"));
    }

    /**
     * Premier bloc de code du langage donné dans la section « Démarrage rapide ».
     */
    private static function block(string $language): string
    {
        $readme = self::read(self::ROOT . '/README.md');
        $start = strpos($readme, "## Démarrage rapide\n");
        self::assertNotFalse($start, 'section « Démarrage rapide » absente du README');

        $pattern = '/```' . preg_quote($language, '/') . '\n(.*?)\n```/s';
        self::assertSame(1, preg_match($pattern, substr($readme, $start), $matches), 'bloc ' . $language . ' absent');

        return $matches[1];
    }

    private static function read(string $path): string
    {
        $content = file_get_contents($path);
        self::assertIsString($content, 'fichier illisible : ' . $path);

        return $content;
    }
}
