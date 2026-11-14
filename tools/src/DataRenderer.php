<?php

declare(strict_types=1);

namespace PolygonLab\IdentifiantsEntreprise\Tools;

/**
 * Écrit un fichier de données PHP à partir d'un tableau, toujours de la même façon : le même tableau
 * donne le même fichier, octet pour octet. Le relire avec require puis le réécrire redonne donc le fichier.
 *
 * Le tableau porte ses métadonnées (contenu, page, licence, sources) : l'en-tête du fichier en est déduit.
 */
final class DataRenderer
{
    public const GENERATOR = 'tools/generer-donnees-naf.php';

    private const INDENT = '    ';

    /**
     * @param array<array-key, mixed> $data
     */
    public static function render(array $data): string
    {
        $lines = [
            '<?php',
            '',
            '// Fichier généré par ' . self::GENERATOR . ' : ne pas modifier à la main.',
            '// Pour le régénérer : php ' . self::GENERATOR . ' <dossier des fichiers de l\'INSEE>',
            '//',
            '// Contenu : ' . self::text($data, 'contenu'),
            '// Source : ' . self::text($data, 'page'),
            '// Licence des données : ' . self::text($data, 'licence'),
        ];

        $sources = $data['sources'] ?? null;
        if (!\is_array($sources) || $sources === []) {
            throw new \InvalidArgumentException('Métadonnée « sources » absente.');
        }
        foreach ($sources as $source) {
            if (!\is_array($source)) {
                throw new \InvalidArgumentException('Métadonnée « sources » mal formée.');
            }
            $lines[] = '//';
            $lines[] = '// Fichier d\'origine : ' . self::text($source, 'fichier');
            $lines[] = '//   SHA-256 : ' . self::text($source, 'sha256');
            $lines[] = '//   Édition : ' . self::text($source, 'edition') . ' (date d\'enregistrement inscrite dans le fichier)';
            $title = $source['titre'] ?? null;
            if (\is_string($title) && $title !== '') {
                $lines[] = '//   Titre : ' . $title;
            }
        }

        $lines[] = '';
        $lines[] = 'declare(strict_types=1);';
        $lines[] = '';
        $lines[] = 'return ' . self::export($data, 0) . ';';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || $value === '' || str_contains($value, "\n")) {
            throw new \InvalidArgumentException(sprintf('Métadonnée « %s » absente ou sur plusieurs lignes.', $key));
        }

        return $value;
    }

    private static function export(mixed $value, int $depth): string
    {
        if ($value === null) {
            return 'null';
        }
        if (\is_string($value)) {
            return '\'' . strtr($value, ['\\' => '\\\\', '\'' => '\\\'']) . '\'';
        }
        if (\is_int($value)) {
            return (string) $value;
        }
        if (!\is_array($value)) {
            throw new \InvalidArgumentException('Type de valeur non pris en charge : ' . get_debug_type($value));
        }
        if ($value === []) {
            return '[]';
        }

        $isList = array_is_list($value);
        $scalars = array_filter($value, static fn(mixed $item): bool => !\is_array($item)) === $value;
        if ($isList && $scalars) {
            return '[' . implode(', ', array_map(static fn(mixed $item): string => self::export($item, $depth), $value)) . ']';
        }

        $indent = str_repeat(self::INDENT, $depth + 1);
        $out = "[\n";
        foreach ($value as $key => $item) {
            $out .= $indent . ($isList ? '' : self::export((string) $key, $depth) . ' => ') . self::export($item, $depth + 1) . ",\n";
        }

        return $out . str_repeat(self::INDENT, $depth) . ']';
    }
}
