---
layout: layouts/base.njk
permalink: /command-line/word/
title: For word
summary: The options `mdoffice word` takes, for the three formats it writes and for the one it reads.
section: The command line
order: 3
---

The Word part decides the direction from the file's first bytes, and `--to` overrides it. The
options it takes are here; the slides part has two commands and two sets of options, on [For
presentations](/command-line/presentations/).

## Options for `to-docx`, `to-odt` and `to-rtf`

| Option | Value | Default |
| --- | --- | --- |
| `-o, --output <file>` | path | beside the input; `-` for standard output |
| `-c, --config <file.php>` | path | none |
| `--theme <name>` | name or path | the frontmatter's `theme:` |
| `-t, --template <file>` | `.docx` or `.dotx` | none |
| `--require-control` | flag | off |
| `--images <mode>` | `embed`, `placeholder` or `alt`, `skip` or `none` | `embed` |
| `--no-images` | flag | shorthand for `--images skip` |
| `--image-base <dir>` | path | the input's directory; the working directory for standard input |
| `--svg-fallback <mode>` | `raster`, `none` | `raster` |
| `--table-width <n>` | fiftieths of a percent; 0–5000 | `5000`, the full width; `0` leaves the width to Word |
| `--plain` | flag | off |
| `--to <format>` | `docx`, `word` for `to-docx`; `odt` for `to-odt`; `rtf` for `to-rtf` | the command names the direction |
| `-h, --help` | flag | off |

`--plain` is the fastest thing to try: no code colouring, no quote style, no table borders, no
added spacing.

The `--to` in this table is the one that names a command, and it takes that command's own
format — any other is refused:

```text
mdoffice word: --to docx does not match what this reads; it takes odt.
mdoffice word:   Omit it and let mdoffice word work the direction out from the file.
```

With no command named, `--to` names the direction instead, and takes `docx`, `word`, `odt`,
`rtf`, `markdown` or `md` — see [Basic use](/command-line/basic/).

Only `to-docx` takes `--template`. The others answer:

```text
mdoffice word: A template cannot be filled in for odt.
mdoffice word:   A template is a .docx or .dotx, and only `mdoffice word to-docx` renders into one.
```

A document that names its own template does so with `template_file:` in its frontmatter, and that
is read whichever of the three formats the document is converted to — so a document with a
`template_file:` is refused for `.odt` and `.rtf` in the same words. `--template` outranks the
frontmatter.

## Options for `to-markdown`

| Option | Value | Default |
| --- | --- | --- |
| `-o, --output <file>` | path | beside the input, `.md` |
| `--media <dir>` | path | no extraction |
| `--line-ending <lf\|crlf>` | `lf`, `crlf` | `lf` |
| `--setext` | flag | off — headings are written as `# Heading` |
| `--no-fence` | flag | off — a monospaced paragraph comes back as a fenced code block |
| `--no-header` | flag | off — the first row of a table is a header row |
| `--to <format>` | `markdown`, `md` | the command names the direction |
| `-h, --help` | flag | off |

A line ending may also be written `unix`, `n`, `dos`, `windows` or `r`.

