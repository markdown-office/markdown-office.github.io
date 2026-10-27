<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Deck;
use MarkdownOffice\Documentation\Tests\Support\Document;
use MarkdownOffice\Documentation\Tests\Support\Theme;
use MarkdownPresentation\Theme\ThemeLoader;

/**
 * The claims the pages make about the inline styles.
 *
 * The eight attributes are a vocabulary, and are checked against the code by
 * `tools/check-reference.php` — which is also what holds the two halves to the same set.
 * What a page says about a rule cannot be checked that way, because a rule is a sentence
 * and a sentence is not a name. These are the sentences, and each one is checked against
 * the library that has to behave that way.
 */

it('refuses a theme that names a style "style"', function () {
    says('general/directives.md', 'a theme may not name a style `style`');

    $root = Theme::slides("style:\n  color: FF0000\n");

    try {
        $message = Deck::refusal("---\ntheme: mine\n---\n\nResilience :brand[Bakery] is open.\n", $root);
    } finally {
        Theme::remove($root);
    }

    expect($message)->toContain('reserved word');
});

it('refuses a Word theme that names a style "style"', function () {
    says('general/directives.md', 'a theme may not name a style `style`');

    $root = Theme::word("textStyles:\n  style:\n    color: \"FF0000\"\n");

    try {
        $message = Document::refusal("---\ntheme: mine\n---\n\nResilience :brand[Bakery] is open.\n", $root);
    } finally {
        Theme::remove($root);
    }

    expect($message)->toContain('cannot be defined');
});

it('refuses the raw form with no attributes in a deck', function () {
    says('general/directives.md', 'a style with neither a named style nor a property says nothing');

    $message = Deck::refusal("A :style[Bakery] B\n");

    expect($message)->toContain('which says nothing');
});

it('does not honour a label that holds Markdown', function () {
    says('general/directives.md', 'is not honoured as emphasis');

    $message = Deck::refusal("A :style[**Bakery**]{color=00B4D8} B\n");

    expect($message)->toContain('which is Markdown');
});

it('reads the named styles of a theme from the file the page names', function () {
    says('presentation/themes.md', 'text-styles.yml');

    expect(ThemeLoader::TEXT_STYLES_FILE)->toBe('text-styles.yml');
});

it('refuses a text style the theme does not have, and names the styles it has', function () {
    says('presentation/directives.md', 'the message names the styles the theme does have');

    $root = Theme::slides("brand:\n  color: FF0000\n  bold: true\nmuted:\n  color: accent1\n  size: 10\n");

    try {
        $message = Deck::refusal("---\ntheme: mine\n---\n\nResilience :bakery[Bakery] is open.\n", $root);
    } finally {
        Theme::remove($root);
    }

    expect($message)->toContain('The styles are: brand, muted.');
});

it('refuses an inline directive in a deck that names no style', function () {
    says('presentation/directives.md', 'fails the build rather than reaching the slide as text');

    $message = Deck::refusal("Status :green[go] before lunch.\n");

    expect($message)->toContain('which is not a style this theme has');
});

it('says that a theme without named styles has none', function () {
    says('presentation/themes.md', 'a theme without one has no named styles');

    $root = Theme::slides();

    try {
        $message = Deck::refusal("---\ntheme: mine\n---\n\nResilience :brand[Bakery] is open.\n", $root);
    } finally {
        Theme::remove($root);
    }

    expect($message)->toContain('The theme has no text styles');
});

it('refuses a highlight in a deck, by name', function () {
    says('presentation/directives.md', 'refused by name rather than accepted and quietly dropped');

    $message = Deck::refusal("A :style[Bakery]{highlight=00B4D8} B\n");

    expect($message)->toContain('which the slides half does not draw');
});

it('refuses a label that holds Markdown in a deck', function () {
    says('presentation/directives.md', 'a complete Markdown construct in one is refused');

    $message = Deck::refusal("A :style[**Bakery**]{color=00B4D8} B\n");

    expect($message)->toContain('which is Markdown');
});

it('draws a word in a named style, in the colour the theme gives it', function () {
    $root = Theme::slides("brand:\n  color: FF0000\n  bold: true\n");

    try {
        $slide = Deck::slide("---\ntheme: mine\n---\n\nResilience :brand[Bakery] is open.\n", $root);
    } finally {
        Theme::remove($root);
    }

    expect(Deck::run($slide, 'Bakery'))->toContain('<a:srgbClr val="FF0000"');
});

it('overrides a named style with the attributes on the directive', function () {
    $root = Theme::slides("brand:\n  color: FF0000\n  bold: true\n");

    try {
        $slide = Deck::slide("---\ntheme: mine\n---\n\nA :brand[Bakery]{color=00B4D8} B\n", $root);
    } finally {
        Theme::remove($root);
    }

    expect(Deck::run($slide, 'Bakery'))->toContain('<a:srgbClr val="00B4D8"');
    expect(Deck::run($slide, 'Bakery'))->not->toContain('<a:srgbClr val="FF0000"');
});

it('leaves a property off the run of a deck when an attribute is false', function () {
    $root = Theme::slides("brand:\n  color: FF0000\n  bold: true\n");

    try {
        $slide = Deck::slide("---\ntheme: mine\n---\n\nA :brand[Bakery]{bold=false} B\n", $root);
    } finally {
        Theme::remove($root);
    }

    expect(Deck::run($slide, 'Bakery'))->not->toContain('b="1"');
});
