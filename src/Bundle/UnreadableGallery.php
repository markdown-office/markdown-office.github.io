<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Bundle;

/**
 * A gallery the bundle is to carry that could not be read.
 *
 * Its own exception rather than a generic one, because the manifest that names the
 * galleries is the thing that went wrong, and a reader of the failure needs to know which
 * file it was.
 */
final class UnreadableGallery extends \RuntimeException
{
    public static function unreadable(string $file): self
    {
        return new self(\sprintf('%s could not be read.', $file));
    }

    public static function notJson(string $file): self
    {
        return new self(\sprintf('%s could not be read as JSON.', $file));
    }
}
