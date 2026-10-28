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
     * The blocks of the pages under a directory, and the ones that carry a vocabulary.
     *
     * One walk for both, because a check that reads the pages twice reads two copies of
     * the site and compares them.
     *
     * A vocabulary with two blocks is two homes for one list, and both of them are about
     * to drift: the caller is told about it rather than being handed one of them.
     *
     * @return array{blocks: list<Block>, marked: array<string, list<Block>>}
     */
    public static function of(string $directory): array
    {
        $blocks = [];
        $marked = [];

        foreach (self::files($directory) as $file) {
            $walk = self::walk($file, \explode("\n", (string) \file_get_contents($file)));

            $blocks = [...$blocks, ...$walk['blocks']];

            foreach ($walk['markers'] as $marker) {
                $marked[$marker['name']][] = $marker['block'];
            }
        }

        \ksort($marked);

        return ['blocks' => $blocks, 'marked' => $marked];
    }

    /**
     * The blocks of a file and the markers that name the vocabulary they carry.
     *
     * @param list<string> $lines
     *
     * @return array{
     *     blocks: list<Block>,
     *     markers: list<array{name: string, block: Block}>
     * }
     */
    private static function walk(string $file, array $lines): array
    {
        $blocks = [];
        $markers = [];

        for ($number = 0; $number < \count($lines); $number++) {
            $line = \trim($lines[$number]);

            if (\preg_match(self::MARKER, $line, $match) === 1) {
                $markers[] = [
                    'name' => $match[1],
                    'block' => self::blockAfter($file, $lines, $number),
                ];

                continue;
            }

            if ($line === '') {
                continue;
            }

            $found = self::blockAt($file, $lines, $number);

            if ($found === null) {
                continue;
            }

            $blocks[] = $found['block'];

            // Past the block, so that its rows are not read as blocks of their own: a
            // row of a table is not a second copy of the list.
            $number = $found['end'];
        }

        return ['blocks' => $blocks, 'markers' => $markers];
    }

    /**
     * The block that starts at a line, if one does, and the line it ends at.
     *
     * A table and a fenced list are the two shapes a page carries a vocabulary in, and
     * both of them are read here, so that a page cannot grow a third.
     *
     * @param list<string> $lines
     *
     * @return array{block: Block, end: int}|null
     */
    private static function blockAt(string $file, array $lines, int $number): ?array
    {
        $line = \trim($lines[$number]);

        if (\str_starts_with($line, '|')) {
            $table = self::table($lines, $number);

            return [
                'block' => new Block($file, $number + 1, $table['names'], $table['values']),
                'end' => $table['end'],
            ];
        }

        if (\str_starts_with($line, '```')) {
            $fence = self::fence($lines, $number);

            return [
                'block' => new Block($file, $number + 1, $fence['names'], []),
                'end' => $fence['end'],
            ];
        }

        return null;
    }

    /**
     * The block that follows a marker.
     *
     * Another marker is stepped over rather than ending the search, so that a marker
     * written twice is two homes for one list rather than one home and a silence.
     *
     * @param list<string> $lines
     */
    private static function blockAfter(string $file, array $lines, int $number): Block
    {
        for ($next = $number + 1; $next < \count($lines); $next++) {
            $trimmed = \trim($lines[$next]);

            if ($trimmed === '' || \preg_match(self::MARKER, $trimmed) === 1) {
                continue;
            }

            $found = self::blockAt($file, $lines, $next);

            if ($found === null) {
                break;
            }

            return $found['block'];
        }

        return new Block($file, $number + 1, [], []);
    }

    /**
     * The names of a table: the backticked words in the first cell of each of its rows.
     *
     * The second cell is kept as well, because a table of ceilings is a table of names
     * and numbers, and the number is as much a claim as the name is.
     *
     * @param list<string> $lines
     *
     * @return array{names: list<string>, values: array<string, string>, end: int}
     */
    private static function table(array $lines, int $number): array
    {
        $names = [];
        $values = [];
        $block = self::blockLines($lines, $number, static fn (string $line): bool => !\str_starts_with($line, '|'));

        foreach ($block['lines'] as $offset => $line) {
            $cells = self::cells($line);

            // The header and the rule under it are not rows.
            if (\count($cells) < 2 || $offset === 1) {
                continue;
            }

            foreach (self::codeIn($cells[0]) as $name) {
                $names[] = $name;
                $values[$name] = $cells[1];
            }
        }

        \sort($names);

        return [
            'names' => \array_values(\array_unique($names)),
            'values' => $values,
            'end' => $block['end'],
        ];
    }

    /**
     * The names of a fenced list: the words in it.
     *
     * @param list<string> $lines
     *
     * @return array{names: list<string>, end: int}
     */
    private static function fence(array $lines, int $number): array
    {
        $names = [];
        $block = self::blockLines($lines, $number + 1, static fn (string $line): bool => \str_starts_with($line, '```'));

        foreach ($block['lines'] as $line) {
            foreach (\preg_split('/\s+/', $line) ?: [] as $word) {
                if ($word !== '') {
                    $names[] = $word;
                }
            }
        }

        \sort($names);

        return ['names' => \array_values(\array_unique($names)), 'end' => $block['end']];
    }

    /**
     * The lines of a block, from the line it starts at, and the line it ends at.
     *
     * @param list<string>          $lines
     * @param callable(string): bool $ends Whether a line ends the block.
     *
     * @return array{lines: list<string>, end: int}
     */
    private static function blockLines(array $lines, int $number, callable $ends): array
    {
        $found = [];

        for ($next = $number; $next < \count($lines); $next++) {
            $line = \trim($lines[$next]);

            if ($ends($line)) {
                return ['lines' => $found, 'end' => $next];
            }

            $found[] = $line;
        }

        return ['lines' => $found, 'end' => \count($lines)];
    }

    /**
     * The cells of a table row.
     *
     * @return list<string>
     */
    public static function cells(string $line): array
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
    public static function codeIn(string $cell): array
    {
        \preg_match_all('/`([^`]+)`/', $cell, $matches);

        return $matches[1];
    }

    /**
     * The files of a directory, of the kinds asked for.
     *
     * The extension is a parameter because the pages and the templates they are built
     * from are read by different checks, and both are the same walk over one directory.
     *
     * @param list<string> $extensions
     *
     * @return list<string>
     */
    public static function files(string $directory, array $extensions = ['md']): array
    {
        if (!\is_dir($directory)) {
            return [];
        }

        $found = [];

        foreach ($extensions as $extension) {
            foreach (['/**/*.', '/*.'] as $pattern) {
                foreach (\glob($directory . $pattern . $extension) ?: [] as $path) {
                    $found[] = $path;
                }
            }
        }

        \sort($found);

        return \array_values(\array_unique($found));
    }
}
