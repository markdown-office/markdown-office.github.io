---
name: mdword
description: Turn Markdown into a Word document — .docx, .odt or .rtf — with the markdown-word library, and turn one back into Markdown. Use when asked to write or convert a Word document, to render Markdown into a company's own Word template or theme, to read a Word document back into Markdown, or to check that a document the library produced looks right.
---

# mdword

Markdown becomes a Word document with `markdown-word`. **The answers are in the files
below, and the answers are the library's own** — this file says where to look and nothing
else. A question about what the library can do is answered by reading the library's
documentation, not by recalling it.

## Where to look

| The question | The file |
| --- | --- |
| The dialect — headings, tables, lists, code, links, pictures — and the library's own API | `references/dialect.md` |
| The directives: `::toc`, `:footnote`, `:style`, and the seven that place objects in a document | `references/directives.md` |
| Every frontmatter key, every option and every style slot | `references/configuration.md` |
| Rendering into a design that already exists — a Word template, or a theme | `references/templates.md` |
| What comes back when a document is read into Markdown, and the six things that do not | `references/round-trip.md` |
| The three options that decide what happens to a picture | `references/images.md` |
| The same three options, and how the slides half differs | `references/general-images.md` |
| Every exception the library throws, and what each one means | `references/errors.md` |
| How a directive is written — names, labels, attributes, fences | `references/directive-syntax.md` |
| What the two halves of the suite do with a name they know, and with one they do not | `references/directive-rules.md` |
| The errors both halves throw | `references/errors-general.md` |
| The command line, and the options each command takes | `references/command-line-word.md`, `references/command-line-basic.md` |
| Installing the command line | `references/command-line-install.md` |
| Installing this skill | `references/install.md` |

The **examples** are beside these: `examples/site/` holds the documents this documentation
shows, and `examples/library/` holds the library's own twenty-three — the catalogue of what
it can do, one file each.

## The loop

Write the Markdown, build the document, render it, **look at it**, and fix the Markdown.

```bash
mdoffice word to-docx notes.md -o notes.docx   # build
php scripts/render-document.php notes.docx --out notes.pdf   # render
```

A **template** is rendered as well as a document — `php scripts/render-document.php
report.dotx --out report.pdf` — and the PDF is named after the template.

**Then read the PDF.** A document that has not been looked at has not been checked, and the
thing a document gets wrong is almost always something only a picture shows: a line that
wraps, a table that runs off the page, a picture in the wrong place.

For a picture of every page rather than a PDF, WordCraft renders them directly:

```bash
php scripts/render.php notes.docx --port 7981 --out pages/
```

The port is the application's own — start it with `--control <port>`, and read the key it
writes out of its settings folder. The script finds the key itself.

## Which renderer to believe

| | What it is | How much it can be trusted |
| --- | --- | --- |
| **Microsoft Word** | the real thing | **the truth** — the only one that will say whether a document opens |
| **WordCraft** | a clean-room Word | faithful, and the only one of the three that runs everywhere |
| **LibreOffice** | headless | approximate — it disagrees with Word about where a line falls often enough that it cannot answer the question a renderer is asked |

`scripts/render-document.php` picks the first of them the machine has, and **says so when
it had to fall back**, because a renderer that cannot be trusted is worse than no renderer:
a caller who does not know is told a layout is right when it is not.

## What this half does differently

**The Word half never refuses.** A directive it does not know is written into the document
as the author's own characters — `Status :green[go] before lunch.` reaches the document
exactly as written. So a typo in a directive produces braces in the output and **no error
at all**. The slides half refuses the same name and names the alternative. See
`references/directive-rules.md`.

## The command line

```bash
mdoffice word to-docx notes.md      # to .docx
mdoffice word to-odt notes.md       # to .odt
mdoffice word to-rtf notes.md       # to .rtf
mdoffice word to-markdown notes.docx  # and back
```

It decides which way the data has to go **from the content, not the name**: a Word document
is a zip and Markdown is text, and the four bytes that say which is which are part of the
format. Exit `0` on success and `1` for anything that did not finish — a mistake the caller
can put right or a defect they cannot.

The command line is not part of this library; it is `mdoffice`, one program over both
converters. `references/command-line-install.md` says how to get it.
