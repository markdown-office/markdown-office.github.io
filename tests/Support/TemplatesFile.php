<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * A deck's own directory, and the templates file beside it.
 *
 * A template may be imported from a file beside the deck, so a claim about one needs a
 * deck's directory to put the file in — and the deck has to be rendered from there, or the
 * path is resolved against somewhere else.
 */
final class TemplatesFile
{
    /**
     * A directory holding a templates file, and the directory it is in.
     */
    public static function beside(string $markdown): string
    {
        $root = Directory::make('templates');

        \file_put_contents($root . '/templates.md', $markdown);

        return $root;
    }

    /**
     * The shapes of a deck drawn against a templates file.
     *
     * The templates file is written beside the deck and taken away again, whatever the deck
     * says: a file left behind is a file the next test draws against.
     *
     * @return list<list<string>>
     */
    public static function shapes(string $markdown, string $templates): array
    {
        return Directory::during(
            self::beside($templates),
            static fn (string $root): array => Deck::shapes($markdown, $root),
        );
    }
}
