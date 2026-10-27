<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Reference;

/**
 * The pages of the site, and the blocks of them that carry a vocabulary.
 *
 * A page says which of its blocks is the list of what by marking it:
 *
 *     <!-- reference: presentation.layout-roles -->
 *
 * The marker is an HTML comment, so it survives Markdown untouched and says nothing to a
 * reader. It is a name rather than a shape, because the pages are hand-written and a
 * table whose first column happens to hold names is not a table the code can be checked
 * against: the frontmatter keys and the exceptions are both two-column tables, and only
 * the page knows which is which. A marker also gives every vocabulary an address, so a
 * vocabulary no page carries is a failure of its own rather than a silence.
 */
final class Pages
{
    /** The marker that names the vocabulary a block carries. */
    private const MARKER = '/^<!--\s*reference:\s*([a-z0-9]+(?:-[a-z0-9]+)*(?:\.[a-z0-9]+(?:-[a-z0-9]+)*)*)\s*-->$/';

    /**
     * Every block under a directory: a table or a fenced list, with the names in it.
     *
     * @return list<Block>
     */
    public static function blocks(string $directory): array
    {
        $blocks = [];

        foreach (self::files($directory) as $file) {
            $lines = \explode("\n", (string) \file_get_contents($file));

            for ($number = 0; $number < \count($lines); $number++) {
                $line = \trim($lines[$number]);

                if ($line === '' || \preg_match(self::MARKER, $line) === 1) {
                    continue;
                }

                if (\str_starts_with($line, '|')) {
                    $table = self::table($lines, $number);
                    $blocks[] = new Block($file, $number + 1, $table['names'], $table['values']);

                    continue;
                }

                if (\str_starts_with($line, '```')) {
                    $blocks[] = new Block($file, $number + 1, self::fence($lines, $number));
                }
            }
        }

        return $blocks;
    }

    /**
     * The blocks that carry a vocabulary, keyed by the vocabulary they carry.
     *
     * A vocabulary with two blocks is two homes for one list, and both of them are about
     * to drift: the caller is told about it rather than being handed one of them.
     *
     * @return array<string, list<Block>>
     */
    public static function marked(string $directory): array
    {
        $marked = [];

        foreach (self::files($directory) as $file) {
            $lines = \explode("\n", (string) \file_get_contents($file));

            for ($number = 0; $number < \count($lines); $number++) {
                if (\preg_match(self::MARKER, \trim($lines[$number]), $match) !== 1) {
                    continue;
                }

                $block = self::blockAfter($lines, $number, $file);

                $marked[$match[1]][] = $block ?? new Block($file, $number + 1, [], []);
            }
        }

        \ksort($marked);

        return $marked;
    }

    /**
     * The block that follows a marker, and the names in it.
     *
     * Two shapes are read: a table, whose names are the backticked words in the first
     * cell of each row, and a fenced list, whose names are the words in it. A page
     * carries whichever of the two fits what it has to say.
     *
     * Another marker is stepped over rather than ending the search, so that a marker
     * written twice is two homes for one list rather than one home and a silence.
     *
     * @param list<string> $lines
     */
    private static function blockAfter(array $lines, int $number, string $file): ?Block
    {
        for ($next = $number + 1; $next < \count($lines); $next++) {
            $trimmed = \trim($lines[$next]);

            if ($trimmed === '') {
                continue;
            }

            if (\preg_match(self::MARKER, $trimmed) === 1) {
                continue;
            }

            if (\str_starts_with($trimmed, '|')) {
                $table = self::table($lines, $next);

                return new Block($file, $next + 1, $table['names'], $table['values']);
            }

            if (\str_starts_with($trimmed, '```')) {
                return new Block($file, $next + 1, self::fence($lines, $next));
            }

            return null;
        }

        return null;
    }

    /**
     * The names of a table: the backticked words in the first cell of each of its rows.
     *
     * The second cell is kept as well, because a table of ceilings is a table of names
     * and numbers, and the number is as much a claim as the name is.
     *
     * @param list<string> $lines
     *
     * @return array{names: list<string>, values: array<string, string>}
     */
    private static function table(array $lines, int $number): array
    {
        $names = [];
        $values = [];

        for ($next = $number; $next < \count($lines); $next++) {
            $line = \trim($lines[$next]);

            if (!\str_starts_with($line, '|')) {
                break;
            }

            $cells = self::cells($line);

            // The header and the rule under it are not rows.
            if (\count($cells) < 2) {
                continue;
            }

            if ($next === $number + 1) {
                continue;
            }

            foreach (self::codeIn($cells[0]) as $name) {
                $names[] = $name;
                $values[$name] = $cells[1];
            }
        }

        \sort($names);

        return ['names' => \array_values(\array_unique($names)), 'values' => $values];
    }

    /**
     * The names of a fenced list: the words in it.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private static function fence(array $lines, int $number): array
    {
        $names = [];

        for ($next = $number + 1; $next < \count($lines); $next++) {
            $line = \trim($lines[$next]);

            if (\str_starts_with($line, '```')) {
                break;
            }

            foreach (\preg_split('/\s+/', $line) ?: [] as $word) {
                if ($word !== '') {
                    $names[] = $word;
                }
            }
        }

        \sort($names);

        return \array_values(\array_unique($names));
    }

    /**
     * The cells of a table row.
     *
     * @return list<string>
     */
    private static function cells(string $line): array
    {
        return \array_map('trim', \explode('|', \trim($line, " \t|")));
    }

    /**
     * The words in a cell that are written in code font.
     *
     * A name is a name in code font, and a cell that carries prose carries no name — so
     * the code font is what tells a name from a description.
     *
     * @return list<string>
     */
    private static function codeIn(string $cell): array
    {
        \preg_match_all('/`([^`]+)`/', $cell, $matches);

        return $matches[1];
    }

    /**
     * The Markdown files under a directory.
     *
     * @return list<string>
     */
    private static function files(string $directory): array
    {
        if (!\is_dir($directory)) {
            return [];
        }

        $found = [];

        foreach (\glob($directory . '/**/*.md') ?: [] as $path) {
            $found[] = $path;
        }

        foreach (\glob($directory . '/*.md') ?: [] as $path) {
            $found[] = $path;
        }

        \sort($found);

        return \array_values(\array_unique($found));
    }
}
