<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Deck;
use MarkdownOffice\Documentation\Tests\Support\Document;
use MarkdownOffice\Documentation\Tests\Support\Theme;
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

/**
 * The refusal messages a page quotes, as the page writes them.
 *
 * @return list<string>
 */
function quotedRefusals(string $page): array
{
    \preg_match_all('/^> (.*)$/m', Site::page($page), $matches);

    return $matches[1];
}

it('refuses a theme that names a style "style"', function () {
    says(Site::GENERAL_DIRECTIVES, 'a theme may not name a style `style`');

    $message = Theme::slidesRefusal(
        Theme::DECK,
        "textStyles:\n  style:\n    color: FF0000\n",
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

it('reads the named styles of a theme from the section the page names', function () {
    says(Site::PRESENTATION_THEMES, 'The `textStyles:` section is optional');

    $message = Theme::slidesRefusal(
        Theme::DECK,
    );

    expect($message)->toContain('a theme names them in its "textStyles:" section');
});

it('refuses each file of the old theme format, and says where the content goes', function () {
    says(Site::PRESENTATION_THEMES, 'move its content into the "layouts:" section of its "theme.yml"');
    says(Site::PRESENTATION_THEMES, 'move its content into the "textStyles:" section of its "theme.yml"');
    says(Site::PRESENTATION_THEMES, 'That file is the Word half\'s, and this half does not read it');

    // The whole message and not a piece of it: the message the library used to print is
    // a prefix of the one it prints now, so a check for the prefix passes for both.
    expect(Theme::slidesRetired(Theme::DECK, 'layouts.yml', "layouts:\n  content: Title and Content\n"))
        ->toBe('The theme "mine" has a "layouts.yml" beside it. A theme is one file: move its content into the "layouts:" section of its "theme.yml", and delete the file.');

    expect(Theme::slidesRetired(Theme::DECK, 'text-styles.yml', "brand:\n  color: FF0000\n"))
        ->toBe('The theme "mine" has a "text-styles.yml" beside it. A theme is one file: move its content into the "textStyles:" section of its "theme.yml", and delete the file.');

    expect(Theme::slidesRetired(Theme::DECK, 'styles.yml', "heading.1:\n  name: Georgia\n"))
        ->toBe('The theme "mine" has a "styles.yml" beside it. That file is the Word half\'s, and this half does not read it: its slots name Word styles, which a deck of slides has no use for. Delete it, and the theme is read again.');
});

it('refuses the file the Word half\'s slots used to be in, and says where they go', function () {
    says('word/configuration.md', 'A `styles.yml` beside the theme is refused');

    expect(Theme::wordRetired(
        "---\ntheme: mine\n---\n\n# One\n",
        'styles.yml',
        "heading.1:\n  name: Georgia\n",
    ))->toMatch(
        '/^"[^"]*\/styles\.yml" is not read any more\. A theme\'s styles are in the "styles:" section of '
        . 'its "theme\.yml", and its named text styles in the "textStyles:" section\. Delete the file, '
        . 'and the theme is read again\.$/',
    );
});

it('quotes the refusals as the library prints them', function () {
    $themes = quotedRefusals(Site::PRESENTATION_THEMES);

    expect($themes)->toHaveCount(3);

    // The page writes the theme's name as "acme", and the library writes the name the
    // deck named: the same sentence with another name in it.
    expect(\str_replace('"mine"', '"acme"', Theme::slidesRetired(
        Theme::DECK,
        'layouts.yml',
        "layouts:\n  content: Title and Content\n",
    )))->toBe($themes[0]);

    expect(\str_replace('"mine"', '"acme"', Theme::slidesRetired(
        Theme::DECK,
        'text-styles.yml',
        "brand:\n  color: FF0000\n",
    )))->toBe($themes[1]);

    expect(\str_replace('"mine"', '"acme"', Theme::slidesRetired(
        Theme::DECK,
        'styles.yml',
        "heading.1:\n  name: Georgia\n",
    )))->toBe($themes[2]);

    $configuration = quotedRefusals('word/configuration.md');

    expect($configuration)->toHaveCount(1);

    // The page writes the path the library prints as an ellipsis, because the path is
    // where the file happened to be and the sentence is about the file.
    expect((string) \preg_replace(
        '/^"[^"]*"/',
        '"…/styles.yml"',
        Theme::wordRetired("---\ntheme: mine\n---\n\n# One\n", 'styles.yml', "heading.1:\n  name: Georgia\n"),
    ))->toBe($configuration[0]);
});

it('refuses a text style the theme does not have, and names the styles it has', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'the message names the styles the theme does have');

    $message = Theme::slidesRefusal(
        "---\ntheme: mine\n---\n\nResilience :bakery[Bakery] is open.\n",
        "textStyles:\n  brand:\n    color: FF0000\n    bold: true\n  muted:\n    color: accent1\n    size: 10\n",
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

it('reads the named styles of a Word theme from the section the page names', function () {
    says('word/configuration.md', '`textStyles:`');

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
