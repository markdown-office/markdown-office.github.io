---
layout: layouts/base.njk
permalink: /word/directives/
title: Word directives
summary: ::toc, :footnote, :style and seven that place objects in a document. Everything else is written into the document exactly as the author typed it.
section: Markdown Word
order: 1
---

Markdown Word draws **ten** directives. They are real Word features rather than Markdown dressed
up, which is the reason they exist at all.

<!-- reference: word.directives -->

| Directive | Shape | Draws |
| --- | --- | --- |
| `toc` | block | a table of contents Word can build |
| `footnote` | inline | a real Word footnote |
| `style` | inline | a word in one of the theme's named styles |
| `image` | block | a picture of a stated size |
| `caption` | block | a caption under a picture |
| `pagebreak` | block | a page break |
| `section` | block | a section break, and the columns of the section it starts |
| `textbox` | block | a floating box with text in it |
| `shape` | block | a shape |
| `field` | block | a form field |

**A caller that builds its own registry draws fewer.** `Directives::standard()` takes a handler for
each name, and the handlers that are not given are not drawn — a caller with no images to place has
no use for a handler that places them. A conversion through `MarkdownToWord` hands over all of
them, so all ten are drawn.

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
::toc{depth=2 title="Contents"}
```

**Attributes are separated by a space.** A comma is part of the value rather than a separator, so
`{depth=2, title="Contents"}` reads the depth as `2,` — which is not a number, and the depth falls
back to the default.

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

::toc{depth=2 title="Contents"}

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

> **A directive needs the whitespace in front of it.** `Body with a note:footnote[Source: the
> ledger.]` is prose — a name that follows a word is not a directive — and the `.docx` carries the
> literal text `:footnote[Source: the ledger.]` in the body. Written
> `Body with a note :footnote[Source: the ledger.]`, it is a real Word footnote: the `.docx`
> carries `word/footnotes.xml`, and a `w:footnoteReference` stands where the note was written.

- The mark sits in the run the directive stood in, so no post-pass is needed.
- The note is set in the `paragraph` slot's character half, not in whatever heading the mark sits in.
- An empty body — `:footnote` or `:footnote[]` — draws the source characters instead.
- Only `.docx` carries it; `.odt` and `.rtf` get the text back.

## `::image` — a picture of a stated size

```markdown
::image[Revenue by quarter]{src="chart.png" width=12 height=8 align=center}
```

The label is the alternative text, and a file that is not there falls back to it — the same answer
a Markdown image gives. The file is resolved against `imageBasePath` the way a Markdown image's
URL is.

**The size is in centimetres**, the unit `imageMaxWidth` is in, so a document that names a size and
a document that leaves it to the option are in the same unit. A size that is asked for is the size
the picture gets: the cap is what happens to a picture that says nothing about its own size, and
shrinking a picture to a limit the author did not know about is a quiet change this library does not
make. One of the two sizes leaves the other to the picture's own proportions.

| Attribute | Carries |
| --- | --- |
| `src` | the file |
| `width`, `height` | the size in centimetres |
| `align` | `left`, `center`, `right` |

A directive with no `src` is written out as its own source text.

## `::caption` — a caption under a picture

```markdown
::caption[Figure 1: Revenue by quarter]{}
```

A caption is a paragraph in the document's own look, with its text in italics.

**A caption is not numbered.** PHPWord has no `SEQ` field, which is the field Word numbers a
figure with, so a caption that numbered itself would be a number written out at conversion time —
wrong the moment a figure is inserted above it. The number is in the caption, where a reader can
see it and an author can change it.

An empty caption is written out as its own source text.

## `::pagebreak` — a page break

```markdown
::pagebreak
```

A page break belongs to a section, and a directive that stands where there is no page to break — in
a table cell — is written out as the characters the author wrote, the same answer an unclaimed
directive gets.

## `::section{columns=…}` — a section break, and its columns

```markdown
::section{columns=2}
```

Everything after the directive belongs to the new section, and the columns are its own. The new
section keeps the page setup of the one it follows: a section is a page, and a document whose
second half is on a different page size from its first is a document nobody meant to write.

A section break is a break between two parts of a document, and a part of a document that is a
quote or a list item is not one — so a `::section` inside either is written out as the characters
the author wrote.

**The columns are carried by `.docx` and `.odt`, and not by `.rtf`.** RTF has no columns at all, so
the section break survives and the columns do not. That is reported the way every other loss is.

## `::textbox` — a floating box with text in it

```markdown
::textbox[A floating note]{width=6 height=2 left=1 top=1 border=00B4D8}
```

The label is the text the box holds.

**The box floats**, and the text flows around it: a box that sat in the flow would be a paragraph
with a border round it, and a paragraph is what a document can already say. `left` and `top` place
it — from the left margin and from the top of the paragraph it stands in — and both are in
centimetres, like every other size here. A box is given a border, because a box with nothing round
it is a paragraph.

| Attribute | Carries |
| --- | --- |
| `width`, `height` | the size in centimetres |
| `left`, `top` | where it floats, in centimetres |
| `border` | the colour of the line round it, six hexadecimal digits without a `#` |

A box with no size is written out as its own source text.

## `::shape` — a shape

```markdown
::shape{type=rect width=4 height=2 fill=E7E6E6 line=4472C4}
```

The type is one of the shapes a Word document can draw: `arc`, `curve`, `line`, `polyline`, `rect`
and `oval`. A type that is not one of them is written out as the characters the author wrote,
because an element no reader knows is not a shape.

| Attribute | Carries |
| --- | --- |
| `type` | the shape to draw |
| `width`, `height` | the size in centimetres |
| `fill` | the colour inside it, six hexadecimal digits without a `#` |
| `line` | the colour of its outline, six hexadecimal digits without a `#` |

## `::field` — a form field

```markdown
::field{name="Customer" type=textinput value="Northwind"}
```

The type is one of `textinput`, `checkbox` or `dropdown`, and `textinput` is the default. A dropdown
carries the choices it offers, separated by commas.

**A dropdown with entries turns the document into a form.** Word only lets a reader choose from one
while the document is protected for forms, so a document with a dropdown that offers choices is
protected for forms. A dropdown without entries is written as a field with nothing to choose from,
and nothing is protected: there is nothing to choose.

| Attribute | Carries |
| --- | --- |
| `type` | `textinput`, `checkbox` or `dropdown` |
| `name` | the name of the field |
| `entries` | the choices a dropdown offers, separated by commas |
| `value` | what a text field starts with |
| `checked` | `true` leaves a checkbox ticked |

## What a format cannot carry

**The Word half never refuses.** A directive a writer cannot express is given the author's own
characters back, and the loss is reported — never a refusal, and never a silent one.

<!-- reference: word.object-features -->

| Feature | `.docx` | `.odt` | `.rtf` |
| --- | --- | --- | --- |
| `text-box` | carried | lost | lost |
| `shape` | carried | lost | lost |
| `form-field` | carried | lost | lost |
| `columns` | carried | carried | lost |

A text box, a shape and a form field are written as VML and as Word's own `FORMTEXT`,
`FORMCHECKBOX` and `FORMDROPDOWN` fields, and neither the ODF writer nor the RTF writer has an
element for any of them. What the other two formats are given instead is the source form, and the
loss is reported rather than suffered in silence:

```text
a ::textbox is left out of the document
a ::shape is left out of the document
a ::field is left out of the document
the columns of a ::section are lost
```

The last of those is the RTF's alone. RTF has no columns at all, so the section break survives and
the columns do not.

`pendingLosses()` is where those sentences come from, and the command line prints them rather than
letting the document quietly lose the thing.

## A word in a style

A word in a style, in the middle of a sentence:

```markdown
Resilience :brand[Bakery] is open.
```

The three forms and the eight attributes are the suite's, and are on
[How directives work](/directives/#a-word-in-a-style). The named styles come from the theme: the
`textStyles` key of its `styles.yml`, beside the slots — see [Themes](/word/configuration/#themes).

**What this half does with a name it does not know is the opposite of the presentation half's: it
writes the directive out as its own source text.**

| Written | In the document |
| --- | --- |
| `:brand[Bakery]` where the theme has no `brand` | `:brand[Bakery]` |
| `:style[Bakery]` with no attributes | `:style[Bakery]` |
| `:brand[Bakery]{weight=900}` | `:brand[Bakery]{weight="900"}` |
| `:brand[**Bakery**]` | `:brand[**Bakery**]` |

The label is plain text, so a complete Markdown construct in one is drawn as the source text as
well. A bare `*` or `_` is not Markdown: `2 * 3` and `snake_case` are ordinary text, and the word
is written in the style as it stands.

{% example "A word in the theme's own named style" %}
````md word
---
theme: corporate
---

Resilience :brand[Bakery] is open. It has been since :muted[2019].
````
{% endexample %}

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

- **The container form of a drawn name draws its body.** `:::toc` … `:::` is not a table of
  contents with fences written around it: the body is drawn as ordinary Markdown and the fence is
  gone. Write `::toc` on a line of its own.
- **Block and inline are separate maps.** `::footnote` is not drawn — footnotes are inline — and
  comes out as the text the author wrote.

## Strict mode is not reachable from here

`GenericDirectiveExtension` takes a `DirectiveRegistry` that makes the parser strict. Through
`CommonMarkParser` that mode cannot be reached: the extension list is a list of class names and
each is built with `new`, so handing over an instance with a registry on it gets a fresh one
without it. If you want strict directives, build the environment yourself — see
[How directives work](/directives/).