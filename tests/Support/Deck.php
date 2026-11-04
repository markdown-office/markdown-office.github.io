<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

use MarkdownPresentation\Document\DeckParser;
use MarkdownPresentation\Files\FileFinder;
use MarkdownPresentation\PowerPointToMarkdown;
use MarkdownPresentation\Render\PresentationRenderer;
use MarkdownPresentation\Reverse\Options;
use MarkdownPresentation\Theme\ThemeResolver;

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
    public static function slides(string $markdown, string $directory = '.', ?FileFinder $files = null): array
    {
        $zip = new \ZipArchive();
        $zip->open(self::render($markdown, $directory, $files));

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
     * The Markdown a deck is read back as.
     *
     * The other direction, and the only one that says what a deck carries: the file is
     * written, read, and the Markdown it is made of is what comes back.
     */
    public static function readBack(string $markdown, string $directory = '.'): string
    {
        return (new PowerPointToMarkdown(
            self::render($markdown, $directory),
            new Options(mediaDirectory: null),
        ))->convert();
    }

    /**
     * The name each layout part of a deck carries, by the part it is in.
     *
     * @return array<string, string>
     */
    public static function layouts(string $markdown, string $directory = '.'): array
    {
        $zip = new \ZipArchive();
        $zip->open(self::render($markdown, $directory));

        $layouts = [];

        for ($number = 1; $number <= $zip->numFiles; $number++) {
            $name = (string) $zip->getNameIndex($number - 1);

            if (\preg_match('#^ppt/slideLayouts/slideLayout(\d+)\.xml$#', $name) !== 1) {
                continue;
            }

            \preg_match('#<p:cSld[^>]*name="([^"]*)"#', (string) $zip->getFromName($name), $match);

            $layouts[$name] = $match[1] ?? '';
        }

        $zip->close();

        \ksort($layouts);

        return $layouts;
    }

    /**
     * The deck written out, and the path it was written to.
     *
     * The finder is the source of the deck's files when there is one: the theme, the
     * pictures and the templates file are asked for by name rather than looked for on
     * disk.
     */
    private static function render(string $markdown, string $directory, ?FileFinder $files = null): string
    {
        $path = \sys_get_temp_dir() . '/markdown-office-deck-' . \bin2hex(\random_bytes(6)) . '.pptx';

        $renderer = $files === null
            ? new PresentationRenderer($directory)
            : new PresentationRenderer($directory, themes: new ThemeResolver(files: $files), files: $files);

        $renderer->render((new DeckParser())->parse($markdown), $path);

        return $path;
    }
}
