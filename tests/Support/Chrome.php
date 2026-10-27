<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * A deck's own directory, and the chrome file beside it.
 *
 * A template may be imported from a file beside the deck, so a claim about one needs a
 * deck's directory to put the file in — and the deck has to be rendered from there, or the
 * path is resolved against somewhere else.
 */
final class Chrome
{
    /**
     * A directory holding a chrome file, and the directory it is in.
     */
    public static function beside(string $markdown): string
    {
        $root = \sys_get_temp_dir() . '/markdown-office-chrome-' . \bin2hex(\random_bytes(6));

        \mkdir($root, 0o777, true);
        \file_put_contents($root . '/chrome.md', $markdown);

        return $root;
    }

    /**
     * A directory of a deck's own, removed again.
     */
    public static function remove(string $root): void
    {
        \exec(\sprintf('rm -rf %s', \escapeshellarg($root)));
    }
}
