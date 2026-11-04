<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Site;

/**
 * Assert that a page says a phrase, whatever case it is written in.
 *
 * The case is folded because the phrase a page states often begins a sentence, and the
 * word that begins it is capitalised: a test that looked for "five themes ship" in a
 * page that says "Five themes ship" would be guarding a spelling rather than a claim.
 */
function says(string $path, string $phrase): void
{
    saysIn(Site::page($path), $phrase);
}

/**
 * Assert that a piece of text says a phrase, whatever case it is written in.
 *
 * The text rather than a page, for the files that are not pages of the site: the skill's
 * own file is one of them, and the bundle's copy of it is made by the build.
 */
function saysIn(string $text, string $phrase): void
{
    $found = \str_contains(
        \mb_strtolower(Site::flat($text)),
        \mb_strtolower($phrase),
    );

    expect([$phrase => $found])->toBe([$phrase => true]);
}

/**
 * A number as the English word a page writes it in.
 */
function words(int $number): string
{
    return Site::words($number);
}
