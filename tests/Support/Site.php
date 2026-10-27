<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * The pages of the site, as they are written.
 */
final class Site
{
    /**
     * The root of the site: the directory this repository is in.
     */
    public static function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * One page of the site.
     */
    public static function page(string $path): string
    {
        $file = self::root() . '/content/' . $path;

        if (!\is_file($file)) {
            throw new \RuntimeException(\sprintf('There is no page at %s.', $file));
        }

        return (string) \file_get_contents($file);
    }

    /**
     * The body of a section of a page, as the page wrote it.
     *
     * The section rather than the page, because a page carries several lists and a claim is
     * about one of them. The line breaks are gone, because the prose is wrapped and a sentence
     * a page states is broken across two lines.
     */
    public static function section(string $path, string $heading): string
    {
        $body = [];
        $inside = false;

        foreach (\explode("\n", self::page($path)) as $line) {
            if (\str_starts_with($line, '## ')) {
                $inside = \trim($line) === '## ' . $heading;

                continue;
            }

            if ($inside) {
                $body[] = $line;
            }
        }

        return (string) \preg_replace('/\s+/', ' ', \implode("\n", $body));
    }

    /**
     * A page of the site with its line breaks gone.
     *
     * Prose is wrapped, so a sentence a page states is broken across two lines and a test
     * that looks for it as one string finds nothing. The words are what the page says,
     * and where it breaks them is the typesetter's business.
     */
    public static function flat(string $page): string
    {
        return (string) \preg_replace('/\s+/', ' ', $page);
    }

    /**
     * A number as the English word a page writes it in.
     *
     * The counts a page states are claims about the code, and a claim written as a digit
     * is read as a number while a claim written as a word is read as a sentence: the
     * word is what the tests look for, and the number behind it is what they check.
     */
    public static function words(int $number): string
    {
        $words = [
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
            11 => 'eleven',
            12 => 'twelve',
            13 => 'thirteen',
            14 => 'fourteen',
            15 => 'fifteen',
            16 => 'sixteen',
            17 => 'seventeen',
            18 => 'eighteen',
            19 => 'nineteen',
            20 => 'twenty',
        ];

        return $words[$number] ?? (string) $number;
    }
}
