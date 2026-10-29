---
layout: layouts/base.njk
permalink: /word/templates/
title: Templates
summary: Rendering into a design that already exists — a Word template with its own styles, and a theme for one that is not a Word document.
section: Markdown Word
order: 3
---

## Two ways to bring a design

There are two, and they answer different questions.

| | The design is | You give it | It gives you |
| --- | --- | --- | --- |
| **A template** | a Word document — a `.docx` or a `.dotx` | the `styleId`s of its styles | its fonts, colours, spacing, headers and footers, and its letterhead |
| **A theme** | a directory of YAML | nothing | a look of your own, in every document that names it |

A template is a design somebody made in Word. A theme is a design you write down, and it is
the only one of the two that travels with the Markdown rather than with the document.

## A Word template

Point the renderer at the **styleIds** your template defines and it inherits the whole design
— fonts, colours, spacing, headers and footers.

```php
use MarkdownWord\Configuration;
use MarkdownWord\Configuration\Styles;
use MarkdownWord\Template\MarkdownTemplate;

$config = Configuration::create()->withStyles([
    Styles::HEADING_1 => 'ReportTitle',   // a styleId in your template
    Styles::PARAGRAPH => 'BodyText',
    Styles::BLOCK_QUOTE => 'PullQuote',
]);

(new MarkdownTemplate('report-template.docx', $config))
    ->insert(file_get_contents('summary.md'))
    ->save('northwind.docx');
```

A `.docx` and a `.dotx` are both accepted, and which one it is makes no difference to the
result: **a filled-in template is always a document.** See [What a `.dotx` costs](#what-a-dotx-costs)
for why that is worth saying.

Everything a template says is something Word already understands, because a template comes from
Word and anything else would have to be invented here:

| | |
| --- | --- |
| A value | a **`MERGEFIELD`**, the field a person inserts with `Ctrl+F9` |
| A place for the content | a **rich-text content control**, named `body` |

There is no third thing. A row of records is a table in the Markdown, and the template styles
it like any other content — which is why there is no loop to explain.

### The values

`fields()` fills the template's `MERGEFIELD`s, and the document carries its own values so
that the document and everything in it travel together:

```php
(new MarkdownTemplate('report-template.docx'))
    ->fields(['customer' => 'Northwind Ltd'])
    ->insert(file_get_contents('summary.md'))
    ->save('northwind.docx');
```

**Filled fields are unlinked.** The value stays and the field goes, which is what Word's own
*Finish & Merge → Edit Individual Documents* produces and what a finished document wants:
nothing to refresh with `F9`, and a value that can hold `&` or `<` without becoming an
instruction.

### The place for the content

**`body` is this library's convention, not Word's.** ECMA-376 §17.5.2.36 defines a content
control's `w:tag` as "an arbitrary string which applications can associate with a structured
document tag", reserves no value for it, and Word itself never reads one — it round-trips it.
So the name collides with nothing Word recognises, and no standard is being missed by not
matching one.

The control is matched on its **tag**, which is the field name Word gives it, rather than on
its alias: the alias is the label a person sees in Word's own UI and is the one they will
rename while editing the template, whereas the tag is what a program reads. A template that
has both is written by something that knows the difference.

A template that names its control something else is asked for that name:

```php
(new MarkdownTemplate('letterhead.docx'))->insert($markdown, 'letter');
```

Nobody else does this: the templating libraries that exist either use plain text delimiters
(`{name}` in docxtemplater, `{{ var }}` in python-docx-template) or invent their own prefix,
and none of them claims `body`.

### A template with no control

**A template need not be one this library's generator wrote.** A letterhead a company made in
Word to be filled in by a person has fields and no control, and refusing those would leave
the library able to use only the templates it wrote itself.

So a template with no control of that name takes the content **at the end of its body**, or
says so and refuses. Which one is the `contentFallback()` setting — `ContentFallback::Append`
or `ContentFallback::Error` — and the refusal is `--require-control` on the command line.

Filling the fields is independent of it: a template can have values and nowhere to put the
prose.

## The same thing from the frontmatter

A document may name its own template, and then nothing has to be supplied from PHP to
reproduce it:

```yaml
---
template_file: ../templates/report.dotx
fields:
  customer: Northwind Ltd
---
```

`template_file:` names a `.docx` or a `.dotx`, and the path is resolved against the
document's own directory. `fields:` fills the template's `MERGEFIELD`s, with `true` and
`false` spelled as strings because that is what Word stores.

The two routes are the same thing reached two ways, and the difference is where the styleIds
come from: the frontmatter route cannot name them, so it inherits the template's own styles
rather than mapping this library's slots onto them.

## What a `.dotx` costs

Nothing to use one, and one thing to *produce* from one: the result is a document, not a
filled template. That is the only difference between the two, and it is worth saying because
the alternative is worse.

A `.dotx` and a `.docx` are the same archive with one line of `[Content_Types].xml` different,
and that line is what Word decides a file by. A package typed as a template is opened by
*creating a new document from it* — double-clicking a filled-in `.dotx` gives you
`Document1.docx` rather than the file you were sent, and anything dispatching on MIME type
treats it as a template. So rendering into one writes a document, and says so by writing one:

```php
$template = new MarkdownTemplate('report-template.dotx');
$template->insert('body', $markdown)->save('northwind.docx');   // a .docx, whatever went in
```

## A theme

A theme is a directory: `theme.yml` — `name` required, plus `description` and `author` — and
an optional `styles.yml` holding the same slot names as
[the configuration](/word/configuration/#every-style-slot). One key of that file is not a slot:
`textStyles` holds the named styles a document may write a word in.

A document names one with `theme:`, and the ones that ship are listed with the configuration, on
[the themes](/word/configuration/#themes).

A theme may also name the `theme.yml` itself, in which case the `styles.yml` beside it is the
theme's, and a document that names one by path gets that one.

## The command line

```bash
mdoffice word to-docx notes.md --template report.dotx --require-control
```

`--template` takes a `.docx` or a `.dotx`, and `--require-control` refuses a template with no
content control instead of appending to it. The rest of the options are on
[For word](/command-line/word/).
