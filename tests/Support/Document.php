<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use MarkdownOffice\CommonMark\Directive\GenericDirectiveExtension;
use MarkdownWord\Format;
use MarkdownWord\Loss;
use MarkdownWord\MarkdownToWord;
use MarkdownWord\Parser\CommonMarkParser;

/**
 * Word documents, and the XML they hold.
 */
final class Document
{
    /**
     * A document built from the Markdown, and the path it was written to.
     */
    public static function path(string $markdown, ?string $directory = null): string
    {
        $path = \sys_get_temp_dir() . '/markdown-office-document-' . \bin2hex(\random_bytes(6)) . '.docx';

        self::converter($markdown, $directory)->save($path);

        return $path;
    }

    /**
     * The body of a document, as the XML it was written as.
     */
    public static function body(string $markdown, ?string $directory = null): string
    {
        return Package::part(self::path($markdown, $directory), 'word/document.xml');
    }

    /**
     * The run of a document that holds the given text, as the XML it holds.
     *
     * The run rather than the body, because a body carries the words around the one a claim
     * is about, and those are the ones that did not change.
     */
    public static function run(string $body, string $text): string
    {
        \preg_match_all('#<w:r(?: [^>]*)?>.*?</w:r>#s', $body, $matches);

        foreach ($matches[0] as $run) {
            if (\preg_match('#<w:t(?: [^>]*)?>' . \preg_quote($text, '#') . '</w:t>#', $run) === 1) {
                return $run;
            }
        }

        return '';
    }

    /**
     * The message the library gives for a document it refuses.
     *
     * An empty string where the document was not refused at all, so a test that expects a
     * message fails on the absence of one rather than on something else.
     */
    public static function refusal(string $markdown, ?string $directory = null): string
    {
        try {
            self::path($markdown, $directory);
        } catch (\Throwable $error) {
            return $error->getMessage();
        }

        return '';
    }

    /**
     * The losses a conversion into a format reports, as the feature keys.
     *
     * The keys rather than the sentences, because a claim about a loss is a claim about
     * which feature was lost: the sentence is prose, and the key is what the library
     * and the page both name.
     *
     * @return list<string>
     */
    public static function losses(string $markdown, Format $format, ?string $directory = null): array
    {
        $converter = self::converter($markdown, $directory);

        $converter->convertTo($format, (string) \tempnam(\sys_get_temp_dir(), 'document') . $format->extension());

        return \array_map(
            static fn (Loss $loss): string => $loss->feature,
            $converter->pendingLosses(),
        );
    }

    /**
     * The text a document written in a format holds, as the file holds it.
     *
     * The part that holds the text rather than the whole package: an `.odt` is a zip and
     * its text is deflated into `content.xml`, and a `.rtf` is a plain file whose text is
     * in it. What a claim is about is whether the words are there.
     */
    public static function written(string $markdown, Format $format, ?string $directory = null): string
    {
        $path = (string) \tempnam(\sys_get_temp_dir(), 'document') . $format->extension();

        self::converter($markdown, $directory)->convertTo($format, $path);

        if ($format === Format::Rtf) {
            return (string) \file_get_contents($path);
        }

        $zip = new \ZipArchive();
        $zip->open($path);

        return (string) $zip->getFromName($format === Format::Odt ? 'content.xml' : 'word/document.xml');
    }

    /**
     * A converter for the Markdown.
     *
     * The directory is named only where there is one: a document that has no file of its
     * own beside it has nothing to resolve a path against.
     */
    private static function converter(string $markdown, ?string $directory): MarkdownToWord
    {
        return $directory === null
            ? new MarkdownToWord($markdown, parser: self::parser())
            : new MarkdownToWord($markdown, parser: self::parser(), directory: $directory);
    }

    /**
     * The parser the site builds its examples with.
     *
     * The frontmatter is read because the default parser does not read it, and a document
     * that names a theme in its frontmatter would then be drawn in no theme at all. The
     * directives are read because the frontmatter alone would leave them out of it.
     */
    private static function parser(): CommonMarkParser
    {
        return new CommonMarkParser([
            GithubFlavoredMarkdownExtension::class,
            GenericDirectiveExtension::class,
            FrontMatterExtension::class,
        ]);
    }
}
