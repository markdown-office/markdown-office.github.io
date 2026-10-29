---
layout: layouts/base.njk
permalink: /files/
title: Where the files come from
summary: The finder both halves read their files through — where a theme, a picture and a template come from, and what decides what may be read.
section: In general
order: 3
---

A document's theme, its pictures and the template it renders into are files. They were files on
this machine, and now they are files wherever the calling program keeps them: **the finder is
where a document's files come from.**

Both halves of this suite have one, and the two have the same shape —
`MarkdownWord\Files\FileFinder` and `MarkdownPresentation\Files\FileFinder`.

The library composes *names* and asks. A finder answers. It never knows what a theme is, and it
never knows the files a theme is made of: it answers for the names it is handed, and lists what is
under a name it is handed. That is what lets a directory of themes on disk and a database of
themes that never had a directory serve the same library.

## The four methods

<!-- reference: finder-methods -->

| Method | Answers |
| --- | --- |
| `read` | the contents of a file a name identifies, or `null` when there is none |
| `path` | the path of a file a name identifies, when the finder holds it as a file |
| `themes` | the themes that resolve, sorted |
| `files` | the names of the files of a theme, sorted |

```php
namespace MarkdownWord\Files;

interface FileFinder
{
    public function read(string $name, ?string $role = null): ?string;

    public function path(string $name, ?string $role = null): ?string;

    public function themes(): array;

    public function files(string $theme): array;
}
```

`read()` is what the library wants for YAML, for Markdown and for a picture: the bytes themselves.
`path()` is for the two things a package has to be opened as — a theme's master and a document's
`template_file:`. A finder that holds a file answers both. A finder that holds only bytes answers
`read()`, and the library stages what it cannot be handed as bytes into a file of its own: a
`.pptx` is a package, and a package is opened as a file.

`themes()` is for a failure that has to name what would have worked. `files()` is for the question
a null cannot answer on its own: a theme that carries no master and a source that cannot serve
one are different problems, and the listing tells them apart.

## The three roles

The role is the **kind** of file. It says which world a name is resolved in, and it is a hint and
never a filter: a source that keeps its themes in a database may answer for a picture as readily
as for a theme.

<!-- reference: finder-roles -->

| Role | What it is | Where it is looked for |
| --- | --- | --- |
| `theme` | a file of a theme — its manifest, its slots, its master | the theme directories |
| `picture` | an image a document names | the picture roots |
| `document` | anything else a document names by path — a `.dotx`, a deck's templates file | the document's own directory, and the template roots |

The three names are the same in both halves, so one adapter serves both.

## The names the library composes

The finder answers for the names it is handed, and the names are the library's own.

| The library wants | It asks |
| --- | --- |
| the theme `corporate`'s manifest | `read('corporate/theme.yml', 'theme')` |
| the theme `corporate`'s slots | `read('corporate/styles.yml', 'theme')` |
| the theme `corporate`'s master | `read('corporate/template.pptx', 'theme')` |
| the picture `logo.png` | `read('logo.png', 'picture')` |
| the document's `report.dotx` | `read('report.dotx', 'document')` |

The two halves' file vocabularies differ, and that is fine: the Word half's `styles.yml` carries
`textStyles:` inside it, and the slides half has a separate `text-styles.yml`. The finder is never
told which is which — it is asked for a name.

## A finder answers; it does not refuse

**A file that is not there is `null`, and nothing else.** A finder never throws.

That is not a style preference. Each half of this suite has its own exception classes, so a finder
that refused would throw one half's exception and the other half would not catch it — and the two
interfaces would stop being the same, which is the whole reason there is one shape rather than two.

**The scope check therefore stays with the library**, where the messages are. The finder resolves a
name to a path; the library holds that path to the directories this render may read and refuses
what is out of reach in its own words. The two cases stay apart, because the finder is what makes
them apart: a name that resolves to nothing is `null`, and the library reports it as not there; a
name that resolves to a file outside the scope is a path, and the library refuses it.

The scope is a property of the *render*, not of the *storage*. A finder the calling program brought
serves that program's own files, and the program vouches for them.

## The disk finder

`Files\DiskFinder` is the default in both halves and has the same shape. It holds the bases — the
directories a name is looked for in — and nothing else.

```php
use MarkdownWord\Files\DiskFinder;

$finder = new DiskFinder(
    theme: ['themes'],
    picture: ['images'],
    document: ['.'],
);
```

The bases are given per role and are tried in the order they are given, so the calling program
decides where a document's files come from.

**A theme is resolved by its directory first.** The first base where `<base>/<theme>` is a
directory is the theme's directory, and the theme's files are looked for inside it. A theme
directory that exists but holds no manifest still shadows a later one, and is reported as a theme
without a manifest rather than as a theme that is not there.

**A name that is already a path is the path, whatever the role.** A name beginning with `/` names
a file, and resolving it against a base would name a file nobody meant.

## The Word half

`MarkdownToWord` takes the finder as `files:`, and it takes `roots:` as well — the directories a
document may name a theme in, for a shared library of themes beside the project.

```php
use MarkdownWord\MarkdownToWord;

(new MarkdownToWord($markdown, files: $finder))->save('report.docx');
```

A theme can be named in code, and it resolves through the finder:

```php
(new MarkdownToWord($markdown, files: $finder))->withTheme('house')->save('report.docx');
```

The name is resolved **once**: the theme that came back is kept, so a source that counted its
reads would say the name was asked for once and not once for the name and once for the document.

A document's own file is asked for as a `document`. A source that holds it as a file has it opened
where it is; a source that holds the bytes has them staged into a file of the library's own, and
the staged file goes when the template is done with — `Template\TemplateFile::release()` gives it
back, and a caller who lets the resolver go without saying so has it said for them.

```php
use MarkdownWord\Template\TemplateFile;

$template = new TemplateFile(directory: __DIR__, files: $finder);

$template->resolve('report.dotx');
```

`MarkdownTemplate` takes the same finder, for the document it inserts rather than for the
template it opens:

```php
use MarkdownWord\Template\MarkdownTemplate;

(new MarkdownTemplate('report.dotx', files: $finder))
    ->insert($markdown)
    ->save('report.docx');
```

## The slides half

A deck's files come from the same kind of source, in two places: the theme from the resolver, and
the pictures and the templates file from the renderer.

```php
use MarkdownPresentation\Render\PresentationRenderer;
use MarkdownPresentation\Theme\ThemeResolver;

$renderer = new PresentationRenderer(
    themes: new ThemeResolver(files: $finder),
    files: $finder,
);
```

A theme can be named in code here too, and it outranks the deck's own `theme:` — which is still
checked, because a value of the wrong type is a mistake in the deck whatever theme it is drawn in.

```php
$renderer = (new PresentationRenderer(themes: new ThemeResolver(files: $finder), files: $finder))
    ->withTheme('house');
```

**A theme's master is a file, and a file the library stages.** A `template.pptx` is a package, and
a package is opened as a file: a source that holds the master as bytes rather than as a file has
them staged into one, and the deck is drawn in the master's geometry all the same. The staged file
is the render's, and it is given back when the render is over — nothing of the library's is left on
the disk afterwards, whether the render worked or not.

**A master the source cannot serve is refused by name.** A theme that carries no master is a theme
that works, and nothing is said about it. A theme that carries one and a source that cannot hand
it over is refused with `MarkdownPresentation\Exception\UnservedTemplate` rather than drawn
without: a deck without the master's geometry is in the wrong design, with nothing in the file
saying so. The listing of a theme's files is what tells the two apart.

A deck can come back as bytes, the way a document can:

```php
$bytes = (new PresentationRenderer())->toString($deck);
```

## One adapter, both halves

The two interfaces have the same method names, the same parameter types and the same return
types, so one class implements both. This one holds its files in memory, and it is the whole of a
source that has bytes and no paths:

```php
use MarkdownPresentation\Files\FileFinder as SlidesFileFinder;
use MarkdownWord\Files\FileFinder as WordFileFinder;

final class MyFinder implements WordFileFinder, SlidesFileFinder
{
    /**
     * @param array<string, string> $files The files it holds, by name.
     */
    public function __construct(private readonly array $files = [])
    {
    }

    public function read(string $name, ?string $role = null): ?string
    {
        return $this->files[$name] ?? null;
    }

    public function path(string $name, ?string $role = null): ?string
    {
        return null;
    }

    public function themes(): array
    {
        $names = [];

        foreach (\array_keys($this->files) as $name) {
            $names[\explode('/', $name, 2)[0]] = true;
        }

        return \array_keys($names);
    }

    public function files(string $theme): array
    {
        $names = [];

        foreach (\array_keys($this->files) as $name) {
            if (\str_starts_with($name, $theme . '/')) {
                $names[\substr($name, \strlen($theme) + 1)] = true;
            }
        }

        return \array_keys($names);
    }
}
```

The claims suite checks that the two have not drifted apart, because that is what lets one adapter
serve both.

## An example, built through a finder

The document below was built with its theme in a finder rather than in a directory. The theme is
the site's own, and the site keeps it the way a plugin keeps its themes: in memory, under a name
the library composes.

{% example "A document whose theme came from a finder" %}
````md word finder
---
theme: house
---

# The house style

A document whose theme came from a finder rather than from a directory.

The theme's own `styles.yml` names the face and the colour of the heading, and the library
asked the finder for both of them.
````
{% endexample %}

And a deck, from the same source:

{% example "A deck whose theme came from a finder" %}
````md presentation finder
---
theme: house
---

# The house style

## A deck whose theme came from a finder

The theme is in the site's own source, not in a directory beside the deck.
````
{% endexample %}
