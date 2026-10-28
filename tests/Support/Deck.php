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
     */
    public static function refusal(string $markdown, string $directory = '.'): string
    {
        return Refusal::of(static fn (): string => self::render($markdown, $directory));
    }

    /**
     * The slide a deck was written as, as the XML it holds.
     *
     * The XML rather than the file, because what a claim about a deck is about is what was
     * written into it: a colour, a weight, a size.
     */
    public static function slide(string $markdown, string $directory = '.'): string
    {
        return Package::part(self::render($markdown, $directory), 'ppt/slides/slide1.xml');
    }

    /**
     * The run of a slide that holds the given text, as the XML it holds.
     */
    public static function run(string $slide, string $text): string
    {
        return Run::of($slide, $text, 'a');
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
        $zip = new \ZipArchive();
        $zip->open(self::render($markdown, $directory));

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

    /**
     * The deck written out, and the path it was written to.
     */
    private static function render(string $markdown, string $directory): string
    {
        $path = \sys_get_temp_dir() . '/markdown-office-deck-' . \bin2hex(\random_bytes(6)) . '.pptx';

        (new PresentationRenderer($directory))->render((new DeckParser())->parse($markdown), $path);

        return $path;
    }
}
