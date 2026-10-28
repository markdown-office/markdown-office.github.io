<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Chrome;
use MarkdownOffice\Documentation\Tests\Support\Deck;
use MarkdownOffice\Documentation\Tests\Support\Site;

/**
 * The claims the pages make about the slide templates.
 *
 * A template is the furniture of a deck: a slide of the deck's own Markdown that is drawn on
 * every slide that names it. What the pages say about one is a set of rules, and a rule is a
 * sentence rather than a name — so each claim here is the sentence and the behaviour it is
 * about, and the second half is what fails when the library stops behaving that way.
 */

it('says that a template definition is not a slide of the deck, and draws none', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'A definition is not a slide of the deck');

    $deck = "---\ntemplate: brand\n---\n::line{left=5% top=72% width=30% height=0.2%}\n\n---\nlayout: blank\ntemplate: brand\n---\n\nWords.\n";

    expect(Deck::slides($deck))->toHaveCount(1);
});

it('says that a template holds chrome and nothing else, and refuses ordinary Markdown in one', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'A template holds chrome and nothing else');

    $message = Deck::refusal("---\ntemplate: brand\n---\n# A heading\n");

    expect($message)->toContain('The template "brand" holds a heading');
    expect($message)->toContain('chrome, not content');
});

it('says that a slide may name several templates, and draws the chrome of each', function () {
    says(Site::PRESENTATION_DIRECTIVES, 'A slide may name several templates');

    $deck = "---\ntemplate: brand\n---\n::rectangle{left=0 top=0 width=10% height=10%}\n\n---\ntemplate: page\n---\n::line{left=5% top=72% width=30% height=0.2%}\n\n---\nlayout: blank\ntemplate: [brand, page]\n---\n\nWords.\n";

    $shapes = Deck::shapes($deck)[0];

    expect($shapes)->toContain('rectangle');
    expect($shapes)->toContain('line');
});

it('says that layout and template are independent, and draws both on one slide', function () {
    says(Site::PRESENTATION_DIRECTIVES, '`layout:` and `template:` are independent');

    $deck = "---\ntemplate: brand\n---\n::rectangle{left=0 top=0 width=10% height=10%}\n\n---\nlayout: content\ntemplate: brand\n---\n\n# Words.\n";

    $shapes = Deck::shapes($deck)[0];

    expect($shapes)->toContain('title');
    expect($shapes)->toContain('rectangle');
});

it('draws the chrome over what the slide placed itself', function () {
    $deck = "---\ntemplate: brand\n---\n::rectangle{left=0 top=0 width=10% height=10%}\n\n---\nlayout: blank\ntemplate: brand\n---\n\n::circle{left=1% top=1% width=5% height=5%}\n";

    $shapes = Deck::shapes($deck)[0];

    expect(\array_search('circle', $shapes, true))->toBeLessThan(\array_search('rectangle', $shapes, true));
});

it('reads a chrome file from beside the deck', function () {
    $shapes = Chrome::shapes(
        "---\nchrome: ./chrome.md\n---\n# One\n\n---\nlayout: blank\ntemplate: brand\n---\n\nWords.\n",
        "---\ntemplate: brand\n---\n::rectangle{left=0 top=0 width=10% height=10%}\n",
    );

    expect($shapes[1])->toContain('rectangle');
});

it('refuses a chrome file that is not there', function () {
    $message = Deck::refusal("---\nchrome: ./nowhere.md\n---\n# One\n", \sys_get_temp_dir());

    expect($message)->toContain('There is no chrome file at');
});

it('lets the deck\'s own template override an imported one of the same name', function () {
    $shapes = Chrome::shapes(
        "---\nchrome: ./chrome.md\n---\n# One\n\n---\ntemplate: brand\n---\n::circle{left=1% top=1% width=5% height=5%}\n\n---\nlayout: blank\ntemplate: brand\n---\n\nWords.\n",
        "---\ntemplate: brand\n---\n::rectangle{left=0 top=0 width=10% height=10%}\n",
    );

    expect($shapes[1])->toContain('circle');
    expect($shapes[1])->not->toContain('rectangle');
});

it('says that a slide number is a shape, and writes the number of the slide', function () {
    says(Site::PRESENTATION_DIRECTIVES, '`::slide-number` is a shape like any other');

    $deck = "---\nlayout: blank\n---\n\n::slide-number{left=94% top=92% width=5% height=6%}\n\n---\nlayout: blank\n---\n\nTwo.\n";

    $slide = Deck::slides($deck)[0];

    expect(Deck::run($slide, '1'))->not->toBe('');
});

it('writes the number of the deck after the number with of=true', function () {
    $deck = "---\nlayout: blank\n---\n\n::slide-number{left=94% top=92% width=5% height=6% of=true}\n\n---\nlayout: blank\n---\n\nTwo.\n";

    $slide = Deck::slides($deck)[0];

    expect(Deck::run($slide, '1 / 2'))->not->toBe('');
});
