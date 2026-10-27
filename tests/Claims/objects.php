<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Document;
use MarkdownOffice\Documentation\Tests\Support\Site;
use MarkdownWord\Format;
use MarkdownWord\WordToMarkdown;

/**
 * The claims the pages make about the objects a Word document may hold.
 *
 * The Word half never refuses: a directive a writer cannot carry is given the author's own
 * characters back, and the loss is reported rather than thrown. Both halves of that are
 * claims about what the library does, and each is checked against the library that has to
 * do it — the sentence the page states beside the behaviour it is about.
 */

/**
 * The body of a section of a page, as the page wrote it.
 *
 * The section rather than the page, because a page carries several lists and a claim is
 * about one of them. The line breaks are gone, because the prose is wrapped and a sentence
 * a page states is broken across two lines.
 */
function sectionOf(string $path, string $heading): string
{
    $body = [];
    $inside = false;

    foreach (\explode("\n", Site::page($path)) as $line) {
        if (\str_starts_with($line, '## ')) {
            $inside = \trim($line) === '## ' . $heading;

            continue;
        }

        if ($inside) {
            $body[] = $line;
        }
    }

    return (string) \preg_replace('/\s+/', ' ', \implode("\n", $body));
}

/**
 * The names a sentence lists, in code font.
 *
 * @return list<string>
 */
function listedIn(string $sentence): array
{
    \preg_match_all('/`([^`]+)`/', $sentence, $matches);

    return $matches[1];
}

it('says that the Word half never refuses, and gives a text box back to the author', function () {
    says('word/directives.md', 'The Word half never refuses');

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
    says('word/round-trip.md', 'a text box comes back without its text');

    $back = (new WordToMarkdown(Document::path("::textbox[A note]{width=6 height=2}\n")))->convert();

    expect($back)->not->toContain('A note');
});

it('says that a form field comes back as the blank field Word draws', function () {
    says('word/round-trip.md', 'a form field comes back as the blank field Word draws');

    $back = (new WordToMarkdown(Document::path("::field{name=\"Customer\"}\n")))->convert();

    expect($back)->toContain(\str_repeat(' ', 30));
});

it('says that a page break and the columns of a section come back as nothing', function () {
    says('word/round-trip.md', 'a page break and the columns of a section come back as nothing');

    $back = (new WordToMarkdown(Document::path("Before.\n\n::pagebreak\n\nAfter.\n")))->convert();

    expect($back)->toBe("Before.\n\nAfter.");
});

it('draws every shape type the page names, and writes one it does not draw as the source text', function () {
    \preg_match('/The type is one of (.+?)\./', sectionOf('word/directives.md', '`::shape` — a shape'), $match);

    $types = listedIn($match[1] ?? '');

    expect($types)->not->toBe([]);

    foreach ($types as $type) {
        expect(Document::body("::shape{type=$type width=4 height=2}"))->not->toContain('::shape');
    }

    expect(Document::body('::shape{type=triangle width=4 height=2}'))->toContain('::shape');
});

it('draws every field type the page names, and writes one it does not draw as the source text', function () {
    \preg_match('/The type is one of (.+?)\./', sectionOf('word/directives.md', '`::field` — a form field'), $match);

    $types = listedIn($match[1] ?? '');

    expect($types)->not->toBe([]);

    foreach ($types as $type) {
        expect(Document::body("::field{name=x type=$type}"))->not->toContain('::field');
    }

    expect(Document::body('::field{name=x type=date}'))->toContain('::field');
});

it('gives a shape back to the author in a format that cannot carry it', function () {
    $markdown = "::shape{type=rect width=4 height=2}\n";

    expect(Document::written($markdown, Format::Rtf))->toContain('::shape');
    expect(Document::losses($markdown, Format::Rtf))->toContain('shape');
});
