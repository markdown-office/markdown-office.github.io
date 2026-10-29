import { createHash } from "node:crypto";

/**
 * The key a built example is filed under, and it has to be the same on both sides of the build.
 *
 * `tools/render-examples.php` computes it as the first sixteen hex digits of
 * `hash('sha256', $block)`, where `$block` is the whole trimmed fenced block — opening fence, body
 * and closing fence. Hashing the body alone would need the body recovered from the text Eleventy
 * hands over, which is the whole block; a fence is the one thing the shortcode cannot strip
 * without re-implementing the parser that produced it.
 */
export function hashOf(block) {
  return createHash("sha256").update(block, "utf8").digest("hex").slice(0, 16);
}

/**
 * A page's body with its frontmatter off and its examples in the form a reader can paste.
 *
 * The `{% example %}` pair is the build's own machinery, and this file is not the build — so an
 * example becomes the fence it was written as. That is the whole point of including them: a
 * reader gets the document source, ready to use, rather than a reference to a picture of one.
 */
export function asMarkdown(source) {
  const [, , body = ""] =
    source.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n?([\s\S]*)$/) ?? [];

  return body
    .replace(/\{%\s*example[^%]*%\}\n?/g, "")
    .replace(/\n?\{%\s*endexample\s*%\}/g, "")
    .trim();
}

/**
 * A page's body with its frontmatter off and each example expanded into a named entry.
 *
 * The same text `asMarkdown()` gives, with one difference: an example is not dropped but
 * replaced by a heading that names it and the three paths an agent needs to use it — the
 * Markdown it was written as, the document it was built into, and the pictures of it.
 *
 * The paths are the ones inside the bundle rather than the ones on this site, because the
 * bundle is what a reader has.
 *
 * @param source The page's source.
 * @param examples What was built for each example, keyed by the hash of its fence.
 */
export function expandExamples(source, examples) {
  const [, , body = ""] =
    source.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n?([\s\S]*)$/) ?? [];

  return body
    .replace(
      /\{%\s*example([^%]*?)%\}\n?([\s\S]*?)\n?\{%\s*endexample\s*%\}/g,
      (whole, title, fence) => {
        const name = title.trim().replace(/^["']|["']$/g, "");
        const hash = hashOf(fence.trim());
        const built = examples[hash];

        // An example with nothing built for it is left as its fence: the block says what
        // it was, and a reference to a file that is not there is worse than the source.
        return built ? expansion(name, built, fence.trim(), hash) : fence.trim();
      },
    )
    .trim();
}

/**
 * One example, as a named entry.
 *
 * @param {{document: string|null, previews: list<string>}} built What was built.
 */
function expansion(name, built, fence, hash) {
  // The heading is the tag's title, and the directory is named from what was built.
  // They are usually the same, because the title travels from the tag to the manifest;
  // they differ when the tag carries no title and the fence does, and the directory
  // follows the manifest because that is the one thing both sides can read.
  const titled = name !== "" ? name : (built.name ?? "");

  const directory = `examples/site/${slug(built.name ?? titled)}-${hash}`;

  const lines = [`#### Example: ${titled}`, ""];

  lines.push(`Source:  \`${directory}/example.md\``);

  if (built.document) {
    lines.push(`Built:   \`${directory}/example.docx\``);
  }

  if (built.previews.length > 0) {
    const images = built.previews.map((preview) => `\`${directory}/${basename(preview)}\``);

    lines.push(`Images:  ${images.join(", ")}`);
  } else {
    lines.push(
      "Images:  none — the machine that built this page had no WordCraft or DeckCraft to draw it with.",
    );
  }

  lines.push("", fence, "");

  return lines.join("\n");
}

/**
 * A name a reader can recognise, from a title.
 */
export function slug(name) {
  // The run of characters that are not letters or digits has already collapsed into one
  // dash, so there is one dash at each end at most and one is enough to take it off.
  return name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-/, "")
    .replace(/-$/, "")
    .slice(0, 48);
}

/**
 * The last part of a path.
 */
function basename(path) {
  return path.split("/").pop() ?? path;
}
