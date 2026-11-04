<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Deck;
use MarkdownOffice\Documentation\Tests\Support\Directory;
use MarkdownOffice\Documentation\Tests\Support\Site;
use MarkdownOffice\Documentation\Tests\Support\Theme;

/**
 * The claims the pages make about the slides half's themes.
 *
 * A theme is one file with a look in it, and the pages say what may be written in that
 * file, what a deck may lay over it, what a master does with the roles it covers and
 * where a theme is looked for. Each of those is a sentence, so none of them can be
 * checked against the code by `tools/check-reference.php`: they are checked here, against
 * the library that has to behave that way.
 */

/**
 * The boxes the shapes of a slide are drawn at.
 *
 * The boxes rather than the whole of the slide, because a claim about geometry is a claim
 * about where the shapes are put and not about every other thing the file holds.
 *
 * @return list<string>
 */
function slideBoxes(string $slide): array
{
    \preg_match_all(
        '#<a:xfrm[^>]*>\s*<a:off x="(-?\d+)" y="(-?\d+)"/>\s*<a:ext cx="(\d+)" cy="(\d+)"/>#s',
        $slide,
        $matches,
        \PREG_SET_ORDER,
    );

    return \array_map(
        static fn (array $match): string => \implode(',', \array_slice($match, 1)),
        $matches,
    );
}

it('says that a theme\'s colours must be quoted, and refuses one that is not', function () {
    says(Site::PRESENTATION_THEMES, 'Every colour is quoted');
    says(Site::PRESENTATION_THEMES, 'YAML reads an unquoted `#` as the start of a comment');

    $deck = "---\ntheme: mine\n---\n\n# One\n\nText.\n";

    $message = Theme::slidesRefusal(
        $deck,
        "colors:\n  dk1: #1E1E2E\n",
    );

    expect($message)->toContain('is not a colour such as "#1E1E2E"');

    // The same colour, quoted, is a colour, and the deck is drawn in it.
    $slide = Theme::slidesSlide(
        $deck,
        "colors:\n  dk1: \"#1E1E2E\"\n",
    );

    expect($slide)->toContain('1E1E2E');
});

it('says that a slide\'s own values are named by `theme-variables:`', function () {
    says(Site::PRESENTATION_THEMES, 'The frontmatter key that carries it is **`theme-variables:`**');
    says(Site::PRESENTATION_DIRECTIVES, '`theme-variables:` is the theme\'s own values for one slide');

    $slides = Deck::slides(
        "---\ntheme: corporate-dark\n---\n\n# One\n\nWords.\n\n---\ntheme-variables:\n"
            . "  colors:\n    accent1: \"#123456\"\n---\n\n# Two\n\nWords.\n\n## Three\n",
    );

    expect($slides[1])->toContain('<a:srgbClr val="123456"');
    expect($slides[0])->not->toContain('<a:srgbClr val="123456"');
});

it('says that a master fills in the roles it covers and says nothing about the rest', function () {
    says(Site::PRESENTATION_THEMES, 'A master fills in the roles it covers and says nothing about the rest');

    $deck = "---\ntheme: mine\n---\n\n# One\n\nText.\n";
    $plain = Theme::slidesSlide($deck, '');

    // A role the master has no layout for keeps the library's own geometry, and so does a
    // role mapped onto a layout with no placeholders in it.
    $uncovered = Theme::slidesMasterSlide(
        $deck,
        "layouts:\n  content: Nothing Here\n",
        ['Title and Content'],
    );

    $blank = Theme::slidesMasterSlide(
        $deck,
        "layouts:\n  content: Blank\n",
        ['Blank'],
    );

    expect(slideBoxes($uncovered))->toBe(slideBoxes($plain));
    expect(slideBoxes($blank))->toBe(slideBoxes($plain));
});

it('says that a theme with no master still names its layouts', function () {
    says(Site::PRESENTATION_THEMES, 'A theme with no master still names its layouts');

    $root = Theme::slidesWithMaster("layouts:\n  content: Mein Inhalt\n", null);

    $layouts = Directory::during(
        $root,
        static fn (string $root): array => Deck::layouts("---\ntheme: mine\n---\n\n# One\n", $root),
    );

    expect(\array_values($layouts))->toBe(['Mein Inhalt']);
});

it('says that the layout names are the theme\'s own, and not the master\'s', function () {
    says(Site::PRESENTATION_THEMES, 'The `layouts:` section maps each role to a layout name');

    expect(Site::flat(Site::page(Site::PRESENTATION_THEMES)))->not->toContain('a layout name in the master');
});

it('says what the three faces are, and draws a style\'s font in the theme\'s face', function () {
    says(Site::PRESENTATION_THEMES, '## The three faces');
    says(Site::PRESENTATION_THEMES, '| `heading` | the heading and the title |');
    says(Site::PRESENTATION_THEMES, '| `body` | the prose |');
    says(Site::PRESENTATION_THEMES, '| `mono` | the code |');
    says(Site::PRESENTATION_THEMES, 'A text style\'s `font:` may name one of the three');

    $slide = Theme::slidesSlide(
        "---\ntheme: mine\n---\n\nA :slot[Bakery] B :literal[Croissant] C\n",
        "fonts:\n  heading: Avenir Next\ntextStyles:\n  slot:\n    font: heading\n  literal:\n    font: Inter\n",
    );

    // The slot is the theme's face, and the name that is not a slot is a family as written.
    expect(Deck::run($slide, 'Bakery'))->toContain('typeface="Avenir Next"');
    expect(Deck::run($slide, 'Croissant'))->toContain('typeface="Inter"');
});

it('resolves a theme in the same order in both halves', function () {
    $slides = Site::section(Site::PRESENTATION_THEMES, 'Resolution order');
    $word = Site::section(Site::WORD_CONFIGURATION, 'Themes');

    foreach ([
        'a path — written from beside the deck, the way a relative image path is —',
        '`themes/<name>` beside the deck',
        '`themes/<name>` beside the working directory',
        'the package\'s own `themes/<name>`',
    ] as $step) {
        saysIn($slides, $step);
        saysIn($word, \str_replace('deck', 'document', $step));
    }

    says(Site::PRESENTATION_INDEX, '`themes/<name>` beside the working directory');
});

it('says that the theme\'s name comes back, and brings it back', function () {
    says(Site::PRESENTATION_INDEX, 'The theme\'s name comes back; the theme does not');

    $markdown = Deck::readBack("---\ntheme: corporate-dark\ntitle: A themed deck\n---\n\n# One\n");

    expect($markdown)->toContain('theme: "corporate-dark"');
});

it('says that a deck\'s own settings come back as the slides\' own', function () {
    says(Site::PRESENTATION_INDEX, 'A deck\'s own settings come back as the slides\' own');

    $markdown = Deck::readBack(
        "---\ntheme: corporate-dark\ndeck-settings:\n  background: \"#101014\"\n---\n\n# One\n\n---\n## Two\n",
    );

    // The background of every slide, and not the deck's own layer.
    expect(\substr_count($markdown, 'background: "#101014"'))->toBe(2);
    expect($markdown)->not->toContain('deck-settings:');
});
