<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Reference\Vocabularies;
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
});
