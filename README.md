# Markdown Office documentation

The documentation site for the Markdown Office suite. **Eleventy builds the pages; PHP builds the
examples.**

Every fenced block tagged with the direction it runs in — `md word`, `md presentation` or
`word md` — is converted by the library it documents, and the page shows what came out: the
`.docx` or `.pptx` to download, and either a picture of it or the text read back out of the built
file.

## Build it

```bash
composer install     # the three libraries, by path, so an edit to one is an edit to the docs
npm install          # Eleventy
npm run build        # build the examples, then the pages, into _site
npm run serve        # the same, with a watcher
```

```bash
composer check       # the examples, the command-line pages, the reference tables, the claims
composer test        # the claims on their own
```

```bash
npm run build:strict # ...and exit non-zero if an example does not build
```

```bash
PORT=9000 npm run serve   # the watcher, on the port you name
```

The watcher defaults to **8123**. Eleventy's own default is 8080, which is a popular port and on
the machine this was written on was already something else's — the failure that causes is an
"address in use", and nothing about it points at the real problem. `PORT` overrides it.

The watcher serves the site under the path the site is published under, which is the root of
the port: the site is at the root of its host, so it is at the root here too.

## Why the examples are not screenshots

A documented example is a claim about what a library does. A claim written by hand is correct only
until the library moves. Here the example **is** the library's output, so a change that breaks it
fails the build rather than the reader's afternoon.

That is not decoration — it caught three real errors in these pages while they were being written,
including one where an example claimed a Word footnote that the converter did not produce.

## The reference tables

The tables are claims too, and they drift: the site said four themes shipped when five did, and
its error page said it listed every exception the package throws and left five of them out.

So the tables are checked against the code, and a table says which vocabulary it carries:

```markdown
<!-- reference: presentation.layout-roles -->

| Role | Title box |
| --- | --- |
| `title` | yes |
```

`tools/check-reference.php` reads every vocabulary out of the library that owns it — the frontmatter
keys, the layout roles, the free shapes, the colour slots, the theme keys, the style keys, the
exceptions, the themes that ship, the ceilings, the Word directives, the features a Word format
cannot carry, the Word options, the Word style slots and the inline style attributes — and reads
the page that carries it for the names it lists. The two are compared in both directions: a name
the code does not have is a page offering something that does not work, and a name the code has
and the page does not is a feature nobody can find.

A vocabulary the two halves share is read out of both of them and held to one list, so the
attributes an inline style takes are the same eight in the deck and in the document — and the
check says so when they are not.

Three rules make the lists stay lists:

- **One list, one home.** A second copy of a table is a failure, because two copies drift apart and
  the one nobody reads is the one that is wrong. Every table that lists names is marked, and the
  marked one is the only one.
- **The code is the only source.** The lists are read out of the libraries, not written down in the
  site, so there is no second copy for the check to compare against.
- **A vocabulary with no home is a failure.** A check that passes because it found nothing to check
  is worse than no check at all.

What a table cannot hold is prose — what a key does, what an exception means — and a count written
out in a sentence ("Eight, and only these"). Those are claims too, and they are checked by the
suite in `tests/`: the counts against the code, the claims of completeness against the code, and the
rules against the library that has to behave that way. A sentence that was wrong and has been
corrected goes into the `FORBIDDEN` map of `tools/check-command-line.php`, and a page that says it
again is a failure.

The tables are not generated. Their other columns are prose that no code holds, and moving that
prose into a generator would make a second copy of the documentation in a file that is not a page —
the same drift, one repository further away.

## Writing a page

```text
content/
├── index.md                 the home page
├── word/                    the dialect, the directives, the configuration, the round trips, the images, the errors
├── presentation/            the dialect, the directives, the themes, the errors
├── general/                 how directives work, the syntax, the images, the errors
├── command-line/            the installation, the basic use, the options of each half
├── suite/                   the other packages
└── llms/                    llms.txt.njk, pages.json.njk
```

There are two entrypoints, one per library, and each is three things in order: **the dialect**
(basic, then frontmatter and themes, then directives), **the command line**, **the library**.

Four frontmatter keys, all required:

```yaml
---
layout: layouts/base.njk
permalink: /word/
title: Markdown Word
summary: One line. It is the sidebar text and the llms.txt entry.
section: Markdown Word
order: 0
---
```

`section` must be one of the five in `SECTIONS` in `eleventy.config.js`. A page naming something
else is listed under **Elsewhere** rather than dropped, so a typo is visible instead of silent.

**Every route has a Markdown representation**, and the build fails without one. It is checked
rather than assumed: a page that reached the site without a mirror looks perfect in a browser and
is invisible to anything reading it as Markdown, and nothing else would have said so.

## Writing an example

````markdown
{% example "A titled document" %}
```md word
# Notes from the review

The library walks the **syntax tree**, not the HTML.
```
{% endexample %}
````

The tag is paired rather than a bare fence so the fence stays a fence for the Markdown parser —
which matters on the pages that document this syntax themselves. The fence needs **four** backticks
when the example itself contains a fenced block.

The fence's tag says which way the example runs: the format it starts in, and the format it ends
in. `md word` builds a Word document from Markdown, `md presentation` builds a deck, and
`word md` reads a Word document back into Markdown. The body of the fence is the input — the
Markdown for the first two, the path of the document for the last, relative to the site's own
directory:

````markdown
{% example "A Word document read back to Markdown" %}
```word md
_documents/quarterly-report.docx
```
{% endexample %}
````

The direction decides what the download on the page says: a document the example built is its
**output**, and a document it read is its **source**.

The shortcode emits an HTML comment and the layout expands it. That is not indirection for its own
sake: a shortcode's output goes back into the Markdown *before* markdown-it runs, so a shortcode
returning `<figure>` has its own contents read as Markdown. An `**` in a sample heading becomes
`<strong>`, and the backticks around a sample fence open a code block that swallows the page.

## Highlighting

Shiki, as a pass over the finished HTML rather than inside the Markdown pipeline. An example's
source is not a code block at markdown-it time — the tag is a comment and the layout expands it
afterwards — so anything running during the Markdown pass would never have seen one. A single pass
over the finished pages reaches both the prose blocks and the example sources.

## Reading it as a machine

| | |
| --- | --- |
| `/llms.txt` | the index, one line per page |
| `/llms-full.txt` | every page, as the Markdown it was written in |
| `/pages.json` | the index as data |
| `/<page>/index.md` | any page as Markdown |

All four are generated from the sources, not from the rendered HTML — a `llms-full.txt` full of
`<h2>` is not a Markdown file.

Those are the site's own routes. The site is served at the root of its host, so the built
files carry no path in front of them: the addresses in `llms.txt` and `pages.json` are
absolute, and the links inside the Markdown mirrors are the routes as they are written.

## Where it is published

`.github/workflows/pages.yml` builds the site and publishes it to
**<https://markdown-office.github.io/>**. A push to `main` deploys it; a pull request builds
it and proves it is sound, without publishing it.

`MARKDOWN_OFFICE_CANONICAL` is the address the site is served from, path included, and it is
the only setting there is. The path in it is the path the site is mounted under, so that one
setting decides the canonical addresses, the links in the built pages, and the address the
READMEs of the other repositories point at.

The site is at the root of its host because the repository is named `markdown-office.github.io`:
GitHub serves a repository of that name at the root of the organisation's site, and any other
name under a path of its own. That is the same shape the site will have on its own domain, so
the build does not change when the domain is pointed at GitHub — only the address does.

The build checks its own links before it publishes:

```bash
npm run check:site
```

Every root-relative link in the built HTML has to point at a page that is in the site, and
every page has to be canonical at the address it is served from. A site whose links 404 is
worse than no site, and nothing else says so.

### The custom domain

`markdown-office.dev` is registered but has no DNS records, so GitHub cannot verify it. A
`CNAME` naming it would leave the site published at an address that does not resolve, and
that is the one thing the site must not be. There is no `CNAME` file, deliberately: the file
is added in the same commit that points the domain at GitHub and moves the canonical address
to it, and the site is published at the GitHub Pages address until then.

## Layout

```text
content/          the pages, one directory per chapter
_includes/        the layout, the stylesheet, the wordmark
_documents/       the documents the examples read. Committed: an input must not change.
src/              the reference tables, read out of the libraries and compared with the pages
tests/            the claims the pages make in prose
tools/            render-examples.php, make-input-document.php, the three checks, and lib/
_examples/        built artifacts, published under the site's own path. Not committed.
_data/            examples.json, written by the example builder. Not committed.
_site/            the built site. Not committed.
```

`content/` is one directory per chapter: `word/`, `presentation/`, `general/`, `command-line/` and
`suite/`. A page's `section` says which chapter it is in and its `order` says where in it, and the
menu and the pager both read those two keys — the menu sorts by them rather than by the order the
files happen to be read in.

The navigation is one list in the layout, and it is a **popover**: on a narrow screen it is a
drawer over the page, opened by the button in the bar, and on a wide one it is the column of
links beside the page. The browser opens it, closes it on Escape, closes it when the page outside
is touched, and moves the focus into it and back — so the drawer needs no script, and the site
still ships none.

`tools/lib/` is a handful of small classes and an autoloader of their own, not a Composer package:
the site is built by Eleventy and this is the part it cannot do, so there is no autoloader to
configure.

`tools/make-input-document.php` writes the document in `_documents/`. It is run by hand, and its
result is committed: see `_documents/README.md` for why an input must not be built.

## Why there is no HTTP endpoint

There was one, and it was removed. It did the same thing the build does — Markdown to a document —
on a timer instead of at build time, and for documentation the build is strictly the better of the
two: it can fail the build, it is cached, it pre-renders page images, and every example is already
a real downloadable file.

What it uniquely offered was *try your own Markdown*, which is a playground rather than
documentation. It needs a PHP-capable host that the static site otherwise does not, and the
libraries are two calls away for anyone who has PHP. If a playground is wanted later it should be
built as one, with its own reasons.

## When there are no preview images

A page image needs a renderer, and a build machine may not have one. A Word document is drawn by
[WordCraft](https://github.com/storytold/wordcraft) and a deck by
[DeckCraft](https://github.com/storytold/deckcraft) — both are looked for on the path as
`wordcraft-cli` and `deckcraft-cli` — and the build **degrades rather than hanging** when they
are missing: every command has a deadline, and a document it cannot draw gets no picture rather
than a failed build.

The published site has the pictures: the workflow installs both tools before it builds, so the
images are drawn on the machine that publishes the pages rather than on the one that wrote them.
The fonts the tools draw with ship with them, so a document looks the same wherever it is drawn.

With no picture, each example shows its document's text read back out of the built `.docx` or
`.pptx` — read from the artifact rather than from the Markdown that produced it, so it is evidence
rather than a restatement of the source.
