---
layout: layouts/base.njk
permalink: /command-line/installation/
title: Installation
summary: The executables, the archive, Composer, Homebrew and winget — five ways to get the tool, and what each one needs.
section: The command line
order: 0
---

`mdoffice` is the whole suite at a terminal. This page is how to get it; [Basic
use](/command-line/basic/) is how to run it.

## The executables

Every release carries one file per platform, with no PHP and no extensions in it and
nothing to install:

| | |
| --- | --- |
| `mdoffice-<version>-macos-arm64` | macOS on Apple silicon |
| `mdoffice-<version>-macos-x86_64` | macOS on Intel |
| `mdoffice-<version>-linux-x86_64` | Linux on x86-64 |
| `mdoffice-<version>-linux-aarch64` | Linux on ARM |
| `mdoffice-<version>-windows-x64.exe` | Windows on x64 |

They come from [the releases page](https://github.com/markdown-office/mdoffice/releases),
and the page carries a `SHA256SUMS` file with the checksum of each of them.

**A download in a browser asks a question on a Mac that a download in a terminal does
not.** The quarantine flag is set by the application that fetched the file rather than
by where it came from, so a file saved by a browser is quarantined and Gatekeeper
asks — the dialog that says *Apple could not check whether "mdoffice" is free of
malware that could harm your Mac or violate your privacy*. It asks because the
signature is an ad-hoc one and the binary is not notarised. Fetching the file with
`curl` does not set the flag:

```bash
curl -L -o mdoffice https://github.com/markdown-office/mdoffice/releases/latest/download/mdoffice-0.1.0-macos-arm64
chmod +x mdoffice
```

If the dialog appears on a file that was fetched some other way, the flag comes off
like this, and the program runs:

```bash
xattr -d com.apple.quarantine mdoffice
```

The macOS executables are signed with an ad-hoc signature, which is not a developer
certificate and not notarised. It is what an arm64 Mac needs — an unsigned arm64
binary is killed by the kernel — and nothing more.

## The archive

The same program as a PHAR, for a machine that has PHP and would rather not download
a second copy of it:

```bash
curl -L -o mdoffice.phar https://github.com/markdown-office/mdoffice/releases/latest/download/mdoffice-0.1.0.phar
php mdoffice.phar word notes.md
```

The archive carries both converter libraries inside it. It needs PHP 8.2 or newer with
`ext-dom`, `ext-gd`, `ext-mbstring`, `ext-xmlwriter` and `ext-zip`.

## Composer

```bash
composer require markdown-office/mdoffice
```

That gives the command as `vendor/bin/mdoffice`.

**The project has to allow a plugin.** `markdown-presentation` writes a few things a
released PHPPresentation cannot — `Table::setFirstRow()`, `AutoShape::setRoundRectCorner()`
among them — and gets them from a patch applied to the installed package. The patch is
applied by [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches),
which Composer only runs when the project allows it:

```json
{
    "require": {
        "markdown-office/mdoffice": "^0.1"
    },
    "config": {
        "allow-plugins": {
            "cweagans/composer-patches": true
        }
    }
}
```

Without that line the install stops with Composer's own message naming the plugin.
Nothing else has to be relaxed: the package carries the stability it needs itself,
and every dependency it resolves to is a stable release.

## Homebrew

```bash
brew tap markdown-office/mdoffice
brew install mdoffice
```

The tap is [markdown-office/homebrew-mdoffice](https://github.com/markdown-office/homebrew-mdoffice),
and the formula carries the checksum of the file it installs. It installs the
binary for the machine it is on: Apple silicon or Intel, x86-64 or ARM.

## winget

```powershell
winget install markdown-office.mdoffice
```

The manifest is written from the release rather than by hand, so it carries the
checksum of the file it names.
