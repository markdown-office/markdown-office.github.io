---
layout: layouts/base.njk
permalink: /suite/
title: The suite
summary: Three packages with no facade between them, one command line over the two
section: The suite
order: 0
---

## There is no umbrella library, and one umbrella tool

The three packages are independent. There is no `markdown-office/office` that depends on all of
them, no shared base class, and no facade: no umbrella **library**. **A consumer installs what
they need** and calls the one class it wants.

```bash
composer require markdown-office/markdown-word
composer require markdown-office/markdown-presentation
composer require markdown-office/commonmark-generic-directives   # both of the above already have it
```

That is a deliberate choice rather than an unfinished one. A facade would have to choose a
configuration story for two packages that disagree about what an unrecognised directive should do,
and that choice would then be the only way to use them.

What there *is* is [`markdown-office/mdoffice`](https://github.com/markdown-office/mdoffice): one
umbrella **tool**, a single command line over both converters, requiring both packages and calling
them rather than reimplementing either.

**A shipped program is not a library.** It has no public API for converting anything — a document
is made by running it, not by calling a class — so nothing about it constrains how the packages
below are used on their own, and nothing there is a second way to reach `MarkdownToWord`. A facade
would have been exactly that, and would have carried the configuration choice above. This does
not.

```bash
mdoffice word notes.md      # → notes.docx
mdoffice slides deck.md     # → deck.pptx
```

The command line is documented in full under [The command line](/command-line/basic/).

## The three

| Package | Markdown in | Document out | Back to Markdown |
| --- | --- | --- | --- |
| `markdown-office/markdown-word` | CommonMark + GFM | `.docx` `.odt` `.rtf` | **yes**, for standard Markdown |
| `markdown-office/markdown-presentation` | Slidev-inspired | `.pptx` | **no** |
| `markdown-office/commonmark-generic-directives` | — | — | — a parser extension, on its own |

## What each one is

| Package | What it is |
| --- | --- |
| [`markdown-office/markdown-word`](/word/directives/) | Markdown to `.docx`, `.odt` and `.rtf`, and `.docx` back to Markdown |
| [`markdown-office/markdown-presentation`](/presentation/directives/) | Markdown to `.pptx`, with a Slidev-compatible splitter |
| [`markdown-office/commonmark-generic-directives`](/directives/syntax/) | the directive syntax, for your own parser if you want one |

Both converters require the directives package at the same constraint, `^0.3.0`, and both use it
permissively — no registry — because the presentation half claims `::click`, `::column` and
`:::click` itself, in front of the generic extension, since the generic grammar has no room for an
argument written after an equals sign.

<div class="coming">

### The spreadsheet is next

The third half will do the same thing to `.xlsx`: the same frontmatter, the same directive syntax,
the same refusals. It is not written yet, and this page says so rather than linking to a page that
is not there.

What it will share, because the other two do: the same configuration keys, the same way of being
loud about what it will not do, and a build where every example on the page is a workbook the
library actually produced.

</div>

## Versions and constraints

| | PHP | Notable |
| --- | --- | --- |
| `commonmark-generic-directives` | `^8.2` | needs `league/commonmark ^2.10` |
| `markdown-word` | `^8.2` | needs `ext-mbstring`, `ext-dom`, `ext-gd`, `ext-zip` |
| `markdown-presentation` | `^8.2` | needs `ext-dom`, `ext-gd`, `ext-zip` — **not** `ext-mbstring` |

Installing both means the intersection of their `symfony/yaml` constraints: `^7.4 || ^8.0`. A
project on 7.2 or 7.3 cannot install the pair together.

`ext-imagick` is in `suggest` for both, and buys SVG. See [Images](/images/).

The presentation package pins `phpoffice/phppresentation` to a **commit on `dev-master`**, not a
tag, because the fixes it needs are not in a release. That is why `composer validate` runs without
`--strict` there: the pin is a warning, and the warning is the point.

## The conventions they share

- MIT, and `declare(strict_types=1)` in every file.
- Every exception message names what would have worked instead.
- Every example in every README is a test.
- Both converters walk the **syntax tree**, never flattened HTML — so what lands in the document is
  the document the Markdown described.
