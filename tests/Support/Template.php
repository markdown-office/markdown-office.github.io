<?php

declare(strict_types=1);

namespace MarkdownOffice\Documentation\Tests\Support;

use MarkdownWord\Template\ContentControl;
use MarkdownWord\Template\MarkdownTemplate;
use PhpOffice\PhpWord\PhpWord;

/**
 * Word templates, built rather than carried.
 *
 * A template is a `.docx` with a content control in it and sometimes a `MERGEFIELD`, and
 * both are a few lines of XML — so they are built here rather than carried as a binary
 * fixture nobody can read.
 *
 * **The alias is left saying something else on purpose.** The library matches a control on
 * its tag, and the tag is the field name a program reads while the alias is the label a
 * person sees in Word's own interface and renames while editing the template. A template
 * whose two say different things is what proves which of them is matched.
 */
final class Template
{
    /**
     * The namespace Word's own XML uses.
     */
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * The part a Word document holds its body in.
     */
    private const BODY_PART = 'word/document.xml';

    /**
     * The part that says what the package holds.
     */
    private const TYPES_PART = '[Content_Types].xml';

    /**
     * The name the templates this builds are given.
     */
    private const STEM = '/markdown-office-template-';

    /**
     * The extension a Word document has.
     */
    private const EXTENSION = '.docx';

    /**
     * The extension a Word template has.
     */
    private const TEMPLATE_EXTENSION = '.dotx';

    /**
     * A template with a content control and a field for a value.
     */
    public static function withControl(string $tag = 'body', string $alias = 'Not the tag'): string
    {
        return self::copied(
            self::withControlMarkup(
                self::withFieldMarkup(self::base()),
                $tag,
                $alias,
            ),
            'with-control',
        );
    }

    /**
     * A template with neither a control nor a field: a letterhead a person made in Word.
     */
    public static function withoutControl(): string
    {
        return self::copied(self::base(), 'without-control');
    }

    /**
     * The same document with its body declared as a template's.
     *
     * A `.dotx` is a `.docx` with one line of `[Content_Types].xml` different, and there is
     * nothing in that line a binary fixture would show a reader.
     */
    public static function dotx(string $source): string
    {
        $path = \sys_get_temp_dir() . self::STEM . \bin2hex(\random_bytes(6)) . self::TEMPLATE_EXTENSION;

        \copy($source, $path);

        $zip = new \ZipArchive();
        $zip->open($path);

        $declared = (string) $zip->getFromName(self::TYPES_PART);

        $zip->deleteName(self::TYPES_PART);
        $zip->addFromString(
            self::TYPES_PART,
            \str_replace(
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml',
                $declared,
            ),
        );

        $zip->close();

        return $path;
    }

    /**
     * The document a template made, written out.
     */
    public static function written(MarkdownTemplate $template): string
    {
        $path = \sys_get_temp_dir() . '/markdown-office-written-' . \bin2hex(\random_bytes(6)) . self::EXTENSION;

        $template->save($path);

        return $path;
    }

    /**
     * The text a document holds, as the file holds it.
     */
    public static function textOf(string $path): string
    {
        return Package::part($path, self::BODY_PART);
    }

    /**
     * The body of a document, as the XML it was written as.
     */
    public static function xmlOf(string $path): string
    {
        return Package::part($path, self::BODY_PART);
    }

    /**
     * A `.docx` with a paragraph to put things into.
     */
    private static function base(): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addText('Northwind Ltd');
        $section->addText('@@reference@@');
        $section->addText('PLACEHOLDER');

        $path = \sys_get_temp_dir() . self::STEM . \bin2hex(\random_bytes(6)) . self::EXTENSION;

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($path);

        return $path;
    }

    /**
     * The document with the field written into it.
     */
    private static function withFieldMarkup(string $path): string
    {
        return self::rewrite($path, static function (\DOMDocument $dom): void {
            $paragraph = self::paragraphWith($dom, '@@reference@@');

            if ($paragraph === null) {
                return;
            }

            $field = $dom->createElementNS(self::W_NS, 'w:fldSimple');
            $field->setAttributeNS(self::W_NS, 'w:instr', ' MERGEFIELD reference \\* MERGEFORMAT ');

            $run = $dom->createElementNS(self::W_NS, 'w:r');
            $text = $dom->createElementNS(self::W_NS, 'w:t');
            $text->appendChild($dom->createTextNode('«reference»'));

            $run->appendChild($text);
            $field->appendChild($run);

            $paragraph->parentNode?->replaceChild($field, $paragraph);
        });
    }

    /**
     * The document with the control written into it.
     */
    private static function withControlMarkup(string $path, string $tag, string $alias): string
    {
        $markup = ContentControl::markup($tag);

        if ($alias !== '') {
            $markup = \str_replace(
                '<w:alias w:val="' . $tag . '"/>',
                '<w:alias w:val="' . $alias . '"/>',
                $markup,
            );
        }

        return self::rewrite($path, static function (\DOMDocument $dom) use ($markup): void {
            $paragraph = self::paragraphWith($dom, 'PLACEHOLDER');

            if ($paragraph === null) {
                return;
            }

            $fragment = new \DOMDocument();
            $fragment->loadXML(
                '<w:wrap xmlns:w="' . self::W_NS . '">' . $markup . '</w:wrap>',
            );

            $control = $fragment->documentElement?->firstChild;

            if ($control === null) {
                return;
            }

            $imported = $dom->importNode($control, true);
            $paragraph->parentNode?->replaceChild($imported, $paragraph);
        });
    }

    /**
     * The paragraph of a document that holds the given text.
     */
    private static function paragraphWith(\DOMDocument $dom, string $text): ?\DOMElement
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::W_NS);

        foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
            if ($paragraph->textContent === $text) {
                \assert($paragraph instanceof \DOMElement);

                return $paragraph;
            }
        }

        return null;
    }

    /**
     * The document, rewritten part by part.
     */
    private static function rewrite(string $path, callable $change): string
    {
        $zip = new \ZipArchive();
        $zip->open($path);

        $xml = (string) $zip->getFromName(self::BODY_PART);
        $zip->close();

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $change($dom);

        $written = \sys_get_temp_dir() . self::STEM . \bin2hex(\random_bytes(6)) . self::EXTENSION;

        \copy($path, $written);

        $out = new \ZipArchive();
        $out->open($written);
        $out->deleteName(self::BODY_PART);
        $out->addFromString(self::BODY_PART, (string) $dom->saveXML());
        $out->close();

        return $written;
    }

    /**
     * A copy of a document under a name of its own.
     */
    private static function copied(string $path, string $name): string
    {
        $written = \sys_get_temp_dir() . '/markdown-office-' . $name . '-' . \bin2hex(\random_bytes(6)) . self::EXTENSION;

        \copy($path, $written);

        return $written;
    }
}
