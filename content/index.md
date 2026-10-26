---
layout: layouts/base.njk
permalink: /
title: Markdown, into the file you actually send
summary: Markdown to Word and to PowerPoint, in PHP. Both convert back.
section: Home
order: 0
---
<div class="hero">

<div class="hero__mark">{{ wordmark | safe }}</div>

# Markdown, into the file you actually send

A finished `.docx` or `.pptx`, from a Markdown file, in PHP. No Office installed, no headless
browser, no export button.

</div>

## Get it

{% tabs %}
{% tab "macOS" %}
```bash
brew tap markdown-office/mdoffice
brew install mdoffice
```
{% endtab %}
{% tab "Windows" %}
```powershell
winget install markdown-office.mdoffice
```
{% endtab %}
{% tab "Linux" %}
```bash
curl -L -o mdoffice https://github.com/markdown-office/mdoffice/releases/latest/download/mdoffice-0.1.1-linux-x86_64
chmod +x mdoffice
```
{% endtab %}
{% tab "PHP" %}
```bash
composer require markdown-office/mdoffice
```
{% endtab %}
{% endtabs %}

One command each, and a release carries its own PHP. The package managers need the first
release, which has not been published yet —
[Installation](/command-line/installation/) has the details and the way in until then.

## What each one does

|  | **Markdown Word** | **Markdown Presentation** |
| --- | --- | --- |
| **Markdown → document** | `.docx` `.odt` `.rtf` | `.pptx` |
| **document → Markdown** | **yes**, for standard Markdown | **yes** |
| **Markdown dialect** | CommonMark + GitHub Flavored | Slidev-inspired |
| **Look and feel** | frontmatter + themes | frontmatter + themes |
| **Special features** | directives | directives |
| **What those are** | `::toc`, `:footnote` | `::click`, `:::click`, `::column`, `::note` |
| **What it will not do** | a `:::toc` body, a one-line code block's language, a theme in a `.dotx` | a Slidev directive, `---` as a rule, an image over 16 megapixels |

Two of those lines are worth reading twice.

**Word converts both ways.** A `.docx` goes back to Markdown, and for standard Markdown it goes
back well — headings, emphasis, code, lists and tables all survive. Directives are the exception
and are written down rather than glossed over: a `::toc` comes back as the text
*"Press F9 to build the table of contents"*, which is what the document actually said, and a
`:footnote[…]` comes back as itself because it was never a footnote in the first place. See
[Round trips](/word/round-trip/).

**PowerPoint converts back too.** A `.pptx` goes back to Markdown, and what comes back is the
Markdown this package writes decks in — so a deck can be taken apart, edited as text and put
in again under a different theme. What comes back is the text and the structure; the design
does not travel, because a `.pptx` records what is on a slide and not how it was drawn.

<div class="hero__rule">The suite</div>

<ul class="cards">

<li>
<a class="card" href="/word/directives/">
<h3>Markdown Word</h3>
<p>Markdown to <code>.docx</code>, <code>.odt</code> and <code>.rtf</code> — and back to
Markdown. Templates, styles, headers and footers, and a table of contents that is a real Word
field.</p>
<code>markdown-office/markdown-word</code>
</a>
</li>

<li>
<a class="card" href="/presentation/directives/">
<h3>Markdown Presentation</h3>
<p>Markdown to <code>.pptx</code>, with a splitter ported from Slidev's. Layouts, themes,
columns, click builds and speaker notes — and a refusal for anything it will not draw.</p>
<code>markdown-office/markdown-presentation</code>
</a>
</li>

<li>
<a class="card" href="/directives/syntax/">
<h3>CommonMark Generic Directives</h3>
<p>The syntax both read: <code>:name</code>, <code>::name</code>, <code>:::name</code>. One
parser extension, for your own directives if you want one.</p>
<code>markdown-office/commonmark-generic-directives</code>
</a>
</li>

<li>
<a class="card" href="/command-line/basic/">
<h3>mdoffice</h3>
<p>The command line for both of them: <code>mdoffice word</code> and <code>mdoffice
slides</code>. Requires both converters and calls them — a program, not a facade, so neither
package depends on it.</p>
<code>markdown-office/mdoffice</code>
</a>
</li>

</ul>

<div class="hero__rule">Start here</div>

<ul class="actions">
<li><a class="primary" href="/word/">Markdown Word</a></li>
<li><a class="primary" href="/presentation/">Markdown Presentation</a></li>
<li><a class="ghost" href="/command-line/basic/">The command line</a></li>
<li><a class="ghost" href="/directives/syntax/">The directive syntax</a></li>
</ul>

## Every example here is a real document

Each one is converted by the library it documents while this page is built, and can be
downloaded and opened. If an example stops working, the build stops — which is how this site came
to say that `:footnote[…]` produces no footnote.

## Read it over HTTP

Nothing here needs a browser or a round trip. The same three files are at
[`llms.txt`](/llms.txt), [`llms-full.txt`](/llms-full.txt) and [`pages.json`](/pages.json),
and every page is available as Markdown at its own URL.

## Requirements

PHP 8.2 or newer, with `ext-dom`, `ext-gd` and `ext-zip` — plus `ext-mbstring` for the Word half.
`ext-imagick` is optional and buys SVG; without it an SVG is refused by name rather than quietly
rasterised badly.

Two extensions are asked for by the dependency rather than by this code, and only matter when the
extractions are compiled from source: `ext-zip`, which every normal PHP install bundles, and
`ext-xmlwriter`, which PHPWord uses to write a document and which `ext-xml` does **not** provide.
A from-source build that omits either produces an archive that reports its version and then
cannot write anything.

The command line needs none of this if you take a release instead: each executable carries its own
PHP. See [the command line](/command-line/basic/).