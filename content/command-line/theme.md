---
layout: layouts/base.njk
permalink: /command-line/theme/
title: For themes
summary: What `mdoffice theme init` reads out of a deck, what it leaves to the library, and what it writes.
section: The command line
order: 4
---

The theme part converts nothing. It reads a deck and writes a theme directory — a first draft of
a theme, meant to be edited. The other parts are on [For word](/command-line/word/) and [For
presentations](/command-line/presentations/).

```bash
mdoffice theme init deck.pptx          # writes deck/
mdoffice theme deck.pptx                # the same run: init is the default
```

## Options for `init`

| Option | Value | Default |
| --- | --- | --- |
| `-o, --output <directory>` | path | beside the deck |
| `--force` | flag | off |
| `-h, --help` | flag | off |

The directory is the deck's own name without its extension, so `deck.pptx` gives `deck/` and
`talks/talk.pptx` gives `talks/talk/`.

**A theme that is already there is not replaced without being asked.** A theme is a directory of
files somebody has edited, and replacing one is how an afternoon of work goes missing:

```text
mdoffice theme: There is already a theme in "deck".
mdoffice theme:   Pass --force to replace it.
```

## What is written

Three files go into the directory.

| File | Holds |
| --- | --- |
| `theme.yml` | the colours, the fonts and the type scale |
| `layouts.yml` | the layout roles, each pointed at a layout of the deck |
| `template.pptx` | the deck's own layouts, without its slides |

**The theme is read back before the command reports success.** The library's own reader reads it
and the library's own geometry reads the boxes of the template, so a theme this command cannot
read is a failure here rather than a theme nobody can use.

## What is read out of the deck

**The colours** come from the deck's own theme part where the deck is drawn in that theme, and
from the deck's slides where it is not. A deck made in a tool that does not do themes carries
Office's palette without ever drawing in it, and that palette describes nothing on the page.

**The type scale** is read off the deck's text, because a deck records a size for every
paragraph rather than a scale. The title is the size most slides are headed in, the cover the
largest type on the first slide, the body the size the prose is written at, and the heading
scale the sizes below the title.

**The layouts** are mapped by the placeholders they hold, not by their names: a deck calls its
layouts whatever it likes, and the same layout is called different things in two offices. A role
the deck has no layout for is reported rather than pointed at a layout that is something else.

**The template** is the deck's layouts without its slides, and without the pictures on them. A
template places, and the slides of the deck it came from are not a placement.

## What is left to the library

The spacing and the monospace face are the library's own. A deck records where its boxes are,
not the rhythm it draws with, and it says nothing about a face it never uses. Every value the
deck did not answer is left at the library's default and named in the report, so the numbers in
the file can be told apart from the numbers somebody chose.

## The report

The command says what it read and where it read it from:

```text
mdoffice theme: wrote the theme for "deck.pptx" into "deck".

  theme.yml     the colours, the fonts and the type scale
  layouts.yml   1 of the 8 layout roles mapped
  template.pptx the deck's 1 layouts, without its 3 slides

  colours
    dk1        #000000   the type
    lt2        #000000   a filled block
```

The report goes to standard output, and the theme it writes is a directory the slides part
reads: put it in `themes/<name>/` beside a deck and name it with `theme:`. The theme format is
the [presentation library's](/presentation/themes/), and the [layout roles](/presentation/themes/#layout-roles)
are the ones it defines.
