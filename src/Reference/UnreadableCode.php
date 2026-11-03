<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Reference;

/**
 * A piece of a library's code that the reference tables need and cannot find.
 *
 * Its own exception rather than a generic one, because the constant that is gone is the
 * thing that went wrong, and a reader of the failure needs to know which one it was.
 */
final class UnreadableCode extends \RuntimeException
{
    public static function missingConstant(string $class, string $constant): self
    {
        return new self(\sprintf(
            'The class %s has no constant "%s", so the list it holds cannot be read.',
            $class,
            $constant,
        ));
    }
}
