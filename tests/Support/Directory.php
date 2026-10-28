<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * A directory of its own, in the system's temporary directory.
 *
 * A fixture that is written to disk has to be written somewhere, and the somewhere has to
 * be one that is not the repository: a test that leaves a directory behind is a test
 * that makes the next one fail.
 */
final class Directory
{
    /**
     * A directory of its own, and the directory it is in.
     *
     * @param string $prefix What the directory is for, in its name.
     */
    public static function make(string $prefix): string
    {
        $root = \sys_get_temp_dir() . '/markdown-office-' . $prefix . '-' . \bin2hex(\random_bytes(6));

        \mkdir($root, 0o777, true);

        return $root;
    }

    /**
     * A directory of its own, removed again.
     */
    public static function remove(string $root): void
    {
        \exec(\sprintf('rm -rf %s', \escapeshellarg($root)));
    }

    /**
     * What a run said, and the directory is taken away again.
     *
     * A fixture that is written for one run is taken away when the run is over, whatever
     * the run said: a directory left behind is a directory the next run finds.
     *
     * @param callable(string): mixed $run What to do with the directory.
     */
    public static function during(string $root, callable $run): mixed
    {
        try {
            return $run($root);
        } finally {
            self::remove($root);
        }
    }
}
