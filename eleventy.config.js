import { mkdir, readFile, writeFile } from "node:fs/promises";
import { existsSync, readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { EleventyHtmlBasePlugin } from "@11ty/eleventy";
import { createHighlighter } from "shiki";

import { asMarkdown, hashOf } from "./tools/markdown.js";
import { parseYaml } from "./tools/yaml.js";
import { writeSkill } from "./tools/write-skill.js";

const here = dirname(fileURLToPath(import.meta.url));

/**
 * The address the site is served from, without a trailing slash.
 *
 * The path is part of the address and not a setting of its own, because the two cannot disagree:
 * a site served at `https://host/docs/` is canonical at `https://host/docs/…` and nowhere else.
 * One variable therefore carries both — the address and the path it is mounted under — and
 * moving the site is a matter of setting it once.
 */
const canonical = (
  process.env.MARKDOWN_OFFICE_CANONICAL ?? "https://markdown-office.github.io"
).replace(/\/+$/, "");

/**
 * The path the site is mounted under, as Eleventy wants it: a leading and a trailing slash.
 *
 * A site at the root of a host has no prefix, and Eleventy's own default of `/` is right for it.
 */
const pathPrefix = mountPath(canonical);

/**
 * The pages of the site, grouped for the sidebar.
 *
 * A hand-written menu is a menu that rots, and it rots silently: the page it names is deleted,
 * the build still passes, and the link 404s for whoever clicks it. Building the menu from the
 * collection makes that impossible — a page cannot be in the site and not in the menu.
 *
 * The five entries below are the whole structure. A page naming one of them is grouped under it;
 * a page naming something else is listed last, because a section nobody declared is a page
 * nobody will find and dropping it would hide the mistake rather than fix it.
 */
const SECTIONS = [
  ["Markdown Word", "The dialect, the directives, the configuration, and the errors."],
  ["Markdown Presentation", "The dialect, the directives, the themes, and the errors."],
  ["In general", "The parts that are about both libraries."],
  ["The command line", "One command over both of them."],
  ["The skill", "What an agent can install, and where it goes."],
  ["The suite", "The other packages, and where this is going."],
];

/**
 * What the PHP example builder produced, read once at startup.
 *
 * Read here rather than from Eleventy's `_data` directory because a shortcode has no access to
 * the data cascade. A missing file is an empty manifest, not a crash: a first build with no
 * examples yet should still render every page.
 */
const examples = readManifest();

export default function (eleventyConfig) {
  eleventyConfig.addPlugin(EleventyHtmlBasePlugin);

  eleventyConfig.addPassthroughCopy({ "_includes/assets": "assets" });

  // The examples are built by PHP before Eleventy runs and copied in whole, rather than written
  // into the output directory by something Eleventy knows nothing about: Eleventy empties its own
  // output on every build, and a preview image that survived that would be a preview of an
  // example that no longer exists.
  eleventyConfig.addPassthroughCopy({ "_examples": "examples" });

  // Paired, because the tag wraps the fence; addShortcode would leave {% endexample %} as
  // literal text for Nunjucks to choke on.
  eleventyConfig.addPairedShortcode("example", example);
  eleventyConfig.addFilter("expandExamples", expandExamples);

  // A set of tabs is a set of radio buttons and a set of panels, and CSS alone decides which
  // panel the checked button shows: the site has no script, and a set of tabs is not the
  // place to start one. The buttons keep the focus, so the arrow keys move between the tabs.
  eleventyConfig.addPairedShortcode("tabs", tabs);
  eleventyConfig.addPairedShortcode("tab", tab);
  eleventyConfig.addFilter("expandTabs", expandTabs);

  eleventyConfig.addFilter("sections", sectionGroups);
  eleventyConfig.addFilter("pager", pager);

  // A table wider than the screen scrolls inside a box of its own rather than widening the page.
  // The wrapper is what it scrolls in: a table made `display: block` would scroll too, but it
  // stops being a table and the columns lose their alignment.
  eleventyConfig.amendLibrary("md", (md) => {
    md.renderer.rules.table_open = () => '<div class="table-scroll">\n<table>';
    md.renderer.rules.table_close = () => "</table>\n</div>";
  });

  eleventyConfig.addGlobalData("suite", () => ({
    name: "Markdown Office",
    canonical,
    sections: SECTIONS.map(([title, note]) => ({ title, note })),
  }));

  // The wordmark goes into the page rather than being fetched: it is above the fold on every
  // page, and a 2 KB request before anything can be read is a delay nobody asked for. The
  // `width` and `height` go so the stylesheet owns the size, and `role` and `aria-label` go
  // because the link that wraps it already carries the wordmark's name.
  eleventyConfig.addGlobalData("wordmark", () =>
    readFileSync(join(here, "_includes/assets/logo.svg"), "utf8")
      .replace(/\s(width|height|role|aria-label)="[^"]*"/g, "")
      .replace("<svg ", '<svg class="wordmark" '),
  );

  eleventyConfig.on("eleventy.after", highlight);
  eleventyConfig.on("eleventy.after", writeMirrors);
  eleventyConfig.on("eleventy.after", writeSkill);

  return {
    dir: {
      input: "content",
      output: "_site",
      includes: "../_includes",
      data: "../_data",
    },
    // The prefix every internal link and permalink is served under. Eleventy puts it on the
    // links in the built HTML; the canonical addresses above carry it already.
    pathPrefix,
    markdownTemplateEngine: "njk",
    htmlTemplateEngine: "njk",
    templateFormats: ["md", "njk"],
  };
}

/**
 * The path an address is served under, as a path: `/documentation/`, or `/` for a host's root.
 *
 * Throws rather than guessing, because a canonical address that is not an address produces a
 * site whose links point nowhere and whose error says nothing about which setting is wrong.
 */
function mountPath(address) {
  let path;

  try {
    path = new URL(address).pathname;
  } catch {
    throw new Error(
      `MARKDOWN_OFFICE_CANONICAL is "${address}", which is not an address. `
        + "It has to be the URL the site is served from, path included.",
    );
  }

  return path.replace(/\/?$/, "/");
}

/**
 * `{% example "A title" %} … {% endexample %}` — a placeholder for an example, expanded by the
 * layout once the Markdown is done.
 *
 * The paired tag rather than a bare fence, because the fence has to stay a fence for the Markdown
 * parser to read it as one, and a page that documents this syntax needs to show an example without
 * building it.
 *
 * This returns an HTML comment and nothing else, and that is the whole trick. A shortcode's
 * return value goes back into the Markdown *before* markdown-it runs, so a shortcode that
 * returned `<figure>` would have its own contents read as Markdown: the `**` in a sample heading
 * became `<strong>`, and the backticks around the sample fence opened a code block that swallowed
 * the rest of the page. An HTML comment survives Markdown untouched and is expanded afterwards by
 * {@see expandExamples}, where the output is the last thing to touch it.
 *
 * The title travels in the placeholder rather than in the manifest, because the manifest is keyed
 * by content and two pages may show the same document under different names.
 */
function example(content, ...args) {
  const title = String(args[0] ?? "")
    .trim()
    .replace(/^["']|["']$/g, "");
  const source = content.trim();
  const hash = hashOf(source);

  return `<!--mo-example:${hash}:${Buffer.from(title, "utf8").toString("base64")}-->`;
}

/**
 * Turn every `<!--mo-example:…-->` placeholder into the markup for that example.
 *
 * Applied to the finished page in the layout. `content` is HTML by this point, so the result is
 * inserted as-is rather than parsed, and the only thing to escape is what came out of a document.
 */
function expandExamples(html) {
  const shown = new Map();

  return String(html ?? "").replace(
    /<!--mo-example:([0-9a-f]{16}):([A-Za-z0-9+/=]*)-->/g,
    (_, hash, encoded) => {
      const occurrence = (shown.get(hash) ?? 0) + 1;

      shown.set(hash, occurrence);

      return render(hash, Buffer.from(encoded, "base64").toString("utf8"), occurrence);
    },
  );
}

/**
 * One tab of a set.
 *
 * The label is the tab's own text, and the content is the panel. Both go into the page as
 * markers, and the markup is built when the page is finished: the content is Markdown, and
 * Markdown is rendered after the shortcodes have run.
 */
function tab(content, ...args) {
  const label = String(args[0] ?? "")
    .trim()
    .replace(/^["']|["']$/g, "");

  return [
    `<!--mo-tab:${Buffer.from(label, "utf8").toString("base64")}-->`,
    content.trim(),
    "<!--/mo-tab-->",
  ].join("\n");
}

/**
 * A set of tabs.
 */
function tabs(content) {
  return `<!--mo-tabs-->\n${content.trim()}\n<!--/mo-tabs-->`;
}

/**
 * Turn every `<!--mo-tabs-->…<!--/mo-tabs-->` block into the markup for a set of tabs.
 *
 * Applied to the finished page, after the examples have been built: the panels hold the
 * rendered Markdown, and the markup is put around them rather than inside them.
 */
function expandTabs(html) {
  let sets = 0;

  return String(html ?? "").replace(
    /<!--mo-tabs-->\n?([\s\S]*?)\n?<!--\/mo-tabs-->/g,
    (_, body) => renderTabs(body, ++sets),
  );
}

/**
 * The markup for one set of tabs.
 *
 * The buttons come first, each followed by its own label, and the panels follow. That order
 * is what the CSS is written against: a button and its label are neighbours, so the ring
 * around a focused button is drawn on the label, and a button and its panel are siblings, so
 * a checked button can hide the panels that are not its own.
 */
function renderTabs(body, set) {
  const panels = [];
  const pattern = /<!--mo-tab:([A-Za-z0-9+/=]*)-->\n?([\s\S]*?)\n?<!--\/mo-tab-->/g;
  let match;

  while ((match = pattern.exec(body)) !== null) {
    panels.push({
      label: Buffer.from(match[1], "base64").toString("utf8"),
      html: match[2].trim(),
    });
  }

  if (panels.length === 0) {
    // A set of tabs with nothing in it is a mistake, and the content is better
    // than the silence: it is shown as it was written rather than dropped.
    return body;
  }

  const name = `mo-tabs-${set}`;
  const parts = [];

  panels.forEach((panel, index) => {
    const id = `${name}-${index}`;

    parts.push(
      `<input class="tabs__radio" type="radio" name="${name}" id="${id}"` +
        (index === 0 ? " checked" : "") +
        ">",
      `<label class="tabs__tab" for="${id}">${escape(panel.label)}</label>`,
    );
  });

  for (const panel of panels) {
    parts.push(`<div class="tabs__panel">\n${panel.html}\n</div>`);
  }

  return `<div class="tabs">\n${parts.join("\n")}\n</div>`;
}

function render(hash, title, occurrence) {
  const built = examples[hash];
  const source = built?.source ?? "";

  if (!built) {
    return [
      '<figure class="example example--missing">',
      '<p class="example__failure"><strong>No build was found for this example.</strong>',
      "Run <code>npm run examples</code> to build it.",
      "</p></figure>",
    ].join("");
  }

  if (built.failure) {
    return [
      `<figure class="example example--failed" data-language="${escape(built.language)}">`,
      head(built, title),
      `<p class="example__failure"><strong>This example did not build.</strong> ${escape(built.failure)}</p>`,
      sourceOf(source, built),
      "</figure>",
    ].join("");
  }

  const previews = (built.previews ?? [])
    .map((href, index) => {
      const label = `${title || built.name}, page ${index + 1}`;
      const id = `example-${hash}-${occurrence}-page-${index + 1}`;

      // The thumbnail opens the picture over the page rather than in a new tab, and it opens
      // it with CSS alone: the link points at the overlay's id, and the overlay is shown when
      // it is the target of the page's fragment. The browser's back button closes it again,
      // and so does the link in its corner.
      //
      // The picture is offered on its own as well, because a reader who wants the file is
      // not served by a picture of it.
      return (
        `<a class="example__page" href="#${id}">` +
        `<img src="${escape(href)}" alt="${escape(label)}"` +
        ` loading="lazy" decoding="async">` +
        `<span class="example__page-no">${index + 1}</span></a>` +
        `<div class="overlay" id="${id}">` +
        `<a class="overlay__backdrop" href="#example-${hash}-${occurrence}" tabindex="-1" aria-hidden="true"></a>` +
        `<img class="overlay__image" src="${escape(href)}" alt="${escape(label)}">` +
        `<a class="overlay__open" href="${escape(href)}" target="_blank" rel="noopener">Open the image</a>` +
        `<a class="overlay__close" href="#example-${hash}-${occurrence}">Close</a>` +
        `</div>`
      );
    })
    .join("");

  const body = previews
    ? `<div class="example__pages">${previews}</div>${downloads(built)}`
    : [
        '<p class="example__notice">This example built. It has no preview image, because the machine ',
        'that built this page had no WordCraft or DeckCraft to draw it with. What follows is ',
        built.role === "source"
          ? "the text of the document it read."
          : "read back out of the document it produced.",
        "</p>",
        outline(built),
        downloads(built),
      ].join("");

  return [
    `<figure class="example" id="example-${hash}-${occurrence}" data-language="${escape(built.language)}">`,
    head(built, title),
    sourceOf(source, built),
    body,
    "</figure>",
  ].join("");
}

/**
 * The document's own text, as read out of the built file.
 *
 * The fallback for a machine with no renderer, and a better one than a page of downloads with
 * nothing under them: this is read from the artifact rather than from the Markdown that produced
 * it, so a converter that dropped something shows up here as something missing.
 */
function outline(built) {
  const pages = built.contents ?? [];

  if (!pages.length) {
    return "";
  }

  return (
    '<div class="example__outline">' +
    pages
      .map(
        (page) =>
          `<div class="example__outline-page"><h4>${escape(page.heading)}</h4><ul>` +
          (page.lines ?? []).map((line) => `<li>${escape(line)}</li>`).join("") +
          "</ul></div>",
      )
      .join("") +
    "</div>"
  );
}

function head(built, title) {
  return (
    '<figcaption class="example__head">' +
    `<span class="example__badge">${built.language === "presentation" ? "PowerPoint" : "Word"}</span>` +
    `<span class="example__name">${escape(title || built.name)}</span>` +
    `<span class="example__made">${built.role === "source" ? "read" : "built"} by the library this page documents</span>` +
    "</figcaption>"
  );
}

function sourceOf(source, built) {
  // Open, not folded. A reader came to this page for the Markdown, and the document below is
  // what proves it — so the Markdown is the first thing in the figure rather than a disclosure
  // under a summary they have to guess is there.
  //
  // The summary says which Markdown it is, because an example that reads a document shows the
  // Markdown that came out of it rather than the one that went in.
  return (
    `<details class="example__source" open><summary>${
      built?.role === "source" ? "The Markdown it reads back as" : "The Markdown"
    }</summary>` +
    `<pre><code class="language-markdown">${escape(source)}</code></pre></details>`
  );
}

function downloads(built) {
  const links = [];

  if (built.document) {
    links.push(
      download(
        built.document,
        built.document.split("/").pop(),
        built.role === "source"
          ? "The document this example read, editable in Word, LibreOffice or Google Docs"
          : "The document this example built, editable in Word, LibreOffice or Google Docs",
        built.role,
      ),
    );
  }

  if (built.pdf) {
    links.push(
      download(built.pdf, "PDF", "The same document as a PDF, for reading without Office", "PDF"),
    );
  }

  return links.length ? `<p class="example__downloads">${links.join("")}</p>` : "";
}

function download(href, label, about, suffix) {
  return (
    `<a class="example__download" href="${escape(href)}" download title="${escape(about)}">` +
    `<span>${escape(label)}</span><abbr>${escape(suffix)}</abbr></a>`
  );
}

function readManifest() {
  try {
    return JSON.parse(readFileSync(join(here, "_data/examples.json"), "utf8"));
  } catch {
    return {};
  }
}

/**
 * Syntax-highlight every code block on every page, as a pass over the finished HTML.
 *
 * After the build rather than during it, for a reason that took a while to see: an example's
 * source is not a code block at markdown-it time. The `{% example %}` tag becomes a comment, the
 * layout expands it, and only *then* is there a `<pre>` holding the Markdown — so anything that
 * ran inside the Markdown pipeline would never have seen it. One pass over the finished pages
 * catches both the prose blocks and the example sources, which is the only place a single pass
 * can reach.
 *
 * Shiki rather than a class-per-token scheme because the colours are the point: a Markdown example
 * on a page about Markdown is unreadable as flat monospace, and guessing at token classes by
 * hand gets them wrong. The theme is the light one, chosen to sit on the same background as the
 * rest of the page rather than looking like a different website embedded in this one.
 */
async function highlight({ results, dir }) {
  const pages = (results ?? []).filter((result) => result.outputPath?.endsWith(".html"));
  if (!pages.length) {
    return;
  }

  const highlighter = await createHighlighter({
    themes: ["github-light"],
    langs: ["markdown", "php", "bash", "yaml", "json", "text", "diff"],
  });

  for (const { outputPath } of pages) {
    const html = await readFile(outputPath, "utf8");

    // markdown-it writes `<pre><code class="language-x">`; the example widget writes
    // `<pre class="code"><code class="language-x">` so both shapes are found by one expression.
    const marked = html.replace(
      /<pre(?: class="code")?><code(?: class="language-([\w-]+)")?>([\s\S]*?)<\/code><\/pre>/g,
      (_, language, code) => {
        const text = code
          .replace(/&lt;/g, "<")
          .replace(/&gt;/g, ">")
          .replace(/&quot;/g, '"')
          .replace(/&#39;/g, "'")
          .replace(/&amp;/g, "&");

        const lang = highlighter.getLoadedLanguages().includes(language) ? language : "text";

        return highlighter.codeToHtml(text, { lang, theme: "github-light" });
      },
    );

    if (marked !== html) {
      await writeFile(outputPath, marked);
    }
  }
}

/**
 * Write each page's Markdown beside its HTML, and assemble the whole set into `llms-full.txt`.
 *
 * Both are written from the source file rather than from the rendered page, because the rendered
 * page is HTML and neither of these is meant to be HTML. A link of type `text/markdown` in the
 * head points at the mirrors, and they are the point of the site for a reader that is not a
 * browser: the directive in front of it rather than a `<pre>` with HTML escaped inside it.
 *
 * Generated here rather than as an Eleventy template because a template only ever sees
 * `templateContent`, which is the page *after* Markdown — a `llms-full.txt` full of `<h2>` and
 * `<pre>` is not a Markdown file and not much use to anything reading it.
 */
async function writeMirrors({ results, dir }) {
  // Read from the source rather than from `result.data`: a build result carries the input path and
  // the URL, and this hook has to work for a template whose data Eleventy computed rather than
  // declared — a page with `eleventyComputed` frontmatter has a `section` that never appears in
  // the file on disk.
  const pages = (results ?? [])
    .filter((result) => result.inputPath?.endsWith(".md") && result.url)
    .map((result) => {
      const source = withMountPath(readFileSync(result.inputPath, "utf8"));

      return { url: result.url, ...frontmatter(source), source };
    })
    .filter((page) => page.section);

  // Anything a template computed goes back on top of what the file declared.
  for (const result of results ?? []) {
    const page = pages.find((candidate) => candidate.url === result.url);

    if (page && result.page?.data) {
      Object.assign(page, frontmatterOf(result.page.data));
    }
  }

  for (const { url, source } of pages) {
    const directory = join(dir.output, url.replace(/^\//, ""));
    const [, frontmatter = "", body = ""] =
      source.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n?([\s\S]*)$/) ?? [];

    await mkdir(directory, { recursive: true });
    await writeFile(join(directory, "index.md"), `---\n${frontmatter}\n---\n\n${body.trim()}\n`);
  }

  // Reading order, which the build order is not: pages are grouped by section and then by the
  // `order` each page declares, so `llms.txt` and this file describe the site in the same sequence.
  pages.sort(
    (a, b) =>
      SECTIONS.findIndex(([title]) => title === a.section) -
        SECTIONS.findIndex(([title]) => title === b.section) ||
      a.order - b.order ||
      a.url.localeCompare(b.url),
  );

  const whole = pages
    .map(
      ({ url, title, source }) =>
        `---\n\n<!-- ${canonical}${url} -->\n\n## ${title}\n\n${asMarkdown(source)}\n\nSource: ${canonical}${url}\n`,
    )
    .join("");

  // Every route, a Markdown representation. Checked rather than assumed: a page that reached the
  // site without one is invisible to anything reading it as Markdown, and nothing else would say
  // so — the page looks perfect in a browser.
  const missing = (results ?? [])
    .filter((result) => result.outputPath?.endsWith(".html"))
    .filter((result) => !existsSync(result.outputPath.replace(/index\.html$/, "index.md")));

  if (missing.length) {
    throw new Error(
      `No Markdown at ${missing.map((result) => result.url).join(", ")}. `
        + "Every route needs one; a page that reaches the site without it is invisible to "
        + "anything reading it as Markdown.",
    );
  }

  await writeFile(
    join(dir.output, "llms-full.txt"),
    `# Markdown Office — complete documentation\n\nOne Markdown document becomes a Word document or a\nPowerPoint deck, in PHP, with no Office and no binaries.\n\n${whole}`,
  );
}

/**
 * The links in a page's Markdown, carrying the path the site is served under.
 *
 * Eleventy rewrites the links in the finished HTML; the Markdown mirrors are written here, and
 * a link to `/guides/round-trip/` in one of them points at the root of the host, which is not
 * where the site is.
 *
 * The examples are left as they are: they are what a reader writes in their own document, where
 * the path of this site means nothing.
 */
function withMountPath(source) {
  const prefix = pathPrefix.replace(/\/+$/, "");

  return source
    .split(/(\{%\s*example[\s\S]*?\{%\s*endexample\s*%\})/)
    .map((part) =>
      part.startsWith("{%")
        ? part
        : part
            .replace(/\]\(\//g, `](${prefix}/`)
            .replace(/\]:\s*\//g, `]: ${prefix}/`)
            .replace(/href="\//g, `href="${prefix}/`),
    )
    .join("");
}

/**
 * The four keys the mirrors and the full-text file need, out of a page's frontmatter.
 *
 * Read from the file rather than from the build result because a build result does not carry the
 * data with it, and the one thing a page is required to have is these four keys — a page missing
 * one is a page with nowhere to put its title.
 */
function frontmatter(source) {
  const block = source.match(/^---\r?\n([\s\S]*?)\r?\n---/)?.[1];

  // A page with no frontmatter block at all is a page with no keys, and `parseYaml("")` is an
  // error rather than an empty document — so the empty case is answered here rather than passed on.
  return block === undefined || block.trim() === "" ? frontmatterOf({}) : frontmatterOf(parseYaml(block) ?? {});
}

/**
 * YAML values arrive already typed, so `order: 3` is a number and `title: 3` is a string. The
 * keys are read with a fallback rather than validated, because a mirror is not the place to fail
 * a build — the page itself would already have failed on the same missing key.
 */
function frontmatterOf(data) {
  return {
    section: typeof data?.section === "string" ? data.section : "",
    order: Number.isFinite(data?.order) ? data.order : 0,
    title: typeof data?.title === "string" ? data.title : "",
    summary: typeof data?.summary === "string" ? data.summary : "",
  };
}

/**
 * The pages grouped for the sidebar, and `Elsewhere` for anything in a section the menu does not
 * declare. Listing those rather than dropping them turns a naming mistake into a visible page
 * instead of a missing one.
 *
 * Takes `collections.all` as piped in, not the collections object: the template writes
 * `collections.all | sections`, so the argument is the array.
 */
function sectionGroups(entries) {
  // The filter is handed `collections.all` itself, because that is what the template pipes in —
  // `collections.all | sections` passes the array, not the object that holds it.
  const pages = (entries ?? []).filter((entry) => entry.url !== "/" && entry.data?.section);

  // The order the pages are in is the one they declare, not the order the collection happens to
  // hold them in: a menu that lists its chapters in the order the files were written is a menu
  // that reorders itself when a page is edited.
  const byOrder = (a, b) => a.data.order - b.data.order || a.data.title.localeCompare(b.data.title);

  const known = SECTIONS.map(([title, note]) => ({
    title,
    note,
    pages: pages.filter((entry) => entry.data.section === title).sort(byOrder),
  })).filter((group) => group.pages.length > 0);

  const elsewhere = pages
    .filter((entry) => !SECTIONS.some(([title]) => title === entry.data.section))
    .sort(byOrder);

  return elsewhere.length
    ? [
        ...known,
        {
          title: "Elsewhere",
          note: "Pages in a section the menu does not declare.",
          pages: elsewhere,
        },
      ]
    : known;
}

/**
 * The page before and the page after, across the pages that carry a section.
 *
 * Computed here rather than in the template because Nunjucks has no `where` filter, and a
 * collection walked by hand in the layout is a loop that has to be right on every page. Out of
 * range gives undefined rather than an error, and an undefined neighbour is simply not linked,
 * which is what should happen at either end anyway.
 */
function pager(entries, url) {
  const ordered = (entries ?? []).filter((entry) => entry.data?.section);
  const index = ordered.findIndex((entry) => entry.url === url);

  return {
    before: index > 0 ? ordered[index - 1] : undefined,
    after: index >= 0 && index + 1 < ordered.length ? ordered[index + 1] : undefined,
  };
}

function escape(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}