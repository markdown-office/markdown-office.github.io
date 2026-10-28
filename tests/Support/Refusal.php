<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

/**
 * The message the library gives for something it refuses.
 *
 * An empty string where nothing was refused, so a test that expects a message fails on
 * the absence of one rather than on something else.
 */
final class Refusal
{
    /**
     * The message the run gave, or an empty string where it gave none.
     */
    public static function of(callable $run): string
    {
        try {
            $run();
        } catch (\Throwable $error) {
            return $error->getMessage();
        }

        return '';
    }
}
