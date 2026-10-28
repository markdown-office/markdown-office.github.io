<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Document;
use MarkdownOffice\Documentation\Tests\Support\Types;
use MarkdownWord\Format;
use MarkdownWord\WordToMarkdown;
use MarkdownOffice\Documentation\Tests\Support\Site;

/**
 * The claims the pages make about the objects a Word document may hold.
 *
 * The Word half never refuses: a directive a writer cannot carry is given the author's own
 * characters back, and the loss is reported rather than thrown. Both halves of that are
 * claims about what the library does, and each is checked against the library that has to
 * do it — the sentence the page states beside the behaviour it is about.
 */

it('says that the Word half never refuses, and gives a text box back to the author', function () {
    says(Site::WORD_DIRECTIVES, 'The Word half never refuses');

    $markdown = "::textbox[A note]{width=6 height=2}\n";

    expect(Document::written($markdown, Format::Odt))->toContain('::textbox');
    expect(Document::losses($markdown, Format::Odt))->toContain('text-box');
});

it('reports the loss of a text box, a shape and a form field in every format but docx', function () {
    $markdown = "::textbox[A note]{width=6 height=2}\n\n::shape{type=rect width=4 height=2}\n\n::field{name=x}\n";

    expect(Document::losses($markdown, Format::Docx))->toBe([]);
    expect(Document::losses($markdown, Format::Odt))->toBe(['text-box', 'shape', 'form-field']);
    expect(Document::losses($markdown, Format::Rtf))->toBe(['text-box', 'shape', 'form-field']);
});

it('loses the columns of a section in an rtf and keeps them in an odt', function () {
    $markdown = "::section{columns=2}\n";

    expect(Document::losses($markdown, Format::Odt))->toBe([]);
    expect(Document::losses($markdown, Format::Rtf))->toBe(['columns']);
});

it('says that a text box comes back without its text', function () {
    says(Site::WORD_ROUND_TRIP, 'a text box comes back without its text');

    $back = (new WordToMarkdown(Document::path("::textbox[A note]{width=6 height=2}\n")))->convert();

    expect($back)->not->toContain('A note');
});

it('says that a form field comes back as the blank field Word draws', function () {
    says(Site::WORD_ROUND_TRIP, 'a form field comes back as the blank field Word draws');

    $back = (new WordToMarkdown(Document::path("::field{name=\"Customer\"}\n")))->convert();

    expect($back)->toContain(\str_repeat(' ', 30));
});

it('says that a page break and the columns of a section come back as nothing', function () {
    says(Site::WORD_ROUND_TRIP, 'a page break and the columns of a section come back as nothing');

    $break = (new WordToMarkdown(Document::path("Before.\n\n::pagebreak\n\nAfter.\n")))->convert();
    $section = (new WordToMarkdown(Document::path("Before.\n\n::section{columns=2}\n\nAfter.\n")))->convert();

    expect($break)->toBe("Before.\n\nAfter.");
    expect($section)->toBe("Before.\n\nAfter.");
});

it('draws every type the page names, and writes one it does not draw as the source text', function (string $heading, string $marker, string $directive, string $unknown) {
    $types = Types::drawn(
        Site::WORD_DIRECTIVES,
        $heading,
        $marker,
        static fn (string $type): string => Document::body(\sprintf($directive, $type)),
    );

    expect($types)->not->toBe([]);

    expect(Document::body($unknown))->toContain($marker);
})->with([
    'shapes' => [
        '`::shape` — a shape',
        '::shape',
        '::shape{type=%s width=4 height=2}',
        '::shape{type=triangle width=4 height=2}',
    ],
    'form fields' => [
        '`::field` — a form field',
        '::field',
        '::field{name=x type=%s}',
        '::field{name=x type=date}',
    ],
]);

it('names every type the page lists, and not only the ones its first sentence does', function () {
    expect(Types::listedIn([
        'The type is one of `arc`. `oval` and `rect` are shapes as well.',
    ]))->toBe(['arc', 'oval', 'rect']);

    expect(Types::listed(Site::WORD_DIRECTIVES, '`::shape` — a shape'))
        ->toBe(['arc', 'curve', 'line', 'polyline', 'rect', 'oval']);
});

it('gives a shape back to the author in a format that cannot carry it', function () {
    $markdown = "::shape{type=rect width=4 height=2}\n";

    expect(Document::written($markdown, Format::Rtf))->toContain('::shape');
    expect(Document::losses($markdown, Format::Rtf))->toContain('shape');
});
