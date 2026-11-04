<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Deck;
use MarkdownOffice\Documentation\Tests\Support\Example;
use MarkdownOffice\Documentation\Tests\Support\Site;

/**
 * The claims the pages make about the deck's own settings.
 *
 * The deck's settings are a layer of their own, and what the pages say about them is a
 * rule: a rule is a sentence rather than a name, so it cannot be checked against the code
 * by `tools/check-reference.php`. These are the sentences, and each one is checked against
 * the library that has to behave that way.
 */

/**
 * A deck whose frontmatter carries the deck's own settings, and a slide that names a
 * background of its own.
 */
const DECK_SETTINGS = "---\ndeck-settings:\n  background: \"#101014\"\n---\n\n# One\n\n---\nbackground: \"#202020\"\n---\n\n## Two\n";

/**
 * The same deck, with a template whose own settings name a background.
 */
const DECK_SETTINGS_TEMPLATED = "---\ndeck-settings:\n  background: \"#101014\"\n---\n\n---\ntemplate: brand\ntemplate-settings:\n  background: \"#303030\"\n---\n:::content{left=4.5% top=3.913% width=29.996% height=4.969% fill=none padding=0 color=FFFFFF font-size=11 font-family=Calibri align=left vertical-align=middle}\nResilience\n:::\n\n---\nlayout: blank\ntemplate: brand\n---\n\n## Two\n";

/**
 * The same deck, with a template and a slide that names a background of its own.
 */
const DECK_SETTINGS_TEMPLATED_AND_SLIDDEN = "---\ndeck-settings:\n  background: \"#101014\"\n---\n\n---\ntemplate: brand\ntemplate-settings:\n  background: \"#303030\"\n---\n:::content{left=4.5% top=3.913% width=29.996% height=4.969% fill=none padding=0 color=FFFFFF font-size=11 font-family=Calibri align=left vertical-align=middle}\nResilience\n:::\n\n---\nlayout: blank\ntemplate: brand\nbackground: \"#404040\"\n---\n\n## Two\n";

it('draws every slide of a deck with the deck\'s own background', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'Every slide is drawn with them');

    $slides = Deck::slides(DECK_SETTINGS);

    expect($slides)->toHaveCount(2);
    expect($slides[0])->toContain('val="101014"');
});

it('lets a slide\'s own background beat the deck\'s', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'A slide\'s own settings are laid over the template\'s');

    $slides = Deck::slides(DECK_SETTINGS);

    expect($slides[1])->toContain('val="202020"');
    expect($slides[1])->not->toContain('val="101014"');
});

it('lets a template\'s own background beat the deck\'s', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'the template\'s over the deck\'s');

    $slides = Deck::slides(DECK_SETTINGS_TEMPLATED);

    expect($slides[0])->toContain('val="101014"');
    expect($slides[1])->toContain('val="303030"');
});

it('lets a slide\'s own background beat a template\'s', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'A slide that draws a template and names a `background:` of its own');

    $slides = Deck::slides(DECK_SETTINGS_TEMPLATED_AND_SLIDDEN);

    expect($slides[1])->toContain('val="404040"');
    expect($slides[1])->not->toContain('val="303030"');
    expect($slides[1])->not->toContain('val="101014"');
});

it('shows a deck whose accent every slide is drawn with', function () {
    $source = Example::of(
        Site::PRESENTATION_DIRECTIVES,
        'A deck whose accent every slide is drawn with',
    );

    $slides = Deck::slides($source);

    // More than one slide, or the title is a claim about a deck that has none to make.
    expect($slides)->toHaveCount(3);

    foreach ($slides as $slide) {
        expect($slide)->toContain('<a:srgbClr val="123456"');
    }
});

it('refuses the deck\'s settings on a slide that is not the first', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'it is refused on any slide but the first');

    $message = Deck::refusal(
        "# One\n\n---\ndeck-settings:\n  background: \"#101014\"\n---\n\n## Two\n",
    );

    expect($message)->toContain('which is a deck directive and belongs in the frontmatter of slide 1');
});

it('refuses a setting that is not one, by name', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'is refused by name rather than left out');

    $message = Deck::refusal("---\ndeck-settings:\n  colour: \"#101014\"\n---\n\n# One\n");

    expect($message)->toContain('names "colour", which is not a setting');
});

it('refuses a value that is not a mapping of settings', function () {
    $message = Deck::refusal("---\ndeck-settings: red\n---\n\n# One\n");

    expect($message)->toContain('is not a mapping of the settings the deck carries');
});
