<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tool;

/**
 * What a check found, and how it says so.
 *
 * Every check collects the things it found wrong and prints them the same way: one
 * problem at a time, indented, with the lines of a message broken so that a long one
 * reads as a paragraph rather than as a wall.
 */
final class Report
{
    /**
     * @var list<string>
     */
    private array $failures = [];

    /**
     * A thing that is wrong.
     */
    public function fail(string $message): void
    {
        $this->failures[] = $message;
    }

    /**
     * Whether anything is wrong.
     */
    public function failed(): bool
    {
        return $this->failures !== [];
    }

    /**
     * What was found, on standard error, one problem at a time.
     *
     * @param string $subject What was checked, for the line the problems are counted under.
     */
    public function print(string $subject): void
    {
        \fwrite(\STDERR, \sprintf("%d problem(s) with the %s:\n\n", \count($this->failures), $subject));

        foreach ($this->failures as $failure) {
            \fwrite(\STDERR, '  ' . \str_replace("\n", "\n  ", $failure) . "\n\n");
        }
    }
}
