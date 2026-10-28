<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Reference\Vocabularies;
use MarkdownOffice\Documentation\Tests\Support\RoundTrip;
use MarkdownOffice\Documentation\Tests\Support\Site;
use MarkdownPresentation\Render\PresentationRenderer;
use MarkdownPresentation\Theme\LayoutRole;
use MarkdownPresentation\Theme\StyleMapping;
use MarkdownPresentation\Theme\Theme;

/**
 * The counts the pages state.
 *
 * A count in prose is a claim about the code, and a claim nobody checks is a claim nobody
 * made: the site said four themes shipped when five did, and said thirteen style keys
 * when there were twelve. Each of these asks the code how many there are and the page
 * how many it says.
 */

it('counts the colour slots as the code does', function () {
    says(Site::PRESENTATION_THEMES, 'The ' . words(\count(Theme::COLOURS)) . ' colour slots');
});

it('counts the style keys as the code does', function () {
    says(Site::PRESENTATION_THEMES, 'the ' . words(\count(StyleMapping::KEYS)) . ' style keys');
});

it('counts the layout roles as the code does', function () {
    says(Site::PRESENTATION_THEMES, words(\count(LayoutRole::names())) . ', and only these');
});

it('counts the colour aliases as the code does', function () {
    $aliases = Vocabularies::all()['presentation.theme-aliases']->names();

    says(Site::PRESENTATION_THEMES, words(\count($aliases)) . ' read-friendly aliases are accepted');
});

it('counts the frontmatter keys as the code does', function () {
    $count = \count(PresentationRenderer::DIRECTIVES);

    says(Site::PRESENTATION_INDEX, 'a **closed set of ' . words($count) . '**');
    says(Site::PRESENTATION_DIRECTIVES, 'one of these ' . words($count));
});

it('counts the free shapes as the code does', function () {
    $shapes = Vocabularies::all()['presentation.free-shapes']->names();

    says(Site::PRESENTATION_DIRECTIVES, words(\count($shapes)) . ' of them and no others');
});

it('counts the themes that ship as the code does', function () {
    $themes = Vocabularies::all()['presentation.themes']->names();

    says(Site::PRESENTATION_THEMES, words(\count($themes)) . ' themes ship');
});

it('counts the Word themes that ship as the code does', function () {
    $themes = Vocabularies::all()['word.themes']->names();

    says('word/configuration.md', words(\count($themes)) . ' themes ship');
});

it('counts the Word directives as the code does', function () {
    $directives = Vocabularies::all()['word.directives']->names();

    says(Site::WORD_DIRECTIVES, 'draws **' . words(\count($directives)) . '** directives');
    says('word/index.md', words(\count($directives)) . ' directives');
    says(Site::GENERAL_DIRECTIVES, words(\count($directives)) . ' directives');
});

it('counts the inline style attributes as the code does', function () {
    $attributes = Vocabularies::all()['text-style-attributes']->names();

    says(Site::GENERAL_DIRECTIVES, words(\count($attributes)) . ' attributes');
});

it('counts the things that do not survive as the page lists them', function () {
    $lost = RoundTrip::losses();

    \preg_match_all('/^### (.+)$/m', Site::page(Site::WORD_ROUND_TRIP), $matches, \PREG_SET_ORDER);

    $listed = \array_map(static fn (array $match): string => $match[1], $matches);

    \sort($lost);
    \sort($listed);

    expect($listed)->toBe($lost);

    says(Site::WORD_ROUND_TRIP, words(\count($lost)) . ' things that do not');
});

it('counts the renderer\'s methods as the code does', function () {
    $methods = \array_filter(
        (new \ReflectionClass(PresentationRenderer::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        static fn (\ReflectionMethod $method): bool => $method->getName() !== '__construct',
    );

    says(Site::PRESENTATION_INDEX, words(\count($methods)) . ' methods');
});
