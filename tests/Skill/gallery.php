<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Bundle\Gallery;
use MarkdownOffice\Documentation\Tests\Support\Bundle;
use MarkdownOffice\Documentation\Tests\Support\Site;

/**
 * The gallery the bundle carries.
 *
 * The bundle copies the two halves' own galleries from the libraries, and a copy that has
 * fallen behind is a copy that is wrong: it shows a construct the library no longer draws,
 * or misses one it does. Nothing in the build can catch that — the copy is made from
 * whatever is beside this repository — so it is caught here, against the libraries.
 */

/**
 * The paths a piece of prose names, as the bundle holds them.
 *
 * The same shape the checker looks for: a path in code font, naming something of the
 * bundle. A path in running text is prose about a path rather than a path.
 *
 * @return list<string>
 */
function pathsNamed(string $text): array
{
    \preg_match_all('/`((?:examples|references|scripts)\/[^`]+)`/', $text, $found);

    return $found[1];
}

it('carries the gallery each library ships, byte for byte', function () {
    $problems = Gallery::differences(Site::root(), Bundle::built());

    expect($problems)->toBe([]);
});

it('holds every path the prose names', function () {
    $bundle = Bundle::built();

    $named = \array_merge(
        pathsNamed(Site::skill()),
        pathsNamed(Site::page('skills/index.md')),
    );

    expect($named)->not->toBeEmpty();

    foreach ($named as $path) {
        expect(\file_exists($bundle . '/' . $path))->toBeTrue();
    }
});
