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

Two files go into the directory.

| File | Holds |
| --- | --- |
| `theme.yml` | the colours, the fonts, the type scale and the layout roles |
| `template.pptx` | the deck's own layouts, without its slides |

A deck with no master has no template to write, so the directory holds the theme's file alone.

**Every role is mapped.** The roles the deck's own layouts cover are named the deck's way, and
the rest take the library's own names — the names the library draws the layouts it generates
under. A theme's `layouts:` section is the whole mapping, so a role it leaves out is a role no
slide can be drawn on, and the roles the deck has no layout for are reported rather than
pointed at a layout that is something else.

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
layouts whatever it likes, and the same layout is called different things in two offices. The
roles the deck has no layout for are reported rather than pointed at a layout that is something
else.

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
mdoffice theme: wrote the theme for "probe.pptx" into "/tmp/probe-theme".

  theme.yml     the colours, the fonts, the type scale and the layout roles
  template.pptx the deck's 3 layouts, without its 2 slides

  colours
    dk1        #0D1B2A   the background of the slides
    lt1        #F8FAFC   the type
    accent1    #00B4D8   the colour the deck draws with
    hlink      #00B4D8   the accent, as a link is drawn

  type
    heading        Cambria                  the face of the type at the title size
    body           Calibri                  the face the deck writes its prose in
    title          26pt                     the largest type on most of the slides
    cover          26pt                     the largest type on the first slide
    section        26pt                     the title on a slide that carries little else
    body size      14pt                     the size the deck writes its prose at
    headings       14pt                     the sizes below the title
    leading        1.0                      the line spacing the deck sets
    heading weight regular                  the weight of the headings
    heading ink    ink                      the colour the headings are drawn in

  roles
    title       Titelfolie                 a title and a subtitle
    content     Titel und Inhalt           a title and one column
    two-column  Two Content                the library's own name
    section     Section Header             the library's own name
    blank       Leer                       nothing in it at all
    image-text  Picture Left               the library's own name
    image-title Picture with Caption       the library's own name
    image-slide Full Bleed Picture         the library's own name

  the deck's own layouts map 3 of the 8 roles; the library's own names map the other 5.

  left at the library's default
    colours  dk2 #44546A, lt2 #E7E6E6, accent2 #ED7D31, accent3 #A5A5A5, accent4 #FFC000, accent5 #5B9BD5, accent6 #70AD47, folHlink #954F72
    type     margin, gutter, block, rule, mono

  the deck has no layout for
    two-column, section, image-text, image-title, image-slide

  the template places 0 of the 3 roles the deck's own layouts map.
```

A deck with no master has no template, and the report says so where the template's line would
be:

```text
  the deck has no master, so the theme carries no template.
```

The report goes to standard output, and the theme it writes is a directory the slides part
reads: put it in `themes/<name>/` beside a deck and name it with `theme:`. The theme format is
the [presentation library's](/presentation/themes/), and the [layout roles](/presentation/themes/#layout-roles)
are the ones it defines.
