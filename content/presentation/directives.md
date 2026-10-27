---
layout: layouts/base.njk
permalink: /presentation/directives/
title: Presentation directives
summary: ::click, :::click, ::column and ::note — plus the closed set of frontmatter keys, and everything the deck will refuse by name.
section: Markdown Presentation
order: 1
---

Markdown Presentation splits its vocabulary in two: **frontmatter keys**, which are configuration,
and **in-body colon markers**, which are instructions. Both are closed sets, and both fail the
build with a message naming what would have worked.

## In-body markers

| Written | Shape | What it does |
| --- | --- | --- |
| `::click` | leaf | Advances the build by one step |
| `::click=N` | leaf | Advances to step `N` |
| `:::click` … `:::` | container | Everything between the fences is at one step |
| `::column` | leaf | Moves the next blocks into the next column |
| `::note` … `::` | container | The slide's speaker note |

All must be alone on their line. Leading spaces are fine; four spaces of indent is a code block.
Names are case-insensitive.

### `::click` — building a slide up

```markdown
## Three things

- One
- Two

::click

- Two is the interesting one
- Three

::click=3

- All three
```

`::click` advances by one. `::click=N` jumps to step `N`, and `N` must be greater than the
current step and no more than **500**.

Step 1 is not a click — it is what is on the slide before anyone clicks — so a step nothing was
written on costs no click, and the first `::click` after a heading is the first click.

{% example %}
````md presentation "A slide that builds up on three clicks, with a speaker note"
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

### `:::click` … `:::` — a step region

Everything between the fences is drawn at the step the opener named, and the walk resets to that
same step after the closer:

```markdown
:::click=2
The second point, made visible.
:::
```

The closer is **exactly three colons** — four inside a region is a paragraph. An unclosed region is
refused rather than running to the end of the slide.

### `::column` — two columns of content

```markdown
## Before and after

::column

Before: a file, and a hope.

::column

After: a file, and an answer.
```

The number of columns is the layout's. Writing more `::column` markers than the layout has columns
is `UnsupportedColumn`, and the message names which slide and which column.

`::column` takes **no argument** — `::column=2` is refused. The layout decides how many there are.

### `::note` — the speaker note

```markdown
# One

::note
Read this out loud.
::
```

Case-insensitive and whitespace-tolerant. A note has to be the last thing on the slide: the
closer is anchored to the end of it, so two `::note` blocks on one slide become **one** note
carrying both. An unclosed note is refused.

## Free shapes

A **free shape** is one you place yourself, in pixels or in fractions of the slide, rather than one
the layout puts where it wants it. It is written in the body of a slide with the same two-colon
directives the step markers use, and there are twelve of them and no others — a name outside them
is refused with a message that lists all twelve.

<!-- reference: presentation.free-shapes -->

| Shape | Written | Draws |
| --- | --- | --- |
| `rectangle` | `::rectangle` | a rectangle |
| `roundrect` | `::roundrect` | a rounded one, with `border-radius` |
| `ellipse` | `::ellipse` | an ellipse |
| `circle` | `::circle` | an ellipse that is as wide as it is tall |
| `triangle` | `::triangle` | an isosceles triangle |
| `diamond` | `::diamond` | a diamond |
| `chevron` | `::chevron` | a chevron |
| `hexagon` | `::hexagon` | a hexagon |
| `arrow` | `::arrow` | a right-pointing arrow |
| `line` | `::line` | a hairline rule as thick as its `stroke-width` |
| `image` | `::image` | a picture |
| `content` | `:::content` … `:::` | Markdown, in a box you sized |

**Attributes are separated by a space.** `::circle{left=8% top=10% width=6% height=6%}` is one
attribute after another, not a list with commas in it — a comma is part of the value, so
`{fill=red,stroke=blue}` is one attribute called `fill` whose value is `red,stroke=blue`.

`layout: blank` is what most of them want: a slide whose whole content is free shapes has no title
to place them around. Every attribute each of them reads is in the package's own
[README](https://github.com/markdown-office/markdown-presentation#free-shapes).

## Slidev, and where it differs

The splitter is a **port of Slidev's `parseSync`**, pinned to a commit of that file. A deck that
splits the way a Slidev deck splits is the whole point: the dialect that people already write
for Slidev is the one worth reading, and matching it matters more than being tidy.

It is a port and not a clone, and the differences are recorded rather than discovered later.
Every fixture in `tests/fixtures/slidev/expected.json` was run through Slidev's own
`parseSync` at that commit, and each divergence below is written down with its reason in
`tests/fixtures/slidev/divergences.json`. There are **21** of them, in four groups.

### Frontmatter it refuses

| Case | Why this library differs |
| --- | --- |
| `list in frontmatter` | A frontmatter block that is a YAML list carries no keys to read configuration from, so it is refused instead of stored as something no caller asked for. |
| `scalar frontmatter` | A frontmatter block that is a YAML scalar is not a mapping, and a scalar where a mapping was expected is a mistake rather than an empty configuration. |
| `broken yaml frontmatter` | Slidev keeps parsing invalid YAML and takes what it can. A deck author gets an error naming the slide instead, because a silently half-read block is how a deck comes out with the wrong title and nobody knows why. |
| `title not a string` | Slidev takes the value as the title whatever its type. A number is not a title, so it is refused rather than rendered. |
| `level not an int` | Slidev passes the value through to whatever consumes the level. A level that is not an integer is refused instead. |
| `fenced yaml frontmatter` | Slidev reads a slide that opens with a fenced yaml block as frontmatter. Here it would be a code block, so the ambiguous shape is refused rather than read the other way round. |

### The delimiter and the comments around it

| Case | Why this library differs |
| --- | --- |
| `comment closed on the delimiter line` | The note is empty. An empty note is no note, so there is none rather than an empty string. |
| `empty trailing comment` | The note is empty. An empty note is no note, so there is none rather than an empty string. |
| `indented opener, delimiter, flush closer` | The same consequence seen from the other side: the `---` is inside the block, so the block runs to the flush closer and the delimiter no longer splits anything. |
| `de-indented fence` | A closing fence may be indented less than the opening one. Slidev matches the close with `startsWith` against the raw line, so the opener's indentation has to be repeated exactly and an otherwise ordinary block is split by the delimiter inside it. |
| `indented opener closed flush, delimiter between` | A consequence of the closing fence not having to repeat the opener's indentation: the flush closer closes the block, so the `---` between them is a delimiter again and splits the slide. |
| `html comment on one line` | A speaker note is `::note` on a line of its own, its text, and a closing `::`. An HTML comment after a slide's last line is the note Slidev reads it as; here it is a deck written for another tool, left on the slide and refused by the renderer rather than taken. |
| `comment ending the slide` | A speaker note is `::note` on a line of its own, its text, and a closing `::`. An HTML comment after a slide's last line is the note Slidev reads it as; here it is a deck written for another tool, left on the slide and refused by the renderer rather than taken. |
| `two trailing comments` | A speaker note is `::note` on a line of its own, its text, and a closing `::`. An HTML comment after a slide's last line is the note Slidev reads it as; here it is a deck written for another tool, left on the slide and refused by the renderer rather than taken. |
| `comment with a delimiter inside the note` | A speaker note is `::note` on a line of its own, its text, and a closing `::`. An HTML comment after a slide's last line is the note Slidev reads it as; here it is a deck written for another tool, left on the slide and refused by the renderer rather than taken. |

### Fences, which are CommonMark's rules rather than Slidev's

| Case | Why this library differs |
| --- | --- |
| `tildes are not a fence` | Tildes fence a code block in CommonMark, so the `---` between them is code rather than a delimiter. Slidev recognises only backticks and splits the slide, leaving the block split across two slides. |
| `unterminated fence` | A fence that is never closed runs to the end of the deck, which is what CommonMark says one is. Slidev leaves the index where it was and reads every following line as ordinary content, so a `---` inside an unclosed fence splits the deck and the next slide opens with a literal delimiter. |
| `unterminated tilde` | The same for a tilde fence, which Slidev does not recognise as a fence at all. |
| `tilde fence` | Tildes fence a code block in CommonMark. Slidev matches a backtick run only, so a `~~~` block is read as prose and a `---` inside it splits the deck. |
| `tilde fence long` | A tilde run of any length fences a block, as CommonMark has it. |

### Levels

| Case | Why this library differs |
| --- | --- |
| `level zero` | Slidev keeps the heading depth because a zero is falsy, which is a truthiness check where a range was meant. A heading level below one is refused instead. |

None of these move a slide boundary for an ordinary deck. They are the corners: an
unterminated fence, a comment on the delimiter line, a `level: 0`. A deck written for Slidev
splits here the way it does there.

## Frontmatter directives

Every key of every slide's frontmatter must be one of these seven:

<!-- reference: presentation.frontmatter -->

| Key | Type | Default | Slide |
| --- | --- | --- | --- |
| `title` | string | `null` | any |
| `name` | string | `null` | any — a fallback for `title` |
| `level` | integer ≥ 1 | the heading's own depth | any |
| `layout` | a role name | `content` | any |
| `theme` | string | `default` | **first only** |
| `author` | string | the theme's, else `markdown-presentation` | **first only** |
| `description` | string | `null` | **first only** |

The layout roles are listed with the [themes](/presentation/themes/#layout-roles). Anything else —
including Slidev's own `two-cols` — is refused with the list of what would have worked.

A deck directive on a later slide is refused by name, because it is nearly always a slide that
was meant to be the first one.

{% example %}
````md presentation "Every frontmatter key, on a deck that uses all seven"
---
theme: corporate-dark
title: The quarter in three slides
author: Markdown Office
description: What changed, what it cost, and what happens next.
---

# The quarter

## What changed

::click

- Two products shipped

::click

- Three customers renewed

::note
Lead with the renewal number. It is the one people remember.
::
````
{% endexample %}

## What is refused, and why

The splitter is a **port** of Slidev's `parseSync`, not a clone of it: a deck that splits the way
Slidev splits is the point, and the places it deliberately does not are written down — see
[Slidev, and where it differs](/presentation/directives/#slidev-and-where-it-differs).

The renderer shares none of that. Everything Slidev draws with something this package does not
have is refused by name rather than drawn as a paragraph:

| Written | Refused as |
| --- | --- |
| `<!-- v-click -->` | an HTML comment |
| `<v-click>`, `<div v-if="x">` | an unsupported element |
| `:::note` … `:::` | a container |
| `::bogus`, `::left::`, `::right::` | a leaf directive |
| `::note` with no closing `::` | an unclosed note |
| `---` used as a rule | a slide delimiter — use `***` |

That list is the point of the package. The thing writing a deck may be a model, and a model can
act on a message and cannot act on a deck that quietly came out wrong.

## Three things that look like they work and do not

These are worth stating because a reader would reasonably assume otherwise.

**A marker inside a list item is drawn as text.** `- ::click` produces a list item whose content is
literally `- ::click`. The refusal walk does not descend into a list, and a marker inside one would
mean splitting a single list block into several.

**`::click{foo=bar}` and `::click[label]` render as an empty paragraph** — not an error, and not as
text. The marker patterns are anchored to the end of a line, so the brace and bracket forms fall
through to the generic directive parser, whose output nothing translates. Write the bare form or
`=N`.

**A frontmatter block must start at the very first byte.** A deck whose source begins with a blank
line has its `theme:` attributed to slide 2, and is refused for naming a deck directive there.