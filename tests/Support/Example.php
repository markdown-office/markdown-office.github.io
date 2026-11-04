<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * An example a page shows, as the Markdown it runs.
 *
 * A page's examples are the code the library is run on, so a claim about one is a claim
 * about what the library does with it. The source is read out of the page rather than
 * written again here, so a page that changes its example changes the claim with it.
 */
final class Example
{
    /**
     * The Markdown of the example a page shows under a given title.
     */
    public static function of(string $page, string $title): string
    {
        $markdown = Site::page($page);

        \preg_match_all(
            '/\{%\s*example\s+"([^"]+)"\s*%\}(.*?)\{%\s*endexample\s*%\}/s',
            $markdown,
            $matches,
            \PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            if ($match[1] !== $title) {
                continue;
            }

            return self::source($match[2], $title);
        }

        throw NoExample::with($title);
    }

    /**
     * The body of a block's fence, without its backticks.
     *
     * The closing fence is a back-reference to the opening one, because a fence is closed
     * by a fence of at least the same length: an example that carries a fence of its own
     * is written with a longer one, and the body ends where that one does.
     */
    private static function source(string $block, string $title): string
    {
        \preg_match('/^(`{3,})[^\n]*\n(.*?)\n\1[ \t]*$/ms', $block, $fence);

        if (! isset($fence[2])) {
            throw NoExample::withoutSource($title);
        }

        return $fence[2];
    }
}
