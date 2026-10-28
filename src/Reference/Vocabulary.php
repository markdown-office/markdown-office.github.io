<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Reference;

/**
 * A vocabulary: a list of names the code has, and the page that lists them.
 *
 * A vocabulary may be kept in more than one place in the code — the Word half's style
 * slots and the presentation half's copy of them are one list with two homes, and the
 * code says so — so the lists are kept apart and compared with each other rather than
 * merged: a merged list hides which half has drifted.
 */
final class Vocabulary
{
    /**
     * @param array<string, list<string>> $lists  The code's lists, keyed by where each is kept.
     * @param array<string, string|int>    $values A value per name, where the code has one.
     */
    public function __construct(
        public readonly array $lists,
        public readonly array $values = [],
    ) {
    }

    /**
     * The names the page has to list.
     *
     * The first list is the one the others are held to, so it is the list the page is
     * compared with.
     *
     * @return list<string>
     */
    public function names(): array
    {
        foreach ($this->lists as $names) {
            return $names;
        }

        return [];
    }

    /**
     * The code's other lists, and the ones of them that do not agree with the first.
     *
     * The order the names are in is not part of the agreement: the two halves keep the
     * same slots in a different order, and a list is a set of names rather than a
     * sequence of them.
     *
     * @return array<string, list<string>>
     */
    public function disagreeing(): array
    {
        $first = $this->sorted($this->names());
        $disagreeing = [];

        foreach ($this->lists as $where => $names) {
            if ($this->sorted($names) !== $first) {
                $disagreeing[$where] = $names;
            }
        }

        return $disagreeing;
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        \sort($names);

        return $names;
    }
}
