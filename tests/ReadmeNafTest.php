<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tests;

use PHPUnit\Framework\TestCase;

/**
 * L'exemple de la partie « NAF 2025 et bascule du 1er janvier 2027 » du README est le script
 * examples/naf-2025.php, et produit la sortie annoncée.
 */
final class ReadmeNafTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    private const EXAMPLE = self::ROOT . '/examples/naf-2025.php';

    private const SECTION = "### NAF 2025 et bascule du 1er janvier 2027\n";

    public function testNafExampleIsTheExampleScript(): void
    {
        $example = str_replace(
            "require __DIR__ . '/../vendor/autoload.php';",
            "require __DIR__ . '/vendor/autoload.php';",
            self::read(self::EXAMPLE),
        );

        self::assertSame(rtrim($example, "\n"), self::block('php'));
    }

    public function testNafExampleOutputIsTheExpectedOutput(): void
    {
        ob_start();
        try {
            require self::EXAMPLE;
        } finally {
            $output = (string) ob_get_clean();
        }

        self::assertSame(self::block('text'), rtrim($output, "\n"));
    }

    public function testReadmeNoLongerClaimsThatTheNafTableIsMissing(): void
    {
        $readme = self::read(self::ROOT . '/README.md');

        self::assertStringNotContainsString('n\'embarque pas la table', $readme);
        self::assertStringNotContainsString('NAF 2025 n\'est pas encore prise en charge', $readme);
    }

    /**
     * Premier bloc de code du langage donné dans la partie NAF 2025.
     */
    private static function block(string $language): string
    {
        $readme = self::read(self::ROOT . '/README.md');
        $start = strpos($readme, self::SECTION);
        self::assertNotFalse($start, 'partie « NAF 2025 et bascule du 1er janvier 2027 » absente du README');

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
