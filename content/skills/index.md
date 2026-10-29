---
layout: layouts/base.njk
permalink: /skills/
title: The skill
summary: A directory an agent reads, holding this site's documentation and the library's examples — and where each agent harness looks for one.
section: The skill
order: 0
---

An agent asked to turn Markdown into a Word document has to know four things: what the
library can do, how to build a document with it, how to *look* at what it built, and how to
use a design that already exists. This page is how it gets them.

## What it is

**A skill is a directory.** It holds a `SKILL.md` — a short account of what it is for and
when to use it — and beside it the files it points at: the documentation, the examples, and
the scripts that render a document so that it can be looked at.

**The one for this suite is `mdword`**, and it is built from this site. The pages in it are
the pages of this site; the examples in it are this site's own and the library's. Nothing in
it is written twice: it is a copy made by the build, and a copy that is made by the build
cannot drift from what it was made from.

## Install it

OpenCode reads it over the network and needs nothing downloaded — one line in
`opencode.json`:

```jsonc
{
  "$schema": "https://opencode.ai/config.json",
  "skills": ["https://markdown-office.github.io/skills/"]
}
```

Every other agent reads a directory, and the whole bundle is one file:

```bash
curl -LO https://markdown-office.github.io/skills/mdword.zip
unzip -q mdword.zip -d ~/.agents/skills/mdword
```

## Where it goes

| Agent | It looks here | What to do |
| --- | --- | --- |
| **Codex** | `~/.agents/skills/mdword` | nothing — that is where it is |
| **OpenCode** | `~/.agents/skills/mdword`, `~/.config/opencode/skills/mdword` and `~/.claude/skills/mdword` | nothing, or the catalog above |
| **Claude Code** | `~/.claude/skills/mdword` | a symlink to the unpacked copy |
| **GitHub Copilot** | `~/.copilot/skills/mdword`, and `.github/skills/mdword` in a repository | a symlink to the unpacked copy |

So the bundle is unpacked in one place and the other two are links to it:

```bash
ln -s ~/.agents/skills/mdword ~/.claude/skills/mdword
ln -s ~/.agents/skills/mdword ~/.copilot/skills/mdword
```

A repository can carry one of its own instead, under `.agents/skills/` or `.github/skills/`,
and it is found from the repository root rather than from the machine.

## Is the copy current?

The version is in `VERSION` beside the `SKILL.md`, and it is the same string as the `version`
in the published catalogue. A copy whose `VERSION` differs from the catalogue's is a copy
from before, and the catalogue is the newer of the two:

```bash
curl -s https://markdown-office.github.io/skills/index.json
```

## What is in it

```
mdword/
├── SKILL.md              what the library does, and how to use it
├── VERSION               the version of this bundle
├── references/           the documentation, one file per page
├── examples/             this site's examples, and the library's
│   ├── site/
│   └── library/
└── scripts/              the render tools
```

The **references** are the pages of this site, one file each, with the frontmatter off. The
**examples** are the decks and documents the pages show, built and named, and the library's
own twenty-three — which between them are the catalogue of what the library can do.

The **scripts** render a document so that it can be looked at, which is the one step a
prose answer cannot do for itself.

## What it is not

**It does not replace this documentation.** It is a copy of it, made by the same build that
makes the site, and the site is where a change is made first. A copy of the documentation is
not the documentation: it is a copy of it, as of the moment the copy was made.

And it is **not a second implementation of anything**. The library is the library; the skill
says how to drive it.
