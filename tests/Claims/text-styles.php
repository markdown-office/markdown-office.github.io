<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Deck;
use MarkdownOffice\Documentation\Tests\Support\Document;
use MarkdownOffice\Documentation\Tests\Support\Theme;

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
