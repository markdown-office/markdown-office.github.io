<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Document;
use MarkdownOffice\Documentation\Tests\Support\Site;
use MarkdownWord\WordToMarkdown;

/**
 * The claims the pages make about what a round trip loses.
 *
 * A round trip is two calls, and what comes back is the document's text and nothing else.
 * What it does not bring back is a list, and the list is a property of the formats rather
 * than a bug: a file says what is on a slide or in a paragraph, and not what drew it.
 */

/**
 * A document with a frontmatter, as the reader is given it.
 */
const FRONTMATTER = "---\ntheme: corporate\nstyles:\n  heading.1:\n    name: Georgia\n"
    . "options:\n  hardBreak: line\n---\n\n# Title\n";

it('says that the frontmatter does not come back, and loses it', function () {
    says(Site::WORD_ROUND_TRIP, 'The frontmatter does not come back');
    says(Site::WORD_ROUND_TRIP, 'no frontmatter at all');

    $back = (new WordToMarkdown(Document::path(FRONTMATTER)))->convert();

    expect($back)->toBe('# Title');
});

it('says that the frontmatter is one of the things that do not survive', function () {
    says(Site::WORD_ROUND_TRIP, 'a document read back has lost its `theme:`, its');
    says(Site::WORD_ROUND_TRIP, '`styles:` and its `options:`');
});
