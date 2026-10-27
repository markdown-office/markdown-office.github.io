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

    /**
     * The slide a deck was written as, as the XML it holds.
     *
     * The XML rather than the file, because what a claim about a deck is about is what was
     * written into it: a colour, a weight, a size.
     */
    public static function slide(string $markdown, string $directory = '.'): string
    {
        $deck = (new DeckParser())->parse($markdown);
        $path = \sys_get_temp_dir() . '/markdown-office-deck-' . \bin2hex(\random_bytes(6)) . '.pptx';

        (new PresentationRenderer($directory))->render($deck, $path);

        return Package::part($path, 'ppt/slides/slide1.xml');
    }

    /**
     * The run of a slide that holds the given text, as the XML it holds.
     *
     * The run rather than the slide, because a slide carries the words around the one a
     * claim is about, and those are the ones that did not change.
     */
    public static function run(string $slide, string $text): string
    {
        \preg_match_all('#<a:r>.*?</a:r>#s', $slide, $matches);

        foreach ($matches[0] as $run) {
            if (\preg_match('#<a:t>(?:<!\[CDATA\[)?' . \preg_quote($text, '#') . '(?:\]\]>)?</a:t>#', $run) === 1) {
                return $run;
            }
        }

        return '';
    }

    /**
     * The slides of a deck, as the XML each of them holds.
     *
     * The slides rather than the deck, because a claim about a deck is usually a claim
     * about one of them: which shapes it carries, and in which order.
     *
     * @return list<string>
     */
    public static function slides(string $markdown, string $directory = '.'): array
    {
        $path = \sys_get_temp_dir() . '/markdown-office-deck-' . \bin2hex(\random_bytes(6)) . '.pptx';

        (new PresentationRenderer($directory))->render((new DeckParser())->parse($markdown), $path);

        $zip = new \ZipArchive();
        $zip->open($path);

        $slides = [];

        for ($number = 1; $number <= $zip->numFiles; $number++) {
            $name = $zip->getNameIndex($number - 1);

            if (\preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $match) === 1) {
                $slides[(int) $match[1]] = (string) $zip->getFromName($name);
            }
        }

        \ksort($slides);

        return \array_values($slides);
    }

    /**
     * The names of the shapes on every slide of a deck, in the order they are drawn.
     *
     * The names rather than the XML, because what a claim is about is which shapes a
     * slide carries and in which order — the chrome of a template is drawn over what the
     * slide placed itself, and that is an order.
     *
     * @return list<list<string>>
     */
    public static function shapes(string $markdown, string $directory = '.'): array
    {
        $names = [];

        foreach (self::slides($markdown, $directory) as $slide) {
            \preg_match_all('#<p:cNvPr id="\d+" name="([^"]*)"#', $slide, $matches);

            $names[] = \array_values(\array_filter($matches[1]));
        }

        return $names;
    }
}
