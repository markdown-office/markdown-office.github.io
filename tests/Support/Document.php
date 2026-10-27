<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use MarkdownOffice\CommonMark\Directive\GenericDirectiveExtension;
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

        (new MarkdownToWord($markdown, parser: self::parser(), directory: $directory))->save($path);

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
