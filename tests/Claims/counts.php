<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Reference\Vocabularies;
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
    says('presentation/themes.md', 'The ' . words(\count(Theme::COLOURS)) . ' colour slots');
});

it('counts the style keys as the code does', function () {
    says('presentation/themes.md', 'the ' . words(\count(StyleMapping::KEYS)) . ' style keys');
});

it('counts the layout roles as the code does', function () {
    says('presentation/themes.md', words(\count(LayoutRole::names())) . ', and only these');
});

it('counts the colour aliases as the code does', function () {
    $aliases = Vocabularies::all()['presentation.theme-aliases']->names();

    says('presentation/themes.md', words(\count($aliases)) . ' read-friendly aliases are accepted');
});

it('counts the frontmatter keys as the code does', function () {
    $count = \count(PresentationRenderer::DIRECTIVES);

    says('presentation/index.md', 'a **closed set of ' . words($count) . '**');
    says('presentation/directives.md', 'one of these ' . words($count));
});

it('counts the free shapes as the code does', function () {
    $shapes = Vocabularies::all()['presentation.free-shapes']->names();

    says('presentation/directives.md', words(\count($shapes)) . ' of them and no others');
});

it('counts the themes that ship as the code does', function () {
    $themes = Vocabularies::all()['presentation.themes']->names();

    says('presentation/themes.md', words(\count($themes)) . ' themes ship');
});

it('counts the Word themes that ship as the code does', function () {
    $themes = Vocabularies::all()['word.themes']->names();

    says('word/configuration.md', words(\count($themes)) . ' themes ship');
});

it('counts the Word directives as the code does', function () {
    $directives = Vocabularies::all()['word.directives']->names();

    says('word/directives.md', 'draws **' . words(\count($directives)) . '** directives');
    says('word/index.md', words(\count($directives)) . ' directives');
    says('general/directives.md', words(\count($directives)) . ' directives');
});

it('counts the inline style attributes as the code does', function () {
    $attributes = Vocabularies::all()['text-style-attributes']->names();

    says('general/directives.md', words(\count($attributes)) . ' attributes');
});

it('counts the things that do not survive as the page lists them', function () {
    $sections = (int) \preg_match_all('/^### /m', Site::page('word/round-trip.md'));

    says('word/round-trip.md', words($sections) . ' things that do not');
});

it('counts the renderer\'s methods as the code does', function () {
    $methods = \array_filter(
        (new \ReflectionClass(PresentationRenderer::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        static fn (\ReflectionMethod $method): bool => $method->getName() !== '__construct',
    );

    says('presentation/index.md', words(\count($methods)) . ' methods');
});
