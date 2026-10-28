<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * A page of the site that is not there.
 *
 * Its own exception rather than a generic one, because the page a claim asks for is the
 * thing that went wrong: a claim about a page that has moved says so, and a reader of
 * the failure knows which page to look for.
 */
final class PageNotFound extends \RuntimeException
{
    public static function at(string $file): self
    {
        return new self(\sprintf('There is no page at %s.', $file));
    }
}
