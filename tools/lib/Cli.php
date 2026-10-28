<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

/**
 * The ground every tool in this directory stands on.
 *
 * A tool is a program, and a program that is not run from a terminal writes its findings
 * where nobody reads them. The errors are shown as they happen rather than collected at
 * the end, because a tool that dies halfway has already found something.
 */
final class Cli
{
    /**
     * The root of the site: the directory this repository is in.
     */
    public static function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * Make sure this runs at a terminal, and that the errors are visible.
     *
     * @param string $message What to say to whoever is not at a terminal.
     */
    public static function guard(string $message): void
    {
        \error_reporting(\E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED);
        \ini_set('display_errors', 'stderr');

        if (\PHP_SAPI !== 'cli') {
            \fwrite(\STDERR, $message . "\n");

            exit(1);
        }
    }

    /**
     * Load the site's own classes.
     *
     * The site is built by Eleventy and this is the part it cannot do, so the classes
     * under `src/` are loaded by Composer's autoloader and the ones in this directory by
     * the autoloader beside them.
     */
    public static function autoload(): void
    {
        $file = self::root() . '/vendor/autoload.php';

        if (!\is_file($file)) {
            \fwrite(\STDERR, \sprintf('Missing "%s". Run `composer install` in documentation/.' . "\n", $file));

            exit(1);
        }

        require_once $file;
    }
}
