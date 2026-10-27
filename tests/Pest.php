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
    $found = \str_contains(
        \mb_strtolower(Site::flat(Site::page($path))),
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
