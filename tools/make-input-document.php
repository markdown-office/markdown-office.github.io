#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Writes the document the site's Word-to-Markdown example reads.
 *
 *   php tools/make-input-document.php
 *
 * The round-trip guide reads a Word document back into Markdown, and the document it reads has
 * to be a file: a binary cannot be carried in a Markdown fence.
 *
 * It is written with PHPWord rather than with the library this site documents, because an example
 * that reads a document the same library wrote proves less than one that reads a document from
 * somewhere else. The document is otherwise ordinary — a heading, a paragraph with emphasis and
 * inline code and a link, a table, a list, a quote and a code block — because an example that
 * shows the reader handling nothing is not an example.
 *
 * The result is committed rather than built, because it is an input and not an output: it is the
 * one file on this site that must not change when the library does, or a reader comparing what
 * the reader makes of it has nothing to compare against.
 */

require_once __DIR__ . '/lib/Autoload.php';

use MarkdownOffice\Documentation\Tool\Cli;
use PhpOffice\PhpWord\PhpWord;

Cli::guard('The document is written from the command line.');
Cli::autoload();

$root = Cli::root();

$word = new PhpWord();
$section = $word->addSection();

$word->addNumberingStyle('Bullets', [
    'type' => 'multilevel',
    'levels' => [
        ['format' => 'bullet', 'text' => '•', 'left' => 720, 'hanging' => 360, 'tabPos' => 720],
        ['format' => 'bullet', 'text' => '◦', 'left' => 1440, 'hanging' => 360, 'tabPos' => 1440],
    ],
]);

$section->addTextRun('Heading1')->addText('Quarterly report');

$run = $section->addTextRun();
$run->addText('Some ');
$run->addText('strong emphasis', ['bold' => true]);
$run->addText(' and some ');
$run->addText('emphasis', ['italic' => true]);
$run->addText(', plus ');
$run->addText('inline code', ['name' => 'Consolas']);
$run->addText(' and a ');
$run->addLink('https://commonmark.org/', 'link', ['color' => '0563C1', 'underline' => 'single']);
$run->addText('.');

$section->addTextRun('Heading2')->addText('Highlights');

$table = $section->addTable([
    'border' => 'single',
    'borderSize' => 6,
    'borderColor' => 'BFBFBF',
    'cellMargin' => 80,
]);
$table->addRow();

foreach (['Region', 'Revenue', 'Change'] as $heading) {
    $table->addCell(1600)->addTextRun()->addText($heading, ['bold' => true]);
}

foreach ([['North', '1,204', '+8.2%'], ['South', '988', '-1.1%']] as [$region, $revenue, $change]) {
    $table->addRow();
    $table->addCell()->addTextRun()->addText($region);
    $table->addCell()->addTextRun()->addText($revenue);
    $table->addCell()->addTextRun()->addText($change);
}

$section->addTextRun('Heading2')->addText('Notes');

// The spacing is what makes the list a tight one, and a tight list is what the reader reads a
// Word list as. A document that asked for space between its items would come back loose, which
// is the reader being honest about the spacing it found.
$section->addListItemRun(0, 'Bullets', ['spaceBefore' => 0, 'spaceAfter' => 0])->addText('a list');
$section->addListItemRun(0, 'Bullets', ['spaceBefore' => 0, 'spaceAfter' => 0])->addText('with two items');

$section->addTextRun('IntenseQuote')->addText('A block quote, indented in the way Word indents it.');

$section->addTextRun()->addText('$x = 1;', ['name' => 'Consolas']);
$section->addTextRun()->addText('$y = 2;', ['name' => 'Consolas']);

$target = $root . '/_documents/quarterly-report.docx';

@\mkdir(\dirname($target), 0o777, true);
$word->save($target);
writeTheStyles($target);

\printf("Wrote %s\n", $target);

/**
 * Writes the document's stylesheet.
 *
 * PHPWord writes a style called `Heading1`, which is a style *called* Heading1 and not the
 * built-in heading 1: Word identifies a built-in style by the name in its definition, and spells
 * those `heading 1` … `heading 9`. A document whose names are wrong has no headings in it —
 * Word collects none for a table of contents, and a reader that resolves styles by name draws
 * them as body text.
 *
 * The ids stay as they are, because that is what a paragraph asks for: `Heading1` and
 * `Heading2` are what the document's paragraphs reference, and what a reader that looks at the
 * id reads as a heading. Only the definition changes — and with it the look, which PHPWord
 * drops: it writes a paragraph style's character properties nowhere, so a heading written
 * through it is a heading in name only.
 */
function writeTheStyles(string $document): void
{
    $styles = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>
        <w:sz w:val="22"/>
        <w:szCs w:val="22"/>
        <w:lang w:val="en-US"/>
      </w:rPr>
    </w:rPrDefault>
    <w:pPrDefault/>
  </w:docDefaults>
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:qFormat/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:basedOn w:val="Normal"/>
    <w:next w:val="Normal"/>
    <w:qFormat/>
    <w:pPr>
      <w:keepNext/>
      <w:spacing w:before="240" w:after="120"/>
      <w:outlineLvl w:val="0"/>
    </w:pPr>
    <w:rPr>
      <w:b/>
      <w:color w:val="2F5496"/>
      <w:sz w:val="32"/>
      <w:szCs w:val="32"/>
    </w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading2">
    <w:name w:val="heading 2"/>
    <w:basedOn w:val="Normal"/>
    <w:next w:val="Normal"/>
    <w:qFormat/>
    <w:pPr>
      <w:keepNext/>
      <w:spacing w:before="240" w:after="120"/>
      <w:outlineLvl w:val="1"/>
    </w:pPr>
    <w:rPr>
      <w:b/>
      <w:color w:val="2F5496"/>
      <w:sz w:val="26"/>
      <w:szCs w:val="26"/>
    </w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="IntenseQuote">
    <w:name w:val="Intense Quote"/>
    <w:basedOn w:val="Normal"/>
    <w:next w:val="Normal"/>
    <w:pPr>
      <w:spacing w:before="120" w:after="120"/>
      <w:ind w:left="720"/>
    </w:pPr>
    <w:rPr>
      <w:i/>
      <w:iCs/>
      <w:color w:val="404040"/>
    </w:rPr>
  </w:style>
</w:styles>
XML;

    $zip = new \ZipArchive();

    if ($zip->open($document) !== true) {
        \fwrite(\STDERR, \sprintf("Unable to open \"%s\" as a zip archive.\n", $document));
        exit(1);
    }

    try {
        $zip->deleteName('word/styles.xml');
        $zip->addFromString('word/styles.xml', $styles);
    } finally {
        $zip->close();
    }
}
