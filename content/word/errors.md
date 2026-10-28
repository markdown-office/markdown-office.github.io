---
layout: layouts/base.njk
permalink: /word/errors/
title: Errors
summary: Every exception Markdown Word throws, and what each one means.
section: Markdown Word
order: 5
---

Every exception Markdown Word throws, in one place. The one to catch, and the advice that is
about both libraries, is on [Errors in general](/errors/).

## Word

<!-- reference: word.exceptions -->

| Exception | Means |
| --- | --- |
| `InvalidConfiguration` | the key half; the base for the next one |
| `UnknownConfigurationKey` | a key nothing reads |
| `InvalidConfigurationValue` | a key whose value is not what it is used as |
| `InvalidInput` | the base for input-shaped failures |
| `NothingToConvert` | no Markdown was given |
| `UnreadableFile` | the input is not a file |
| `UnreadableDocument` | the bytes are not a Word document |
| `MalformedDocument` | it is one, and it is broken |
| `FileNotWritable` | **not** `InvalidInput`; the target cannot be written |
| `TemplateNotFound` | `template_file:` names nothing |
| `NoContentControl` | `--require-control`, and the template has none |
| `MalformedTheme` | a theme file is not shaped like one |
| `ThemeNotFound` | a theme name resolves to nothing — the message lists the ones that do |
| `UnknownStyleSlot` | `styles:` names a slot that does not exist |
| `UnsupportedElement` | **not** `InvalidInput`; something in the document cannot be drawn |
| `UnsupportedImageFormat` | see [Images](/images/) |

