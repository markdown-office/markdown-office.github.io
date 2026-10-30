<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Reference\Code;
use MarkdownOffice\Documentation\Tests\Support\Names;
use MarkdownOffice\Documentation\Tests\Support\Site;
use MarkdownPresentation\PowerPointToMarkdown;
use MarkdownPresentation\Render\PresentationRenderer;
use MarkdownWord\Files\FileFinder as WordFileFinder;

/**
 * The method names the pages state in prose.
 *
 * A page says what a class has as often in a sentence as in a table, and a name in a
 * sentence is a name nobody checks: the code is free to rename it, and the sentence
 * stands there saying what the class had. The count of a list is asked of the code by
 * `counts.php`; the names are asked here.
 */

/**
 * The methods a sentence names, in the order the code lists them.
 *
 * @return list<string>
 */
function namedMethods(string $sentence): array
{
    $named = Names::methods($sentence);

    \sort($named);

    return $named;
}

it('names the renderer\'s methods as the code does', function () {
    $page = Site::flat(Site::page(Site::PRESENTATION_INDEX));

    \preg_match('/`PresentationRenderer` has \w+ methods, ([^;]+);/', $page, $match);

    expect(namedMethods($match[1] ?? ''))->toBe(Code::methods(PresentationRenderer::class));
});

it('names the reader\'s methods as the code does', function () {
    $page = Site::flat(Site::page(Site::PRESENTATION_INDEX));

    \preg_match('/`PowerPointToMarkdown`, with ([^.]+)\./', $page, $match);

    expect(namedMethods($match[1] ?? ''))->toBe(Code::methods(PowerPointToMarkdown::class));
});

it('names the finder\'s methods as the code does', function () {
    $section = Site::section(Site::FILES, 'The four methods');

    expect(namedMethods($section))->toBe(Code::methods(WordFileFinder::class));
});
