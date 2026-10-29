---
layout: layouts/base.njk
permalink: /presentation/errors/
title: Errors
summary: Every exception Markdown Presentation throws, and what each one means.
section: Markdown Presentation
order: 3
---

Every exception Markdown Presentation throws, in one place. The one to catch, and the advice that
is about both libraries, is on [Errors in general](/errors/).

## Presentation

<!-- reference: presentation.exceptions -->

| Exception | Means |
| --- | --- |
| `InvalidInput` | the base for everything a deck author can fix |
| `UnknownDirective` | a key or marker outside the closed set — the message names the set |
| `UnknownMarker` | `::click=0`, backwards, over 500, unclosed, or a stray closer |
| `UnknownLayoutRole` | `layout:` is not one of the layout roles |
| `UnknownTemplate` | a slide names a template the deck does not have — the message names the templates |
| `MalformedChrome` | a template holds ordinary Markdown |
| `MalformedTemplatesFile` | the templates file a deck names holds a slide |
| `TemplatesFileNotFound` | the templates file a deck names is not there, or is outside the roots |
| `TemplatesFileTooLarge` | the templates file a deck names is over the ceiling |
| `UnsupportedColumn` | more `::column` markers than the layout has columns |
| `UnsupportedImageFormat` | not an image, no decoder, or over the ceiling |
| `UnsupportedLink` | `javascript:`, `data:`, `phar://`, `file://`, a UNC share |
| `ContentOverflow` | a block does not fit and `strict` is on |
| `TooManySlides` | more slides than the ceiling allows |
| `TooManyShapes` | more shapes on a slide than the ceiling allows |
| `MalformedFrontmatter` | not a mapping, or a value of the wrong type |
| `FencedFrontmatter` | a ```` ```yaml ```` block at the top of a slide |
| `MalformedTheme` | a theme file is not shaped like one |
| `ThemeNotFound` | a theme name resolves to nothing — the message lists the ones that do |
| `UnknownStyleSlot` | `styles.yml` names a slot that does not exist |
| `MalformedTemplate` | a `template.pptx` is not one |
| `MalformedShape` | a free shape has an attribute it does not read, or a value it cannot be |
| `UnknownTextStyle` | an inline style names a style the theme does not have — the message names the styles |
| `MalformedTextStyle` | a text style has a property it does not have, or a value it cannot be |
| `UnknownFont` | the theme names a face there is no table of advance widths for |
| `ImageNotFound` | a path, a URL, or something outside the roots |
| `UnreadablePackage` | **not** `InvalidInput`; nothing a deck names fixes it |
| `UnreadableDeck` | **not** `InvalidInput`; the file is not a package this PHP can read |
| `FileNotWritable` | **not** `InvalidInput`; the target cannot be written |
| `NothingToConvert` | **not** `InvalidInput`; there was no deck to convert |
| `UnpatchedDependency` | **not** `InvalidInput`; the installed PHPPresentation lacks the fixes |

Five sit beside `InvalidInput` rather than under it: nothing a deck names fixes a file that cannot
be read, a target that cannot be written, a conversion with nothing to convert, or a dependency
that was installed without the fixes the library needs.

The four that carry a template are the deck author's to fix: a name nothing is under, a body
that holds the wrong thing, a file that holds a slide, and a file that is not there.
