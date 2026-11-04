---
layout: layouts/base.njk
permalink: /presentation/themes/
title: Themes and layout
summary: The twelve colour slots, the twelve style keys, the eight layout roles, and the themes that ship with the presentation package.
section: Markdown Presentation
order: 2
---

A theme is **one file**, plus a master if it has one. `theme.yml` holds the look, the roles the
theme maps and the named styles a deck may write in, and `template.pptx` beside it is the
master: the PowerPoint file that says where the theme's layouts put their boxes. A theme
without one is drawn with the library's own geometry.

```text
themes/corporate-dark/
├── theme.yml      the look, the roles and the named styles
└── template.pptx  optional, the master
```

## `theme.yml`

<!-- reference: presentation.theme-keys -->

| Key | Type | Default | Notes |
| --- | --- | --- | --- |
| `name` | string | — | **required**, non-empty |
| `description` | string | `null` | |
| `author` | string | `null` | |
| `dark` | boolean | `false` | boolean only — `dark: yes` is refused |
| `colors` | mapping | see below | `#rrggbb`, stored upper-cased — **quoted**, or YAML reads the `#` as a comment |
| `fonts` | mapping | Calibri / Calibri / Consolas | `heading`, `body`, `mono` — see [The three faces](#the-three-faces) |
| `style` | mapping | see below | the geometry |
| `layouts` | mapping | the library's own | which layout each role maps to — see [Layout roles](#layout-roles) |
| `textStyles` | mapping | none | the names a deck writes words in — see [Text styles](#text-styles) |

Five of them hold a mapping. A key is written with a colon in the file whatever it holds,
and the table names the keys as they are — the colon is YAML's and not theirs.

`dark: yes` being refused is not pedantry. YAML 1.1 truthy strings are what make a theme written
by hand behave differently from one written by a machine, and this one is for machines.

## The files a theme used to be made of

A theme was a directory of up to five files, and the three that are no longer read are
**refused** when they are beside the theme, each with a message that says what to do with it:

> The theme "acme" has a "layouts.yml" beside it. A theme is one file: move its content into the "layouts:" section of its "theme.yml", and delete the file.

> The theme "acme" has a "text-styles.yml" beside it. A theme is one file: move its content into the "textStyles:" section of its "theme.yml", and delete the file.

> The theme "acme" has a "styles.yml" beside it. That file is the Word half's, and this half does not read it: its slots name Word styles, which a deck of slides has no use for. Delete it, and the theme is read again.

`layouts.yml` and `text-styles.yml` are the two that became sections of the theme's own file.
`styles.yml` was the Word half's, and this half loaded it for years without ever using it.

They are refused rather than ignored, because a file that is quietly not read is a theme that
looks like the one its author wrote and is not: the author renames a layout in the file
beside the theme, the deck is drawn with the old mapping, and nothing in the output says so.

## A whole theme

One file, with every section in it:

```yaml
# themes/mine/theme.yml
name: Resilience Bakery
description: The house look.
author: Markdown Office
dark: true
colors:
  dk1: "#1E1E2E"
  lt1: "#F5F3EE"
  accent1: "#C25E00"
fonts:
  heading: "Avenir Next"
  body: "Charter"
  mono: "Fira Code"
style:
  margin: 48
  gutter: 24
  body: 18
  title: 32
  heading-weight: bold
  heading-ink: accent
layouts:
  title: Titelfolie
  content: Titel und Inhalt
  two-column: Zwei Spalten
  section: Abschnitt
  blank: Leer
  image-text: Bild links
  image-title: Bild mit Titel
  image-slide: Ganzflächiges Bild
textStyles:
  brand:
    color: accent1
    bold: true
  muted:
    color: lt2
    size: 10
```

**Every colour is quoted.** YAML reads an unquoted `#` as the start of a comment, so
`dk1: #1E1E2E` is a key with no value at all — and the theme is refused for it:

> In "…/theme.yml": "dk1" is not a colour such as "#1E1E2E".

The `layouts:` section names all eight roles, because a theme with a `layouts:` section has the
whole mapping: a role it does not name is a role the theme does not map. `textStyles:` is
optional, and so is everything else here except `name:`.

## A slide's own values

A slide may name the theme's values for itself, in its frontmatter, and what it names is a
`theme.yml`: the four keys that hold a value — `dark`, `colors`, `fonts` and `style` — read by
the reader that reads the theme's own file. The frontmatter key that carries it is
**`theme-variables:`**, and every slide may carry one — the
[frontmatter table](/presentation/directives/#frontmatter-directives) lists it among the
sixteen. The three that hold the theme's identity —
`name`, `description` and `author` — are not a slide's, so naming one is refused, and so are
`layouts` and `textStyles`: those are the theme's own shape rather than anything a slide may
lay over it.

A slide's values are laid over the template's, and the template's over the deck's, and the
deck's over the theme's — and one value laid over another leaves the rest alone: a slide that
names one colour leaves the other eleven as they were.

## The twelve colour slots

<!-- reference: presentation.theme-colours -->

```text
dk1 lt1 dk2 lt2 accent1 accent2 accent3 accent4 accent5 accent6 hlink folHlink
```

Defaults: `#000000` `#FFFFFF` `#44546A` `#E7E6E6` `#4472C4` `#ED7D31` `#A5A5A5` `#FFC000`
`#5B9BD5` `#70AD47` `#0563C1` `#954F72`.

Six read-friendly aliases are accepted — the spellings the concept draft used:

<!-- reference: presentation.theme-aliases -->

```text
dark1  light1  dark2  light2  hyperlink  followedHyperlink
```

`dark: true` swaps four of them — the page becomes `dk1`, the ink `lt1`, the panel `dk2`, the
subtle ink `lt2` — which is why the same theme can be dark without being a second theme.

## The three faces

A theme names three of them, and each is a slot rather than a family:

| Slot | The face it is |
| --- | --- |
| `heading` | the heading and the title |
| `body` | the prose |
| `mono` | the code |

**A name that is none of the three is a family as written.** A theme that says `body: Charter`
draws its prose in Charter, and a style that says `font: Inter` is drawn in Inter whether or
not the theme names it.

**A text style's `font:` may name one of the three.** `font: heading` is drawn in the theme's
heading face, `font: body` in its body face and `font: mono` in its code face; a name that is
none of the three is a family as written.

## `style:` — the geometry

<!-- reference: presentation.style-keys -->

| Key | Type | Default | Minimum |
| --- | --- | --- | --- |
| `margin` | int, px | 40 | 1 |
| `gutter` | int, px | 20 | 1 |
| `block` | int, px | 12 | 1 |
| `rule` | int, px | 2 | 1 |
| `body` | int, pt | 16 | 1 |
| `title` | int, pt | 28 | 1 |
| `cover` | int, pt | 40 | 1 |
| `section` | int, pt | 44 | 1 |
| `headings` | list of int, pt | `[22, 20, 18, 16, 14]` | each ≥ 1 |
| `leading` | number | 1.2 | 1.0 |
| `heading-weight` | `bold` or `regular` | `bold` | |
| `heading-ink` | `ink` or `accent` | `ink` | |

## Text styles

A theme may name the styles a deck writes words in. The `textStyles:` section is optional: a
theme without one has no named styles, and every named form written against it is a name the
theme does not have.

```yaml
# themes/mine/theme.yml
textStyles:
  brand:
    color: accent1
    bold: true
  muted:
    color: lt2
    size: 10
```

The properties are the [eight attributes](/directives/#a-word-in-a-style) an inline style takes, and
a size is in points as it is everywhere else — and the smallest one is **half a point**. A colour
is the same one a shape's fill is written in: a role, a theme slot or a hex value.

**Ten mistakes in a theme's named styles are refused when the theme is read** rather than reaching
the slide:

| Refused | Why |
| --- | --- |
| a style that sets an attribute that does not exist | the attributes are a closed set |
| a style that sets nothing at all | a style that styles nothing is a mistake |
| a style that is not a mapping of attributes | a style carries one attribute per line |
| a style called `style` | that word is how the raw form is written |
| a style that sets a `highlight` | **not drawn by this half** |
| a style that names another one in a different case | `Brand` and `brand` are one word |
| a colour that is not a colour | the message names every colour that would have been right |
| a size that is not a number of points | the message says what a size may be |
| a font that is not a name | a font is a family name |
| a flag that is not `true` or `false` | `yes` is a YAML truthy string rather than the flag |

Every one of them names what would have worked instead, so a mistake in a theme's
`textStyles:` section is never rendered as silence. A `textStyles:` section that is not a mapping
of styles at all is refused the same way.

**A style's name is folded**, so a theme that spells a style `Brand` has it under `:brand[…]` and
`:Brand[…]` alike — the rule is on [How directives work](/directives/#a-word-in-a-style), which
both halves keep.

## Layout roles

Eight, and only these:

<!-- reference: presentation.layout-roles -->

```text
title  content  two-column  section  blank  image-text  image-title  image-slide
```

`blank` and `image-slide` have no title box. The `layouts:` section maps each role to a layout
name, and the names are the theme's own:

```yaml
# themes/mine/theme.yml
layouts:
  title: Titelfolie
  content: Titel und Inhalt
  two-column: Zwei Spalten
  section: Abschnitt
  blank: Leer
  image-text: Bild links
  image-title: Bild mit Titel
  image-slide: Ganzflächiges Bild
```

**A theme with no `layouts:` section is drawn with the library's own mapping**, which is the one
every theme shipping with this library used to carry in a file of its own: `Title Slide`,
`Title and Content`, `Two Content`, `Section Header`, `Blank`, `Picture Left`,
`Picture with Caption` and `Full Bleed Picture`.

**A theme with a `layouts:` section has the whole mapping.** A role the section does not name is
a role the theme does not map, and a deck that asks for one is told which roles the theme does
map. The section is not a set of overrides over the library's own names; it is the mapping, and
it is either complete or the theme cannot draw every role.

**A master fills in the roles it covers and says nothing about the rest.** A role the master
has no layout for — or a layout with no placeholders in it, such as Office's own *Blank* —
keeps the library's own geometry, even when the theme's `layouts:` section names it. The section
says which layout a role is; the master says where that layout puts its boxes.

**A theme with no master still names its layouts.** The library writes the layout parts itself,
and it names them by the mapping above: a theme whose `layouts:` section says
`content: Mein Inhalt` and carries no master has a layout part called *Mein Inhalt*, drawn in
the library's own geometry.

**A slide with no title box draws the frontmatter's title as a heading in its body.** A deck
that names no title has nothing to put one in, and a title that is not drawn is a silence —
so the title becomes the first thing in the body, where it is a heading like any other. Write
`title: ""` to leave it as metadata only, and the slide opens with no title at all.

Only the roles a deck actually uses are built, so a ten-slide deck does not carry eight masters.

## Templates

A **template** is not a theme, and it does not live beside one.

| | Theme | Template |
| --- | --- | --- |
| Where it lives | a directory, `themes/<name>/` | a slide of the deck, or a file beside the deck |
| What it is | `theme.yml` and its master | chrome: the free shapes it draws, and the words in them |
| What names it | `theme:` | `template:` |
| What it decides | the colours, the type, the grid | the furniture on the slide |

A theme's own `template.pptx` is a **third thing** and shares only the name: it is the PowerPoint
file that says where the theme's layouts put their boxes, and it says nothing about the furniture
on a slide. A template that says where the furniture goes is written in Markdown, beside the deck
or in it — `templates-file: ./templates.md` resolves against the deck's own directory, so a deck
and the furniture it shares travel together.

The rules a template follows, and the four things that decide what one is, are on
[Presentation directives](/presentation/directives/#slide-templates).

## The ones that ship

Five themes ship, and a deck names one with `theme:`:

<!-- reference: presentation.themes -->

| Theme | Margin | Block | Body | Headings | Leading | Weight | Ink |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `default` | 40 | 12 | 16pt | 22/20/18/16/14 | 1.2 | bold | ink |
| `corporate-dark` | 48 | 16 | 18pt | 26/23/21/19/17 | 1.32 | bold | **accent** |
| `minimal` | 64 | 14 | 17pt | 23/22/21/20/19 | 1.12 | **regular** | ink |
| `dense` | 28 | 8 | 13pt | 17/16/15/14/13 | 1.1 | bold | ink |
| `pitch` | 48 | 10 | 12pt | 24/20/16/14/12 | 1.25 | bold | ink |

`minimal` has more margin and less ink; `dense` has less margin and smaller type. Those are the
two axes a deck is usually fighting over, and they are separate keys so you can move one.

`pitch` is the one for a proposal: a near-black ink rather than a true black, and accents that carry
greys as well as colours.

## Resolution order

1. a path — written from beside the deck, the way a relative image path is —
2. `themes/<name>` beside the deck
3. `themes/<name>` beside the working directory
4. the package's own `themes/<name>`

There is **no silent fallback to `default`**. A name that does not resolve raises `ThemeNotFound`
and lists every theme that does. A deck that came out looking like something other than what was
asked for is worse than a deck that did not build.

That order is the disk's. A program that keeps its themes somewhere else — in a database, say —
hands the library a **finder** instead, and the name is asked for through it: see
[Where the files come from](/files/).

## Word themes

The Word half has themes too, and they are the other shape: a `theme.yml` — `name` required,
plus `description` and `author` — whose `styles:` section holds the same slot names the
[Word configuration](/word/configuration/) page lists, and whose `textStyles:` section holds the
named styles a document may write a word in. The two halves part at the look, and a Word theme
that carries the slides half's `colors:` is refused because that is the other half's look. They
are listed [there](/word/configuration/#themes).

A theme is a **starting point**, not a replacement: your own `styles:` sits on top of it, and
which keys you wrote is remembered so a partial override does not silently discard the rest.
