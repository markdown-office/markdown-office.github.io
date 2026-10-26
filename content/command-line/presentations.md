---
layout: layouts/base.njk
permalink: /command-line/presentations/
title: For presentations
summary: The options `mdoffice slides to-pptx` and `mdoffice slides to-markdown` take, and the dialect the deck is read with.
section: The command line
order: 2
---

The slides part has two commands. `mdoffice slides deck.md` is the same run as `mdoffice slides
to-pptx deck.md`, and the second reads a deck back. The options each takes are here; the
options the Word part takes are on [For word](/command-line/word/).

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

**There is no `--to` in this part.** The command says which way the deck goes, and the deck
is a `.pptx` either way:

```text
mdoffice slides: Unknown option "--to".
mdoffice slides:   Run `mdoffice help` to see what each command accepts.
```
