<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * The PHP a page shows.
 *
 * A page that shows an interface shows it as the library writes it, and a page that shows
 * an adapter shows code a reader may copy. Both are copies, and a copy drifts — so the
 * code is read here and compared with what the library has.
 *
 * The methods are read the way the page writes them, because that is how
 * {@see \MarkdownOffice\Documentation\Reference\Code::signatures()} writes them: a
 * signature that does not match is a page that has to change.
 */
final class Snippet
{
    /**
     * The classes that have been loaded, by name.
     *
     * A class cannot be declared twice in one process, so the second claim that asks for
     * the adapter a reader would copy gets the one that is already loaded.
     *
     * @var array<string, true>
     */
    private static array $loaded = [];

    /**
     * The fenced PHP of a page that holds a given text.
     */
    public static function of(string $page, string $needle): string
    {
        \preg_match_all('/```php\n(.*?)\n```/s', $page, $blocks);

        foreach ($blocks[1] as $block) {
            if (\str_contains($block, $needle)) {
                return $block;
            }
        }

        throw NoSnippet::with($needle);
    }

    /**
     * The methods a piece of PHP declares, as they are written.
     *
     * A method without a return type is not one: a constructor is not part of an
     * interface, and a method the code does not say what it returns is not a signature
     * anybody can check.
     *
     * @return list<string>
     */
    public static function methods(string $code): array
    {
        \preg_match_all(
            '/function\s+(\w+)\s*\(([^)]*)\)\s*:\s*([^;{]+)[;{]/',
            $code,
            $matches,
            \PREG_SET_ORDER,
        );

        $found = [];

        foreach ($matches as $match) {
            $parameters = \trim($match[2]) === ''
                ? []
                : \array_map('trim', \explode(',', $match[2]));

            $found[] = $match[1] . '(' . \implode(', ', $parameters) . '): ' . \trim($match[3]);
        }

        \sort($found);

        return $found;
    }

    /**
     * The class a piece of PHP declares.
     */
    public static function className(string $code): string
    {
        if (\preg_match('/^\s*(?:final\s+)?class\s+(\w+)/m', $code, $match) !== 1) {
            throw NoSnippet::withoutClass();
        }

        return $match[1];
    }

    /**
     * The adapter a page shows, loaded and given some files.
     *
     * The code is loaded rather than read, because what a claim is about is what the
     * code does: a sample that returns its themes unsorted is a sample that breaks the
     * contract the page states, however it is written.
     *
     * @param array<string, string> $files The files the adapter holds, by name.
     */
    public static function adapter(string $code, array $files): object
    {
        $class = self::className($code);

        if (! isset(self::$loaded[$class])) {
            $file = \sys_get_temp_dir() . '/markdown-office-adapter-' . $class . '.php';

            // The page shows a piece of a file rather than a file, so the opening tag is
            // missing from it — and a file without one is read as text.
            \file_put_contents($file, "<?php\n\n" . $code);

            require_once $file;

            // The class is in memory now, and the file it came from is of no use to
            // anybody: a fixture that is left behind is a fixture the next run finds.
            \unlink($file);

            self::$loaded[$class] = true;
        }

        return new $class($files);
    }
}
