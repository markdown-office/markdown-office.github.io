---
layout: layouts/base.njk
permalink: /presentation/themes/
title: Themes and layout
summary: The twelve colour slots, the twelve style keys, the eight layout roles, and the themes that ship with the presentation package.
section: Markdown Presentation
order: 2
---

A theme is a directory. `theme.yml` is required; `styles.yml`, `layouts.yml` and
`template.pptx` are optional.

```text
themes/corporate-dark/
├── theme.yml      required
├── styles.yml     the Word half's vocabulary — the renderer does not read it
├── layouts.yml    which PowerPoint layout each role maps to
├── text-styles.yml  the names a deck writes words in — see [Text styles](#text-styles)
└── template.pptx  optional, a path
```

## `theme.yml`

<!-- reference: presentation.theme-keys -->

| Key | Type | Default | Notes |
| --- | --- | --- | --- |
| `name` | string | — | **required**, non-empty |
| `description` | string | `null` | |
| `author` | string | `null` | |
| `dark` | boolean | `false` | boolean only — `dark: yes` is refused |
| `colors` | mapping | see below | `#rrggbb`, stored upper-cased |
| `fonts` | mapping | Calibri / Calibri / Consolas | `heading`, `body`, `mono` |
| `style` | mapping | see below | the geometry |

The last three hold a mapping. A key is written with a colon in the file whatever it holds,
and the table names the keys as they are — the colon is YAML's and not theirs.

`dark: yes` being refused is not pedantry. YAML 1.1 truthy strings are what make a theme written
by hand behave differently from one written by a machine, and this one is for machines.

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

A theme may name the styles a deck writes words in. `text-styles.yml` is optional: a theme
without one has no named styles, and every named form written against it is a name the theme does
not have.

```yaml
# themes/mine/text-styles.yml
brand:
  color: accent1
  bold: true
muted:
  color: lt2
  size: 10
```

The properties are the [eight attributes](/directives/#a-word-in-a-style) an inline style takes, and
a size is in points as it is everywhere else. A colour is the same one a shape's fill is written
in: a role, a theme slot or a hex value.

When the theme is read, a style that names an attribute that does not exist, a style that carries
no property at all, a style called `style` — the word the raw form is written with — and a
`highlight`, which this half does not draw, are each refused. A typo in the file is never rendered
as silence.

## Layout roles

Eight, and only these:

<!-- reference: presentation.layout-roles -->

```text
title  content  two-column  section  blank  image-text  image-title  image-slide
```

`blank` and `image-slide` have no title box. `layouts.yml` maps each role to a layout name in the
master — the file may be a bare mapping or nested under a single `layouts:` key.

Only the roles a deck actually uses are built, so a ten-slide deck does not carry eight masters.

## Templates

A **template** is not a theme, and it does not live beside one.

| | Theme | Template |
| --- | --- | --- |
| Where it lives | a directory, `themes/<name>/` | a slide of the deck, or a file beside the deck |
| What it is | `theme.yml` and its friends | chrome: the free shapes it draws, and the words in them |
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

1. an explicit path — anything containing `/`
2. `themes/<name>` beside the deck
3. the package's own `themes/<name>`

There is **no silent fallback to `default`**. A name that does not resolve raises `ThemeNotFound`
and lists every theme that does. A deck that came out looking like something other than what was
asked for is worse than a deck that did not build.

## Word themes

The Word half has themes too, and they are the other shape: a directory with `theme.yml` —
`name`, `description`, `author`, and `name` required — and an optional `styles.yml` holding the
same slot names the [Word configuration](/word/configuration/) page lists. They are listed
[there](/word/configuration/#themes).

A theme is a **starting point**, not a replacement: your own `styles:` sits on top of it, and
which keys you wrote is remembered so a partial override does not silently discard the rest.
