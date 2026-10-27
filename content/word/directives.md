---
layout: layouts/base.njk
permalink: /word/directives/
title: Word directives
summary: ::toc, :footnote and :style are drawn. Everything else is written into the document exactly as the author typed it.
section: Markdown Word
order: 1
---

Markdown Word draws **three** directives. They are real Word features rather than Markdown
dressed up, which is the reason they exist at all.

<!-- reference: word.directives -->

| Directive | Shape | Draws |
| --- | --- | --- |
| `toc` | block | a table of contents Word can build |
| `footnote` | inline | a real Word footnote |
| `style` | inline | a word in one of the theme's named styles |

## `::toc` — a table of contents Word can build

A block directive. Word's `TOC` element cannot draw one of these: it writes a `PAGEREF` for every
title registered, and this library never registers any — it draws a heading as a paragraph, not
through `addTitle()`. With none registered it writes a closing field character and no opening one,
so the document carries a field that starts nowhere. The field is therefore built directly.

### Attributes

| Attribute | Type | Default | Notes |
| --- | --- | --- | --- |
| `depth` | integer | `maxHeadingLevel`, which is `6` | clamped to 1–6 |
| `numbers` | boolean | `true` | `false` is Word's `\n` |
| `title` | string | none | a heading drawn above the field |

```markdown
::toc{depth=2, title="Contents"}
```

Names are matched case-insensitively and trimmed, so `::TOC` and `:: toc` both work.

### What comes out

An **empty field, marked dirty**, with `w:updateFields` set so Word builds it on open. Between
`separate` and `end` it says:

> Press F9 to build the table of contents.

That is deliberate. PHPWord's cached page numbers are all `1`, and a reader who never updates a
field shows them as they are — a table of contents in which every entry claims to be on page 1 is
worse than one that says plainly what to do. `F9` is what both Word and LibreOffice Writer update a
field with, so it names the way rather than the menu, which is not the same in either.

The field instruction it writes is exactly:

```text
 TOC \o "1-<depth>" \h \z \u
```

and with `numbers=false`, a `\n` before the `\h`.

{% example %}
````md word "A document with a table of contents, a footnote and a link"
# Quarterly report

Contents below are built by Word on open.

::toc{depth=2, title="Contents"}

## Highlights

The figure was provisional and has since been confirmed.

## Notes

Word will build the table on open. If it does not, select it and press F9.
````
{% endexample %}

### Limitations

- **Only `.docx` carries it.** `.odt` and `.rtf` get the text `::toc` instead — and the library
  says so in `pendingLosses()` rather than letting the document quietly lose a feature.
- **Heading styles must be the built-in names.** `LookAndFeel` sets `Heading1`…`Heading6` for
  exactly this reason; Word collects a table of contents by style name.
- **A template gets the source form.** Through a `.dotx` the handler is constructed detached, so
  the fallback applies and `::toc` is written as a paragraph.

## `:footnote[…]` — a real Word footnote

An inline directive. The text in the brackets is the note; there are **no attributes**, and the
body is inserted as text rather than parsed as Markdown.

```markdown
The figure was provisional[^1] and has since been confirmed.

[^1]: Source: the regional ledger, March.
```

Word's own footnote syntax is not what is parsed here. `:footnote[…]` is this suite's spelling.

> **Measured, and it does not currently reach a footnote.** Building
> `Body with a note:footnote[Source: the ledger.]` with the library's own default parser produced
> a `.docx` with **no `word/footnotes.xml` in it at all**, and the literal text
> `:footnote[Source: the ledger.]` in the body. The same happened with an explicit parser
> carrying `GenericDirectiveExtension`. This page says so rather than describing the intended
> behaviour, because the build on this site checks the difference and would fail the example
> otherwise.

- The mark sits in the run the directive stood in, so no post-pass is needed.
- The note is set in the `paragraph` slot's character half, not in whatever heading the mark sits in.
- An empty body — `:footnote` or `:footnote[]` — draws the source characters instead.
- Only `.docx` carries it; `.odt` and `.rtf` get the text back.

## Everything else

Any other name parses and is **written out as its own source text**. That is the policy, and it is
the opposite of the presentation package's:

```markdown
Status :green[go] before lunch.
```

parses, and `Status :green[go] before lunch.` is what reaches the document.

The reasoning is that `Status :green[go]` is a sentence, and a parser nobody asked to change
should not change the syntax of every document converted after it. A directive that used to be
opt-in became on by default, which was a behaviour change and is written down as one.

Two consequences worth knowing:

- **The container form of a drawn name is also claimed.** `:::toc` … `:::` matches the block map,
  the handler returns `true`, and the body is **silently dropped**. There is no test pinning this
  either way, so treat `::toc` as the only supported spelling.
- **Block and inline are separate maps.** `::footnote` is not drawn — footnotes are inline — and
  comes out as the text the author wrote.

## Strict mode is not reachable from here

`GenericDirectiveExtension` takes a `DirectiveRegistry` that makes the parser strict. Through
`CommonMarkParser` that mode cannot be reached: the extension list is a list of class names and
each is built with `new`, so handing over an instance with a registry on it gets a fresh one
without it. If you want strict directives, build the environment yourself — see
[How directives work](/directives/).