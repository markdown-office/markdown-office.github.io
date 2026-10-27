---
layout: layouts/base.njk
permalink: /directives/
title: How directives work
summary: One syntax, three shapes, and what each half of the suite does with a name it recognises — and with one it does not.
section: In general
order: 0
---

Both halves of the suite read the same syntax, and it is
[Generic Directives](https://github.com/markdown-office/commonmark-generic-directives) — one
`league/commonmark` extension, implemented in `markdown-office/commonmark-generic-directives`.

A directive is a run of colons, a name, and optionally a label and some attributes. **The number
of colons is the whole difference between the three shapes**, and it is what makes the syntax
readable at a glance:

| Shape | Written | Contains | Node |
| --- | --- | --- | --- |
| Text | `:name[label]{attrs}` | nothing — it sits in a paragraph | `DirectiveInline` |
| Leaf | `::name[label]{attrs}` | nothing — it is one line | `DirectiveBlock` |
| Container | `:::name{attrs}` … `:::` | any Markdown | `DirectiveBlock` |

There is no fourth shape. Four spaces of indent is a code block in CommonMark, not a directive,
and `{…}` is a modifier available on all three shapes rather than a shape of its own.

## What each half does with a name

Here the two packages make opposite decisions, and both are defensible.

**Markdown Word is permissive.** It draws exactly three directives — `::toc`, `:footnote` and
`:style` — and every named style the theme brings, and renders every other name as its own source
text. A document nobody intended as a set of directives keeps working, and the text an author
wrote is what a reader sees.

**Markdown Presentation has a closed set.** Anything outside it fails the build with a message
naming what would have worked. A deck is a thing a person presents, and a slide with the wrong
thing on it is worse than a deck that did not build. An inline directive in a deck is a
[text style](#a-word-in-a-style), so a name the theme does not have is refused there too.

Both then agree on one thing: an unrecognised directive is never silently dropped.

- [Word directives](/word/directives/) — `::toc`, `:footnote`, `:style`, and the rest rendered as
  text.
- [Presentation directives](/presentation/directives/) — `::click`, `:::click`, `::column`,
  `::note`, and the refusals.
- [The syntax itself](/directives/syntax/) — every rule, including the ones that surprise people.

## A word in a style

Both halves can write one word in a style of its own — a colour, a size, a weight — inside a
sentence that is otherwise one colour and one size. The syntax is the same in both, and the set of
attributes is the same in both: eight attributes, closed.

<!-- reference: text-style-attributes -->

| Attribute | Carries |
| --- | --- |
| `color` | the ink |
| `size` | the size of the type, in points |
| `font` | the family |
| `bold` | the weight |
| `italic` | the slant |
| `underline` | the underline |
| `strike` | the strike |
| `highlight` | a colour behind the word |

Two of the eight are not the same in both halves. A `color` is a role, a theme slot or a hex value
in Markdown Presentation, and six hexadecimal digits in Markdown Word. A `highlight` is drawn by
Markdown Word and **refused by name** by Markdown Presentation, which has no way to write one.

Three forms, one grammar:

| Written | Means |
| --- | --- |
| `:accent[brand]` | the word "brand", in the style the theme names `accent` |
| `:accent[brand]{color=FF0000}` | the same word, with the attributes laid over the style's |
| `:style[brand]{color=FF0000}` | the word "brand", in the attributes, with no theme style behind it |

`style` is reserved: a theme may not name a style `style`, and the raw form needs at least one
attribute — a style with neither a named style nor a property says nothing.

**An attribute overrides the style for the property it names, and for that property only.** A
property neither of them names is not set: the run takes it from the block it is in, and nothing is
reset to a default. A property set to `false` is not set either — it means "not on this run" rather
than "off", because neither format can write a weight of none.

**The label is plain text.** A directive's label is a string rather than Markdown, because
CommonMark's inline engine has no way for a parser to say "the rest of this run is mine". So a
complete Markdown construct in a label — `**text**`, `*text*`, `_text_`, `__text__` or `` `code` ``
— is not honoured as emphasis. A link cannot be one: a label holds no brackets at all, so there is
no way to write one. A bare `*` or `_` is a character rather than an intent, so `2 * 3` and
`snake_case` are ordinary text.

What the two halves do with a name they do not know, and with a label they cannot honour, is where
they part: [Markdown Presentation](/presentation/directives/#a-word-in-a-style) refuses both and
names what would have worked, and [Markdown Word](/word/directives/#a-word-in-a-style) writes the
directive out as its own source text.

## The exact grammar

Names are a letter followed by letters, digits, `_` and `-`. There are no namespace prefixes in
the document syntax: `w:toc` is not a thing, and a name that needs one is two names.

{% example %}
````md word "A directive nobody claims is written out as it was typed"
Status :green[go] before lunch.

:::note
This container is not one this library draws.
:::

::somethingelse{key=value}
````
{% endexample %}

Every line above parses. None of them is a Word directive, so none of them is drawn — they are
written into the document exactly as typed, which is what the output above is showing.

## Registering your own

The directives package is usable on its own. A renderer over the node, and a registry if you want
the parser to refuse what you have not listed:

```php
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use MarkdownOffice\CommonMark\Directive\Node\DirectiveBlock;
use MarkdownOffice\CommonMark\Directive\Directive\DirectiveRegistry;
use MarkdownOffice\CommonMark\Directive\GenericDirectiveExtension;

final class CalloutRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        \assert($node instanceof DirectiveBlock);

        return sprintf(
            '<aside class="callout callout--%s">%s</aside>',
            \htmlspecialchars($node->getAttributes()->getString('type', 'note')),
            $childRenderer->renderNodes($node->children()),
        );
    }
}

$environment->addExtension(new GenericDirectiveExtension(
    new DirectiveRegistry(['callout', 'badge:text']),
));

$environment->addRenderer(DirectiveBlock::class, new CalloutRenderer(), 10);
```

`match` is written without a leading backslash above for the same reason it is in this project:
`\match` is a parse error, because PHP lexes it as a namespaced name rather than the keyword.

**A registry with entries is strict.** An empty one is permissive, which is what the RFC
describes and what both halves of this suite rely on. The mode worth running in is the strict one,
when a machine is writing the Markdown: the alternative to an error is a document that renders
without the block it asked for and says nothing about why.

Note the `:text` suffix in `'badge:text'` — that is how a registry narrows a name to one shape.
It is a **registry** spelling, not a document one.