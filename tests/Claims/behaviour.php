<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Deck;
use MarkdownPresentation\Document\DeckParser;
use MarkdownPresentation\Render\PresentationRenderer;

/**
 * The rules the pages state about what the libraries do.
 *
 * The tables are checked against the code by `tools/check-reference.php`; what a page
 * says about a rule cannot be, because a rule is a sentence and a sentence is not a name.
 * These are the sentences, and each one is checked against the library that has to
 * behave that way.
 */

it('says that a note has to be the last thing on the slide', function () {
    says('presentation/directives.md', 'a note has to be the last thing on the slide');
});

it('refuses a note that is closed but is not last, and says why', function () {
    $message = Deck::refusal("# One\n\n::note\nHello.\n::\n\n## After\n");

    expect($message)->toContain('last thing on the slide');
});

it('says that attributes are separated by a space', function () {
    says('presentation/directives.md', 'Attributes are separated by a space');
});

it('says how the attributes of a shape are separated when one of them cannot be read', function () {
    $message = Deck::refusal("---\nlayout: blank\n---\n\n::rectangle{fill=red,stroke=blue}\n");

    expect($message)->toContain('separated by a space');
});

it('says that a theme is found beside the deck', function () {
    says('presentation/themes.md', 'beside the deck');
    says('presentation/index.md', 'beside the deck');
});

it('finds a theme that sits beside the deck', function () {
    $root = \sys_get_temp_dir() . '/markdown-office-deck-' . \bin2hex(\random_bytes(6));

    \mkdir($root . '/themes/mine', 0o777, true);
    \file_put_contents($root . '/themes/mine/theme.yml', "name: Mine\n");
    \file_put_contents(
        $root . '/themes/mine/layouts.yml',
        "layouts:\n  title: Title Slide\n  content: Title and Content\n",
    );

    try {
        $deck = (new DeckParser())->parse("---\ntheme: mine\n---\n\n# One\n");

        (new PresentationRenderer($root))
            ->render($deck, (string) \tempnam(\sys_get_temp_dir(), 'deck') . '.pptx');
    } finally {
        \exec(\sprintf('rm -rf %s', \escapeshellarg($root)));
    }

    expect(true)->toBeTrue();
});
