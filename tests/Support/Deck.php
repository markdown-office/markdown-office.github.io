<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

use MarkdownPresentation\Document\DeckParser;
use MarkdownPresentation\Render\PresentationRenderer;

/**
 * Decks, and the messages the library gives for the ones it refuses.
 */
final class Deck
{
    /**
     * The message the library gives for a deck it refuses.
     *
     * An empty string where the deck was not refused at all, so a test that expects a
     * message fails on the absence of one rather than on something else.
     */
    public static function refusal(string $markdown, string $directory = '.'): string
    {
        try {
            $deck = (new DeckParser())->parse($markdown);

            (new PresentationRenderer($directory))
                ->render($deck, (string) \tempnam(\sys_get_temp_dir(), 'deck') . '.pptx');
        } catch (\Throwable $error) {
            return $error->getMessage();
        }

        return '';
    }
}
