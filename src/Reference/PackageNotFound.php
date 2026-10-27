<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Reference;

/**
 * A package whose root could not be found.
 *
 * Its own exception rather than a generic one, because the class that belongs to no
 * package is the thing that went wrong, and a reader of the failure needs to know which
 * class it was.
 */
final class PackageNotFound extends \RuntimeException
{
    public static function for(string $class): self
    {
        return new self(\sprintf(
            'There is no composer.json above %s, so the package it belongs to cannot be found.',
            $class,
        ));
    }
}
