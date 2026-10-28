<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

use MarkdownWord\WordToMarkdown;

/**
 * The things a round trip does not preserve, and what the reader makes of them.
 *
 * The reader is asked, and its answer is what the page has to say: a thing that comes
 * back whole is not a thing that does not survive. The page's count of them is the
 * number of answers that say so, not what the page lists.
 */
final class RoundTrip
{
    /**
     * The things the reader does not preserve, under the heading the page writes each
     * of them.
     *
     * @return list<string>
     */
    public static function losses(): array
    {
        $lost = [];

        foreach (self::documents() as $heading => [$document, $found]) {
            $back = (new WordToMarkdown(Document::path($document)))->convert();

            expect($back)->toBe($found);

            $lost[] = $heading;
        }

        return $lost;
    }

    /**
     * The documents that show each of the losses, and the Markdown the reader makes of
     * them.
     *
     * @return array<string, array{string, string}>
     */
    private static function documents(): array
    {
        return [
            "A table's header row comes back bold" => [
                "| A | B |\n| --- | --- |\n| 1 | 2 |\n",
                "| **A** | **B** |\n| :-- | :-- |\n| 1 | 2 |",
            ],
            'A fenced code block comes back without its language' => [
                "```php\n\$x = 1;\n\$y = 2;\n```\n",
                "```\n\$x = 1;\n\$y = 2;\n```",
            ],
            'A one-line code block comes back as inline code' => [
                "```php\n\$x = 1;\n```\n",
                '`$x = 1;`',
            ],
            'A quote configured as indentation comes back as a paragraph' => [
                "---\nstyles:\n  blockQuote: null\n---\n\n> A quote.\n",
                'A quote.',
            ],
            'The last line has no newline' => [
                "# Title\n",
                '# Title',
            ],
            'A delimiter row is rewritten' => [
                "| A | B |\n| :--- | ---: |\n| 1 | 2 |\n",
                "| **A** | **B** |\n| :-- | --: |\n| 1 | 2 |",
            ],
        ];
    }
}
