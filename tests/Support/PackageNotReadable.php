<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * A package that could not be opened.
 *
 * Its own exception rather than a generic one, because a package is a fixture and the
 * file that could not be read is what a reader of the failure needs to know.
 */
final class PackageNotReadable extends \RuntimeException
{
    public static function at(string $path): self
    {
        return new self(\sprintf('The package at "%s" could not be opened.', $path));
    }
}
