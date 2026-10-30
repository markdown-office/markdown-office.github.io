<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * A piece of PHP a page was supposed to show.
 *
 * Its own exception rather than a generic one, because the code a claim is about is the
 * thing that went wrong: a page whose adapter was taken out says so, and a reader of the
 * failure knows which page to look at.
 */
final class NoSnippet extends \RuntimeException
{
    public static function with(string $needle): self
    {
        return new self(\sprintf('No page shows PHP that holds "%s".', $needle));
    }

    public static function withoutClass(): self
    {
        return new self('The PHP a page shows declares no class.');
    }
}
