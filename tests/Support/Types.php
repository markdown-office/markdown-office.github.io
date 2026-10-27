<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

use MarkdownOffice\Documentation\Reference\Pages;

/**
 * The types a page lists for a directive, and the ones the library draws.
 *
 * A page that says a directive takes one of these types is making a claim about a closed
 * set, and the set is the library's: a type the page lists is drawn, and a type it does
 * not list is written out as the directive's own source text.
 */
final class Types
{
    /**
     * The types a page lists, each of them checked to be drawn.
     *
     * A page that lists no type at all is a failure here rather than an empty answer: a
     * check that passes because it found nothing to check is worse than no check at all.
     *
     * @param callable(string): string $directive The document a type is drawn in.
     *
     * @return list<string>
     */
    public static function drawn(string $page, string $heading, string $marker, callable $directive): array
    {
        \preg_match('/The type is one of (.+?)\./', Site::section($page, $heading), $match);

        $listed = Pages::codeIn($match[1] ?? '');

        if ($listed === []) {
            throw NoTypesListed::on($page);
        }

        foreach ($listed as $type) {
            expect($directive($type))->not->toContain($marker);
        }

        return $listed;
    }
}
