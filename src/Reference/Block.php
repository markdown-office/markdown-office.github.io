<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Reference;

/**
 * A block of a page: a table or a fenced list, and the names in it.
 *
 * A block is where a vocabulary lives, whether or not the page says so — which is what
 * makes a second copy of a list findable: the block that carries the vocabulary is the
 * marked one, and every other block that lists the same names is a copy of it.
 */
final class Block
{
    /**
     * @param list<string>         $names  The names the block lists.
     * @param array<string, string> $values The second cell of each row, where the block is a table.
     */
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly array $names,
        public readonly array $values = [],
    ) {
    }
}
