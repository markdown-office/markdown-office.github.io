<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Reference\Pages;
use MarkdownOffice\Documentation\Reference\Vocabularies;
use MarkdownOffice\Documentation\Tests\Support\Site;
use MarkdownPresentation\Exception\Exception as PresentationException;
use MarkdownPresentation\Exception\InvalidInput;
use MarkdownWord\Exception\Exception as WordException;

/**
 * The claims the pages make about being complete.
 *
 * A page that says it lists everything is making a promise a reader relies on, and the
 * promise is worth nothing if nothing checks it: the presentation half's error page said
 * it listed every exception the package throws, and left five of them out.
 */

/**
 * The names the marked block of a vocabulary lists.
 *
 * @return list<string>
 */
function listed(string $vocabulary): array
{
    $marked = Pages::of(Site::root() . '/content')['marked'][$vocabulary] ?? [];

    return $marked === [] ? [] : $marked[0]->names;
}

it('lists every exception the presentation half throws', function () {
    says('presentation/errors.md', 'Every exception Markdown Presentation throws, in one place');

    expect(\count(listed('presentation.exceptions')))
        ->toBe(\count(Vocabularies::exceptions(PresentationException::class)));
});

it('lists every exception the Word half throws', function () {
    says('word/errors.md', 'Every exception Markdown Word throws, in one place');

    expect(\count(listed('word.exceptions')))->toBe(\count(Vocabularies::exceptions(WordException::class)));
});

it('says how many exceptions sit beside InvalidInput, as the code does', function () {
    $beside = 0;

    foreach (Vocabularies::exceptions(PresentationException::class) as $name) {
        // `InvalidInput` is the base itself rather than a failure beside it.
        if ($name === 'InvalidInput') {
            continue;
        }

        if (!\is_subclass_of('MarkdownPresentation\\Exception\\' . $name, InvalidInput::class)) {
            $beside++;
        }
    }

    says('presentation/errors.md', words($beside) . ' sit beside `InvalidInput`');
    says('presentation/index.md', words($beside) . ' sit beside `InvalidInput`');
    says('general/errors.md', words($beside) . ' sit beside `InvalidInput`');
});
