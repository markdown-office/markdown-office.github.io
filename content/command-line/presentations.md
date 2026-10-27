---
layout: layouts/base.njk
permalink: /command-line/presentations/
title: For presentations
summary: The options `mdoffice slides to-pptx` and `mdoffice slides to-markdown` take, and the dialect the deck is read with.
section: The command line
order: 2
---

The slides part has three commands. `mdoffice slides deck.md` is the same run as `mdoffice slides
to-pptx deck.md`, the second reads a deck back, and the third compares two decks. The options
each takes are here; the options the Word part takes are on [For word](/command-line/word/), and
the theme part is on [For themes](/command-line/theme/).

## Options for `to-pptx`

| Option | Value | Default |
| --- | --- | --- |
| `-o, --output <file>` | path | beside the input; `-` for standard output |
| `--strict` | flag | off — a deck that does not fit is shrunk to fit |
| `--image-base <path>` | path; may be repeated | the deck's own directory |
| `-h, --help` | flag | off |

A deck is read with the Slidev-compatible dialect, so a `---` line closes the slide being read
and `---` on its own opens the frontmatter block.

There is no `--theme`: the renderer resolves the theme from the deck's own `theme:`.

## Options for `to-markdown`

| Option | Value | Default |
| --- | --- | --- |
| `-o, --output <file>` | path | beside the input, `.md`; `-` for standard output |
| `--media <dir>` | path | no extraction |
| `--line-ending <lf\|crlf>` | `lf`, `crlf` | `lf` |
| `-h, --help` | flag | off |

A line ending may also be written `unix`, `n`, `dos`, `windows` or `r`.

The Markdown that comes back is the Markdown this tool writes decks in, so a deck can be read
out, edited as text and put in again through `to-pptx` with a different theme.

## Options for `diff`

| Option | Value | Default |
| --- | --- | --- |
| `--exit-code` | flag | off |
| `-h, --help` | flag | off |

A diff takes two decks and says what changed between them:

```bash
mdoffice slides diff before.pptx after.pptx
```

**The bytes are never compared.** A `.pptx` carries a timestamp in every part it holds and
another in the document's own properties, so two correct renderings of the same deck differ most
of the time; comparing the bytes would report every deck as changed.

**The slides are paired by their id**, because an id survives an edit and a reorder of the deck
and a position does not: a slide inserted at the front moves every slide after it, and pairing by
position would report the whole deck as changed. The shapes of a slide are paired by their id
first, then by their name, and then by their place. A slide or a shape that is left over was
added or removed, and the report says which.

**What is compared** is the text of every shape, the position and the size of every shape that
states them, and the colour of every fill and every run. A colour that names a slot of the theme
is compared as the colour that slot holds, so a change in the theme or in the master's colour
map is a change of colour. Positions and sizes are in millimetres, from the top left of the
slide, and two lengths are the same length when they agree to a hundredth of a millimetre.

**What is not compared** is said as well, because a diff that quietly skips what it could not
compare leaves a reader worse off than one that says what it looked at: the notes, the layouts,
the masters, the theme's own fonts, the pictures, the document's own properties, a fill that is
not a single colour, the frame of a group of shapes, and the position or the size of a shape
that takes it from the layout.

The report goes to standard error, so a pipe gets the difference and not the report of it. It
ends with what was looked at:

```text
Compared the text of every shape, the position and the size of every shape that states them, and the colour of every fill and every run, on the 3 slides the two decks have in common. A colour that names a slot of the theme is compared as the colour that slot holds, so a change in the theme or in the master's colour map is a change of colour.
Positions and sizes are in millimetres, from the top left of the slide. Slides are numbered as in the first deck.
Not compared: the notes, the layouts, the masters, the theme's own fonts, the pictures, the document's own properties, a fill that is not a single colour, the frame of a group of shapes, and the position or the size of a shape that takes it from the layout.
```

**A diff that finds differences has done what it was asked**, so it exits `0`. `--exit-code` is
what turns the difference into a failure, for a build to act on:

```bash
mdoffice slides diff before.pptx after.pptx --exit-code || echo "the decks differ"
```

A diff needs two decks, and one is refused:

```text
mdoffice slides: Name the two decks to compare.
mdoffice slides:   A diff takes two: the deck before and the deck after.
```

**There is no `--to` in this part.** The command says which way the deck goes, and the deck
is a `.pptx` either way:

```text
mdoffice slides: Unknown option "--to".
mdoffice slides:   Run `mdoffice help` to see what each command accepts.
```
