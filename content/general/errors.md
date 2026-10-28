---
layout: layouts/base.njk
permalink: /errors/
title: Errors in general
summary: The exception to catch for each package, the two classes that share a name, and what the messages say about paths and losses.
section: In general
order: 3
---

An exception is only useful if it says what to do next. Every message in this suite names the
thing that would have worked instead, and the pages below are those messages in one place.

## The one to catch

**`MarkdownWord\Exception\InvalidConfiguration`** is the one type to catch for the Word half.
`UnknownConfigurationKey` covers the key half — a key nobody reads — and
`InvalidConfigurationValue` covers the rest. Both extend it, and both extend `Exception\InvalidInput`.

```php
use MarkdownWord\Exception\Exception as WordException;
use MarkdownWord\Exception\InvalidConfiguration;

try {
    (new MarkdownToWord($markdown))->save('out.docx');
} catch (InvalidConfiguration $error) {
    // Every problem at once, with line numbers and a "Did you mean".
    fwrite(STDERR, $error->getMessage());
}
```

**`MarkdownPresentation\Exception\Exception`** covers everything the presentation half reports,
so one `catch` does not also catch the failures of the libraries underneath. The failures a deck
author can fix by naming something else extend `Exception\InvalidInput` below it.

Five sit beside `InvalidInput` rather than under it, and nothing a deck names fixes any of them: a
file that cannot be read, a target that cannot be written, a conversion with nothing to convert, or
a dependency installed without the fixes the library needs.

## The two `UnknownDirective`

Two unrelated classes share the name. Importing the wrong one is a confusing afternoon:

| Class | Thrown when |
| --- | --- |
| `MarkdownOffice\CommonMark\Directive\Exception\UnknownDirective` | a name is not in a `DirectiveRegistry`, during parsing |
| `MarkdownPresentation\Exception\UnknownDirective` | a Slidev directive or marker is not drawn, during rendering |

Both extend `RuntimeException`; only the second extends `InvalidInput`.

## Messages repeat absolute paths

This is deliberate and there is no switch for it:

```text
Unable to read "ppt/presentation.xml" out of "/Users/you/deck.pptx".
```

The alternative is a message a machine cannot act on. A switch would be something somebody would
eventually get wrong — by turning it on in the place where the paths are harmless and off where
they are the only way to find the file.

## When something is lost rather than refused

Not everything is an exception. A feature a format cannot carry is reported rather than thrown:

```php
$converter = (new MarkdownToWord('notes.md'));

$converter->save('notes.docx');

foreach ($converter->pendingLosses() as $loss) {
    echo $loss->feature, ': ', $loss->message, "\n";
}
```

`.docx` carries everything, so `pendingLosses()` is empty. `.odt` and `.rtf` drop named features —
every list item left out of an RTF, an SVG flattened to a raster, a `::toc` left as its text, a text
box left out of the document — and the command line prints them to standard error rather than
letting the document quietly lose them.
