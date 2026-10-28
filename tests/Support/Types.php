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
        $listed = self::listed($page, $heading);

        if ($listed === []) {
            throw NoTypesListed::on($page);
        }

        foreach ($listed as $type) {
            expect($directive($type))->not->toContain($marker);
        }

        return $listed;
    }

    /**
     * The types a page names, in the paragraph that names them.
     *
     * @return list<string>
     */
    public static function listed(string $page, string $heading): array
    {
        return self::listedIn(Site::paragraphs($page, $heading));
    }

    /**
     * The types the paragraphs name, in the paragraph that names them.
     *
     * The paragraph rather than the sentence: a list that goes on after the first full
     * stop is the list the page names all the same, and a sentence that ends at the
     * first period is how a type nobody draws slips in unlooked-at.
     *
     * @param list<string> $paragraphs
     *
     * @return list<string>
     */
    public static function listedIn(array $paragraphs): array
    {
        foreach ($paragraphs as $paragraph) {
            if (\str_contains($paragraph, 'The type is one of')) {
                return \array_values(\array_unique(Pages::codeIn($paragraph)));
            }
        }

        return [];
    }
}
