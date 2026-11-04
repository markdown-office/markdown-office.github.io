<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * An example a page was supposed to show.
 *
 * Its own exception rather than a generic one, because the example a claim asks for is
 * the thing that went wrong: a page whose example was renamed says so, and a reader of
 * the failure knows which page to look at.
 */
final class NoExample extends \RuntimeException
{
    public static function with(string $title): self
    {
        return new self(\sprintf('No page shows an example called "%s".', $title));
    }

    public static function withoutSource(string $title): self
    {
        return new self(\sprintf('The example called "%s" holds no fenced Markdown.', $title));
    }
}
