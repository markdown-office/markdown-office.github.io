<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Bundle\Gallery;
use MarkdownOffice\Documentation\Tests\Support\Site;

/**
 * The claims the skill's page makes about what the bundle carries.
 *
 * The bundle is a copy of this site's documentation, and its own file says what it holds.
 * The two are read by an agent instead of by a person, so a sentence that names the wrong
 * directory sends it to a file that is not there.
 */

/**
 * The phrase a page used when the bundle carried only one half's gallery.
 *
 * It is here so that it cannot come back: the bundle carries both halves' galleries now,
 * and a page that says the slides half's is missing is a page that lies about the bundle.
 */
const MISSING_GALLERY = 'gallery of decks is not in the bundle';

it('says the bundle carries both halves\' galleries', function () {
    says('skills/index.md', 'the two halves\' own galleries');

    foreach (Gallery::paths(Site::root()) as $path) {
        says('skills/index.md', $path);
    }
});

it('does not say the slides half\'s gallery is missing', function () {
    expect(Site::flat(Site::page('skills/index.md')))->not->toContain(MISSING_GALLERY);
    expect(Site::flat(Site::skill()))->not->toContain(MISSING_GALLERY);
});

it('says about the galleries what the site says', function () {
    $skill = Site::flat(Site::skill());
    $page = Site::flat(Site::page('skills/index.md'));

    $paths = \array_values(Gallery::paths(Site::root()));

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        expect($skill)->toContain($path);
        expect($page)->toContain($path);
    }

    expect($skill)->toContain('the two halves\' own galleries');
    expect($page)->toContain('the two halves\' own galleries');
});
