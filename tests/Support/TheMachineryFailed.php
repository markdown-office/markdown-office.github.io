<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * A piece of the tests' own machinery that did not come up.
 *
 * Its own exception rather than a generic one, because a test whose stand-in did not start
 * has failed for a reason of its own and not because of the code under test, and a reader
 * of the failure needs to know which of the two it was.
 */
final class TheMachineryFailed extends \RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
