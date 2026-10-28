<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * A page that lists no type at all.
 *
 * Its own exception rather than a generic one, because a page that promises a closed set
 * and lists nothing has broken its promise, and the page it broke it in is what a reader
 * of the failure needs to know.
 */
final class NoTypesListed extends \RuntimeException
{
    public static function on(string $page): self
    {
        return new self(\sprintf('The page "%s" lists no type at all.', $page));
    }
}
