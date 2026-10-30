---
layout: layouts/base.njk
permalink: /command-line/basic/
title: Basic use
summary: The parts, every command, the exit codes, the pipes, and how it decides which way the data has to go.
section: The command line
order: 1
---

`mdoffice` is the whole suite at a terminal. It is a program in its own right,
[`markdown-office/mdoffice`](https://github.com/markdown-office/mdoffice), which requires both
converter packages and calls them — so what it does is what those libraries do.

The options of each part are on a page of their own: [For word](/command-line/word/), [For
presentations](/command-line/presentations/) and [For themes](/command-line/theme/). How to get the
tool is on [Installation](/command-line/installation/).

```bash
mdoffice word README.md          # writes README.docx
mdoffice word README.docx        # writes README.md
mdoffice slides deck.md          # writes deck.pptx
mdoffice theme deck.pptx         # writes deck/, a theme to edit
```

## Parts

The first argument says which part the run is for.

| Part | Does |
| --- | --- |
| `mdoffice word …` | Markdown to `.docx`, `.odt` and `.rtf`, and `.docx` back to Markdown |
| `mdoffice slides …` | Markdown to `.pptx`, and back |
| `mdoffice theme …` | A theme scaffolded from a deck |

**A bare file is not a run.** `mdoffice notes.md` is refused, and the parts are listed:

```text
mdoffice: Unknown part "notes.md".

  mdoffice <part> — one of:

  word       Markdown to .docx, .odt and .rtf, and .docx back to Markdown
  slides     Markdown to .pptx, and back
  theme      A theme scaffolded from a deck
```

The Word half decides the direction from the file's first bytes, but with more than one converter
here a name alone cannot say which way the data goes, and an error that guessed would be right
half the time and wrong the other half.

Only a `.docx` is read back. An `.odt` is a zip archive as well, so it is read as a Word
document and refused — the reader knows the Word document format and not the OpenDocument one.
The two are written, not read.

The theme part is the one that converts nothing: it reads a deck and writes a theme directory,
which is a first draft of a theme and is meant to be edited.

## Commands

| Part | Command | Does |
| --- | --- | --- |
| `word` | `to-docx` | Markdown to `.docx` |
| `word` | `to-odt` | Markdown to `.odt` |
| `word` | `to-rtf` | Markdown to `.rtf` |
| `word` | `to-markdown` | a Word document to Markdown |
| `slides` | `to-pptx` | Markdown to `.pptx` |
| `slides` | `to-markdown` | a deck to Markdown |
| `slides` | `diff` | two decks, and what changed between them |
| `theme` | `init` | a theme scaffolded from a deck |

In the Word part, with no command, the direction is detected. `--to` overrides it: `docx` or
`word` for a Word document, `odt` for an OpenDocument Text, `rtf` for Rich Text Format, and
`markdown` or `md` for Markdown. Those are the spellings it takes; `opendocument` and `rich
text` are refused, because the command that runs says which format it reads.

The slides part has three commands, and the first is the default: `mdoffice slides deck.md` is
the same run as `mdoffice slides to-pptx deck.md`. The second reads a deck back, and it is
named — a deck is a `.pptx`, and the part does not work the direction out for itself. The third
compares two decks and says what changed between them; its options are on [For
presentations](/command-line/presentations/).

The theme part has one command, and it is the default: `mdoffice theme deck.pptx` is the same
run as `mdoffice theme init deck.pptx`. Its options are on [For
themes](/command-line/theme/).

A command named on the command line decides the direction itself, and `--to` is then only allowed
to agree with it:

```text
mdoffice word: --to docx does not match what this reads; it takes odt.
mdoffice word:   Omit it and let mdoffice word work the direction out from the file.
```

The two forms do not take quite the same options. With no command named, the direction is worked
out from the file, and the options are the ones the four commands take between them — `--theme`
and `--require-control` among them. An option no command takes is refused:

```text
mdoffice word: Unknown option "--nope".
mdoffice word:   Run `mdoffice help` to see what each command accepts.
```

Naming the command is still what a script should do: an option that belongs to another command
is refused by the command the run turns out to be, whichever form it was written in.

Every command answers `--help` on its own, and every part answers `help`:

```bash
mdoffice help
mdoffice word help
mdoffice word to-docx --help
mdoffice slides to-pptx --help
mdoffice theme help
```

## Exit codes

`0` on success, and `1` for anything that did not finish — whether the cause is a mistake the
caller can put right or a defect they cannot. A mistake is printed as the sentence alone,
because it is the caller's to fix:

```text
mdoffice word: The file "nosuch.md" does not exist.
mdoffice word:   Name the file to read, or omit it to read standard input.
```

A defect is printed with its class and the line it happened on, because it is nobody's to fix and
somebody's to see: `mdoffice word: <ExceptionClass>: <message>`, and then the file and the line
underneath it.

**A diff that finds differences has done what it was asked.** It exits `0`, because comparing two
decks and reporting the difference is the whole of the work; `--exit-code` is what turns the
difference into a failure, for a build to act on.

## Things it is easy to get wrong

- `--output=x`, `--output x` and `-o x` are the same. Leading dashes are stripped before the
  lookup, so even `-o=x` works.
- Everything after a bare `--` is an operand, and a lone `-` is standard input.
- An option no command takes is named, and the hint says which command it does belong to:

  ```text
  mdoffice word: The option "--media" does not apply here.
  mdoffice word:   It belongs to `to-markdown`.
  ```

- An option no command takes at all is refused with the way out:

  ```text
  mdoffice word: Unknown option "--nope".
  mdoffice word:   Run `mdoffice help` to see what each command accepts.
  ```

## Pipes

```bash
cat notes.md | mdoffice word to-docx - -o - | pbcopy
cat deck.md  | mdoffice slides to-pptx - -o - | pbcopy
```

Reading standard input and writing standard output both work, which makes it a filter rather than
a command that has to be handed a filename. The result leaves through standard output and
everything else goes to standard error, so a pipe gets a document and not a progress line.

## What is lost

A run that dropped something says so on standard error, naming the feature:

```text
mdoffice word: odt cannot carry table-borders: a table comes out with no borders
```

The Word half reports what a target format cannot carry. The slides half reports what it had to
shrink to make fit — a block that is clamped rather than drawn over whatever comes next, so the
file itself says nothing about it.

## `--version`, and which library produced the document

An installed copy answers with its own version and the two libraries it holds:

```bash
$ mdoffice --version
mdoffice 0.1.2
  markdown-office/markdown-word            v0.3.1
  markdown-office/markdown-presentation    v0.3.1
```

The tool versions itself separately from the libraries it holds, because it is neither of them: a
CLI-only change is a reason to release, and one version across three repositories would mean a
Word-only fix forcing a presentation release. The libraries are read from the install rather
than declared, so a `--version` and the files beside it cannot disagree — and a checkout
answers with the branch it resolved rather than with a release, because that is what it holds.

`mdoffice word --version` reports the Word half alone, on one line:

```bash
$ mdoffice word --version
mdoffice word v0.3.1
```
