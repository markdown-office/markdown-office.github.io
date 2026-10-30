<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * The names a piece of prose states.
 *
 * A page says what a class has in prose as often as in a table, and a name in prose is a
 * name nobody checks: the code is free to rename it and the sentence stands.
 */
final class Names
{
    /**
     * The methods a piece of prose names.
     *
     * The name is written in code font and with its brackets, because that is how a page
     * writes a method it means and how it writes a file it does not.
     *
     * @return list<string>
     */
    public static function methods(string $text): array
    {
        \preg_match_all('/`(\w+)\(\)`/', $text, $matches);

        return \array_values(\array_unique($matches[1]));
    }
}
