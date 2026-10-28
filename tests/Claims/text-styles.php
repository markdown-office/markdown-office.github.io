<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Deck;
use MarkdownOffice\Documentation\Tests\Support\Document;
use MarkdownOffice\Documentation\Tests\Support\Theme;
use MarkdownPresentation\Theme\ThemeLoader;
use MarkdownWord\Configuration\TextStyles;
use MarkdownWord\WordToMarkdown;
use MarkdownOffice\Documentation\Tests\Support\Site;

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
    says(Site::GENERAL_DIRECTIVES, 'a theme may not name a style `style`');

    $message = Theme::slidesRefusal(
        Theme::DECK,
        "style:\n  color: FF0000\n",
    );

    expect($message)->toContain('reserved word');
});

it('refuses a Word theme that names a style "style"', function () {
    says(Site::GENERAL_DIRECTIVES, 'a theme may not name a style `style`');

    $message = Theme::wordRefusal(
        Theme::DECK,
        "textStyles:\n  style:\n    color: \"FF0000\"\n",
    );

    expect($message)->toContain('cannot be defined');
});

it('refuses the raw form with no attributes in a deck', function () {
    says(Site::GENERAL_DIRECTIVES, 'a style with neither a named style nor a property says nothing');

    $message = Deck::refusal("A :style[Bakery] B\n");

    expect($message)->toContain('which says nothing');
});

it('does not honour a label that holds Markdown', function () {
    says(Site::GENERAL_DIRECTIVES, 'is not honoured as emphasis');

    $message = Deck::refusal("A :style[**Bakery**]{color=00B4D8} B\n");

    expect($message)->toContain('which is Markdown');
});

it('reads the named styles of a theme from the file the page names', function () {
    says(Site::PRESENTATION_THEMES, 'text-styles.yml');

    expect(ThemeLoader::TEXT_STYLES_FILE)->toBe('text-styles.yml');
});

it('refuses a text style the theme does not have, and names the styles it has', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'the message names the styles the theme does have');

    $message = Theme::slidesRefusal(
        "---\ntheme: mine\n---\n\nResilience :bakery[Bakery] is open.\n",
        "brand:\n  color: FF0000\n  bold: true\nmuted:\n  color: accent1\n  size: 10\n",
    );

    expect($message)->toContain('The styles are: brand, muted.');
});

it('refuses an inline directive in a deck that names no style', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'fails the build rather than reaching the slide as text');

    $message = Deck::refusal("Status :green[go] before lunch.\n");

    expect($message)->toContain('which is not a style this theme has');
});

it('says that a theme without named styles has none', function () {
    says(Site::PRESENTATION_THEMES, 'a theme without one has no named styles');

    $message = Theme::slidesRefusal(
        Theme::DECK,
    );

    expect($message)->toContain('The theme has no text styles');
});

it('refuses a highlight in a deck, by name', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'refused by name rather than accepted and quietly dropped');

    $message = Deck::refusal("A :style[Bakery]{highlight=00B4D8} B\n");

    expect($message)->toContain('which the slides half does not draw');
});

it('refuses a label that holds Markdown in a deck', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'a complete Markdown construct in one is refused');

    $message = Deck::refusal("A :style[**Bakery**]{color=00B4D8} B\n");

    expect($message)->toContain('which is Markdown');
});

it('draws a word in a named style, in the colour the theme gives it', function () {
    $slide = Theme::slidesSlide(
        Theme::DECK,
        Theme::BRAND,
    );

    expect(Deck::run($slide, 'Bakery'))->toContain('<a:srgbClr val="FF0000"');
});

it('overrides a named style with the attributes on the directive', function () {
    $slide = Theme::slidesSlide(
        "---\ntheme: mine\n---\n\nA :brand[Bakery]{color=00B4D8} B\n",
        Theme::BRAND,
    );

    expect(Deck::run($slide, 'Bakery'))->toContain('<a:srgbClr val="00B4D8"');
    expect(Deck::run($slide, 'Bakery'))->not->toContain('<a:srgbClr val="FF0000"');
});

it('leaves a property off the run of a deck when an attribute is false', function () {
    $slide = Theme::slidesSlide(
        "---\ntheme: mine\n---\n\nA :brand[Bakery]{bold=false} B\n",
        Theme::BRAND,
    );

    expect(Deck::run($slide, 'Bakery'))->not->toContain('b="1"');
});

it('reads the named styles of a Word theme from the key the page names', function () {
    says('word/configuration.md', '`textStyles`');

    expect(TextStyles::KEY)->toBe('textStyles');
});

it('draws a word in a named style in a Word document', function () {
    says(Site::WORD_DIRECTIVES, 'the named styles come from the theme');

    $body = Document::body("---\ntheme: corporate\n---\n\nResilience :brand[Bakery] is open.\n");

    expect(Document::run($body, 'Bakery'))->not->toBe('');
    expect($body)->not->toContain(':brand[Bakery]');
});

it('overrides a named style with the attributes on the directive in a Word document', function () {
    $body = Document::body("---\ntheme: corporate\n---\n\nA :brand[Bakery]{color=FF0000} B\n");

    expect(Document::run($body, 'Bakery'))->toContain('<w:color w:val="FF0000"/>');
});

it('leaves a property off the run of a Word document when an attribute is false', function () {
    $body = Document::body("---\ntheme: corporate\n---\n\nA :brand[Bakery]{bold=false} B\n");

    expect(Document::run($body, 'Bakery'))->not->toContain('<w:b');
});

it('writes a name the theme does not have as its own source text', function () {
    says(Site::WORD_DIRECTIVES, 'writes the directive out as its own source text');

    $body = Document::body("Resilience :brand[Bakery] is open.\n");

    expect($body)->toContain(':brand[Bakery]');
});

it('writes a label that holds Markdown as the source text of a Word document', function () {
    says(Site::WORD_DIRECTIVES, 'a complete Markdown construct in one is drawn as the source text as well');

    $body = Document::body("---\ntheme: corporate\n---\n\nA :brand[**Bakery**] B\n");

    expect($body)->toContain(':brand[**Bakery**]');
});

it('reads a word in a text style back as the word', function () {
    says(Site::WORD_ROUND_TRIP, 'the style is not, and neither is the name it was written under');

    $path = Document::path("---\ntheme: corporate\n---\n\nResilience :brand[Bakery] is open.\n");
    $markdown = (new WordToMarkdown($path))->convert();

    expect($markdown)->toContain('Bakery');
    expect($markdown)->not->toContain(':brand');
});
