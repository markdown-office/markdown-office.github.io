---
layout: layouts/base.njk
permalink: /presentation/
title: Markdown Presentation
summary: Slidev-inspired Markdown into .pptx — layouts, themes, click builds and speaker notes, extended with directives. And back again.
section: Markdown Presentation
order: 0
---

The dialect and the library. The command line for both halves is
[`mdoffice`](https://github.com/markdown-office/mdoffice); this page is about what you get
without it.

```bash
composer require markdown-office/markdown-presentation
```

PHP 8.2 or newer, with `ext-dom`, `ext-gd` and `ext-zip`. `ext-imagick` is optional and buys SVG.

**Both directions.** Markdown to `.pptx`, and a `.pptx` back to Markdown — what comes back is
the Markdown this package writes decks in, so a deck can be taken apart, edited as text and put
in again under a different theme. `PresentationRenderer` has five methods, `render()`,
`overflows()`, `unknownFonts()`, `withTheme()` and `toString()`; the reader is
`PowerPointToMarkdown`, with `convert()`, `save()`, `toMarkdown()` and `read()`. The two that
report rather than write are `overflows()` — every block that had to be made smaller than it
asked for — and `unknownFonts()` — every face the deck named that there is no table of advance
widths for. What the round trip does not keep is written down in
[Reading a deck back](#reading-a-deck-back).

---

## 1. The Markdown dialect

### Basic

**Slidev-inspired.** A deck is Markdown split on `---`. The splitter is a **port of Slidev's
`parseSync`** pinned to a commit, so a deck written for Slidev splits the same way here; the 21
places it deliberately does not are written down in
[Slidev, and where it differs](/presentation/directives/#slidev-and-where-it-differs).

{% example "A deck with a title slide and a content slide" %}
````md presentation
# Markdown Office

Convert a Markdown document into a deck that opens in PowerPoint.

---

## Why a slide and not a page

- One `---`, one slide
- A theme decides the type, the colour and the grid
- `::click` builds the slide up on a click
````
{% endexample %}

Within a slide it is CommonMark plus tables, strikethrough and fenced code. `---` separates
slides and **cannot be a thematic break** — use `***`.

`----` is also a delimiter, so a YAML document as slide content needs a different fence.

### Extended: frontmatter and themes

The deck's own configuration goes in the frontmatter of slide 1. Every key is in a **closed set of
nine**, and anything outside it fails the build with a message naming what would have worked. The
nine are listed with the [directives](/presentation/directives/#frontmatter-directives).

{% example "The four keys that describe a deck" %}
````md presentation
---
theme: corporate-dark
title: The quarter in three slides
author: Markdown Office
description: What changed, what it cost, and what happens next.
---

# The quarter

## What changed

- Two products shipped
- Three customers renewed
````
{% endexample %}

#### A theme

A theme is a directory, and `theme.yml` is what it has to hold: the name, an optional description
and author, the colours, the fonts and the geometry. A `theme:` may also name the `theme.yml`
itself, in which case the theme's other files are the ones beside it. The keys it takes, the
colour slots, the geometry and the themes that ship are all on
[Themes and layout](/presentation/themes/).

Resolution is a path, then `themes/<name>` beside the deck, then the package's own. **No silent
fallback to `default`**: a name that does not resolve raises `ThemeNotFound` listing the ones that
do.

#### A template

A **template** is the furniture of a deck — the wordmark (a company's name on a slide, with its
mark), the rule, the page number — written
once and drawn on every slide that names it. It is a slide of the deck's own Markdown, marked by
its own frontmatter, and it is not a slide of the deck: it does not appear and it does not count
towards the number of slides. See [Presentation directives](/presentation/directives/#slide-templates).

### Extended: directives

Four markers, and the set is closed — anything outside it is refused by name rather than drawn as a
paragraph.

| Written | Does |
| --- | --- |
| `::click` | advances the build by one step |
| `::click=N` | advances to step `N` |
| `:::click` … `:::` | everything between the fences is at one step |
| `::column` | moves the next blocks into the next column |
| `::note` … `::` | the slide's speaker note |

All must be alone on their line. Names are case-insensitive.

**`:name[…]`** is the inline one — a word in one of the theme's named styles, in the middle of a
sentence. It is the only inline directive a deck has, and a name the theme does not have is
refused by name like any other marker: [the text styles](/presentation/directives/#a-word-in-a-style).

{% example "A slide that builds up, with a speaker note" %}
````md presentation
## Three things

- One
- Two

::click

- Two is the interesting one
- Three

::click

- All three

::note
Say "two" slowly. That is the point of the slide.
::
````
{% endexample %}

`::click=N` must be greater than the current step and no more than **500**. Step 1 is not a click
— it is what is on the slide before anyone clicks — so a step nothing was written on costs no
click.

`::column` takes **no argument**; the layout decides how many columns there are, and writing more
markers than that is `UnsupportedColumn`.

The closer for `:::click` is **exactly three colons**. An unclosed region is refused rather than
running to the end of the slide.

More, including the refusal table and the three things that look like they work and do not:
[Presentation directives](/presentation/directives/).

---

## 2. Command line

**The package declares no `bin`**, and `examples/build.php` in the repository is
the closest thing — it converts a directory of Markdown files and renders page images of each.

A shell loop is the whole substitute:

```bash
for f in decks/*.md; do
  php -r 'require "vendor/autoload.php";
    (new MarkdownPresentation\Render\PresentationRenderer())
      ->render((new MarkdownPresentation\Document\DeckParser())->parse(file_get_contents($argv[1])), $argv[2]);' \
    "$f" "${f%.md}.pptx"
done
```

The suite has a command line for both halves — see
[The command line](/command-line/basic/).

---

## 3. As a library

Two calls, and there is no single facade class.

```php
use MarkdownPresentation\Document\DeckParser;
use MarkdownPresentation\Render\PresentationRenderer;

$deck = (new DeckParser())->parse($markdown);

(new PresentationRenderer())->render($deck, 'deck.pptx');
```

### Reading the deck without writing it

Useful for a linter, an index, or a check that runs before anything is built:

```php
$deck = (new DeckParser())->parse($markdown);

echo $deck->frontmatterValue('title'), "\n";
echo $deck->count(), " slides\n";

foreach ($deck->slides() as $slide) {
    echo $slide->index(), ' ', $slide->title(), "\n";
    echo $slide->note(), "\n";
}
```

```text
0 One
1 Two
```

### Reading a deck back

The other direction: a `.pptx` becomes the Markdown this package writes decks in, so a deck
somebody else made can be taken apart, edited as text, and put back together in another
design.

```php
use MarkdownPresentation\PowerPointToMarkdown;
use MarkdownPresentation\Reverse\Options;

echo (new PowerPointToMarkdown('deck.pptx', new Options(mediaDirectory: null)))->convert();
```

```text
---
title: "Untitled Presentation"
author: "markdown-office"
---

# One

Text.
```

The frontmatter carries what the deck said about itself — its title, its author and its
description — because a deck's own properties are the only thing about it that is not on a
slide. `save()` writes it to a file instead, and `read()` gives the slides back as a list.

What comes back is the text and the structure. What does not is the design: the type sizes,
the colours and the geometry live in the theme and the master, and neither travels to a new
deck. A `.pptx` records what is on a slide, not how it was drawn.

| In the deck | In the Markdown |
| --- | --- |
| the title placeholder | the heading of the slide |
| the subtitle placeholder | a paragraph under it |
| a body placeholder | a list |
| a text box | a paragraph |
| a picture | an image, written out beside the deck |
| a table | a Markdown table |
| the speaker's note | a `::note` block |
| the date, the footer and the slide number | nothing |

What the round trip does not keep is written down in the package's own
[README](https://github.com/markdown-office/markdown-presentation#reading-a-deck-back): the
design, the layout, the underline, a text style, a link inside a run, and a chart.

### Where a deck's files come from

The theme a deck names, the pictures it shows and the templates file it imports are read from disk
by default. A program that keeps its own files somewhere else — in a database, say — says where
they are with a **finder**, and hands it over:

```php
use MarkdownPresentation\Render\PresentationRenderer;
use MarkdownPresentation\Theme\ThemeResolver;

$renderer = new PresentationRenderer(
    themes: new ThemeResolver(files: $finder),
    files: $finder,
);
```

What a finder is, what the three roles are, and why the scope check stays with the library are on
[Where the files come from](/files/).

### The rendering pipeline

`render()` checks every slide's frontmatter **before** anything is built, so a deck whose fourth
slide names a directive nobody reads fails before it has written a byte. It then resolves the
theme, resolves the layout roles, builds the masters, and writes one slide per source slide.

Five passes run over the written package afterwards — `NotesColourMapPass`, `NotesChainPass`,
`MeasureRoundingPass`, `PlaceholderPass` and `TimingPass` — because PHPPresentation cannot write
what PowerPoint needs. The rounding pass exists because a fractional `a:spcPts` value makes
PowerPoint open the deck as `[Repaired]`.

### Ceilings

<!-- reference: presentation.ceilings -->

| | |
| --- | --- |
| `MAX_SLIDES` | 1000 |
| `MAX_STEP` | 500 |
| `MAX_PIXELS` | 16,777,216 — 4096 × 4096, enforced on the file header |
| `MAX_BYTES` | 1,048,576 — the size of a templates file |
| `MAX_SHAPES` | 100 — the shapes one slide may carry |
| `MAX_PART_BYTES` | 4,194,304 — one part of a theme's template |

The pixel ceiling is worth a moment. The obvious implementation of it is wrong: measuring a
raster means decoding it, and decoding is what allocates the memory you were trying to bound. Both
halves read the size from the header without drawing, so a **152-byte** file declaring a
16000-square drawing is refused in 0.05 s with a 4 MB peak.

More, and the reason there is no second line of defence: [Images](/images/).

### Errors

`MarkdownPresentation\Exception\Exception` covers everything this half reports, so one `catch` does
not also catch the failures of the libraries underneath. The failures a deck author can fix extend
`Exception\InvalidInput` below it.

```php
use MarkdownPresentation\Exception\Exception as DeckException;
use MarkdownPresentation\Exception\InvalidInput;

try {
    (new PresentationRenderer())->render($deck, 'out.pptx');
} catch (InvalidInput $error) {
    fwrite(STDERR, $error->getMessage());   // names what would have worked
}
```

Six sit beside `InvalidInput` rather than under it: nothing a deck names fixes a file that cannot
be read, a target that cannot be written, a conversion with nothing to convert, a dependency
installed without the fixes the library needs, or a source that cannot serve the master a theme
carries.

The whole catalogue is on [Errors](/presentation/errors/).

---

## Next

[Markdown Word](/word/) · [The directive syntax](/directives/syntax/) ·
[Presentation directives](/presentation/directives/) · [Themes and layout](/presentation/themes/) ·
[Images](/images/) · [Where the files come from](/files/) · [Errors](/presentation/errors/)