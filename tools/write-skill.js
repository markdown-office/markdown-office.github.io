import { cp, mkdir, readFile, readdir, rm, writeFile } from "node:fs/promises";
import { existsSync, readFileSync } from "node:fs";
import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import { fileURLToPath } from "node:url";
import { basename, dirname, join, relative, resolve } from "node:path";

import { expandExamples, slug } from "./markdown.js";

const here = dirname(fileURLToPath(import.meta.url));

/**
 * The address the site is served from, without a trailing slash.
 *
 * The same address the site itself is built with, so a link in the bundle that points at
 * the site points at the site that was built.
 */
const CANONICAL = withoutTrailingSlashes(
  process.env.MARKDOWN_OFFICE_CANONICAL ?? "https://markdown-office.github.io",
);

/**
 * An address without the slashes at its end.
 *
 * The slashes are counted off rather than matched: a pattern that strips them gives one
 * of them back at a time, and it gives one back for every place it tries, so a long run
 * of them costs more the longer it is. The address is the environment's to give, and the
 * environment is not a person who would not do that.
 */
function withoutTrailingSlashes(address) {
  let end = address.length;

  while (end > 0 && address[end - 1] === "/") {
    end -= 1;
  }

  return address.slice(0, end);
}

/**
 * The address a page is at, from its frontmatter.
 *
 * Null where the page states none, which is a page the bundle cannot place: a link to it
 * cannot be rewritten into a link to a file.
 */
function permalinkOf(source) {
  const found = /^permalink:\s*(\S+)\s*$/m.exec(source);

  return found === null ? null : found[1];
}

/**
 * The tools the library ships, which the bundle carries unchanged.
 *
 * They are copied rather than rewritten, and the checker says so: a copy that has drifted
 * is a copy that is wrong, and a script the bundle carries is a script somebody has to
 * keep working.
 */
const LIBRARY_TOOLS = [
  "render-document.php",
  "render-with-word.applescript",
  "render-with-word.ps1",
  "package-as-document.sh",
  "pdf-is-complete.sh",
];

/**
 * Where the library is checked out beside this one.
 *
 * The site's build resolves the libraries through path repositories, so the sibling is
 * there or the build never started. It is named here rather than read out of the manifest
 * because the manifest says which *version* of the library to use and this says where it
 * is on this machine.
 */
const LIBRARY = join(here, "..", "..", "markdown-word");

/**
 * The galleries the bundle carries, and the libraries they come from.
 *
 * Read rather than written down here, because the checker reads it too: two copies of one
 * list are two answers to one question, and the two drift.
 */
const GALLERIES = JSON.parse(
  readFileSync(join(here, "..", "skill", "mdword", "galleries.json"), "utf8"),
);

/**
 * Where the archive is made by.
 *
 * The absolute path rather than the name, because a command looked up on the path is a
 * command the path decides, and the path is not the build's to decide.
 */
const ZIP = "/usr/bin/zip";

/**
 * Writes the skill: this site's documentation as a directory an agent can read.
 *
 * The bundle is a copy, and it is made by the same build that makes the site — so it
 * cannot say anything the site does not say, and it cannot be out of date in a way the site
 * is not.
 *
 * Four things are in it: the pages of the site, the examples the site built, the galleries
 * the two libraries ship, and the tools that render a document so that it can be looked at.
 */
export async function writeSkill({ dir }) {
  const manifest = JSON.parse(
    readFileSync(join(here, "..", "skill", "mdword", "pages.json"), "utf8"),
  );
  const examples = readManifest();

  const bundle = join(dir.output, "skills", "mdword");

  await rm(bundle, { recursive: true, force: true });
  await mkdir(bundle, { recursive: true });

  // The skill itself, and the scripts it points at.
  await cp(join(here, "..", "skill", "mdword", "SKILL.md"), join(bundle, "SKILL.md"));
  await mkdir(join(bundle, "scripts"), { recursive: true });

  await Promise.all(
    (await readdir(join(here, "..", "skill", "mdword", "scripts"))).map((script) =>
      cp(
        join(here, "..", "skill", "mdword", "scripts", script),
        join(bundle, "scripts", script),
      ),
    ),
  );

  await Promise.all(
    LIBRARY_TOOLS.map(async (tool) => {
      const from = join(LIBRARY, "tools", tool);

      if (! existsSync(from)) {
        throw new Error(
          `The tool "${tool}" is not there. The library is checked out beside this one, and it is `
            + "where the bundle's scripts come from.",
        );
      }

      await cp(from, join(bundle, "scripts", tool));
    }),
  );

  // The pages, one file each, with the frontmatter off.
  await mkdir(join(bundle, "references"), { recursive: true });

  const carried = manifest.references ?? [];

  // The address a page is at on the site, and the file the bundle carries it in.
  //
  // The bundle is distributed as an archive with no website beside it, so a page's link to
  // another page of the site resolves to nothing. A link to a page the bundle carries
  // becomes a link to the file beside it; a link to a page it does not carry becomes the
  // address of the page on the site, which is where the page is.
  const at = new Map();

  for (const reference of carried) {
    const page = join(here, "..", "content", reference.page);

    if (!existsSync(page)) {
      throw new Error(`The page "${reference.page}" is not there, and the bundle says it carries it.`);
    }

    const permalink = permalinkOf(readFileSync(page, "utf8"));

    if (permalink !== null) {
      at.set(permalink, reference.file);
    }
  }

  await Promise.all(
    carried.map(async (reference) => {
      const page = join(here, "..", "content", reference.page);

      const source = readFileSync(page, "utf8");

      await writeFile(
        join(bundle, "references", reference.file),
        `${expandExamples(source, examples)}\n`.replace(
          /\]\((\/[^)\s#]*)(#[^)\s]*)?\)/g,
          (whole, path, fragment) => {
            const target = at.get(path);

            if (target !== undefined) {
              return `](${target}${fragment ?? ""})`;
            }

            return `](${CANONICAL}${path})`;
          },
        ),
      );
    }),
  );

  // The examples the site built, one directory each.
  await mkdir(join(bundle, "examples", "site"), { recursive: true });

  await Promise.all(
    Object.entries(examples).map(async ([hash, built]) => {
      const from = join(here, "..", "_examples", built.language ?? "word", hash);

      if (! existsSync(from)) {
        return;
      }

      await cp(from, join(bundle, "examples", "site", directoryFor(built.name ?? hash, hash)), {
        recursive: true,
      });
    }),
  );

  // The galleries the libraries ship: the catalogue of what each half can do.
  //
  // The sources are copied and not the built output, and the manifest says which parts of
  // a gallery are output: a gallery that carried the built decks would be a second copy of
  // something the library builds, and one that had fallen behind would be a gallery of
  // decks the library no longer draws.
  await Promise.all(
    Object.entries(GALLERIES.galleries).map(async ([half, gallery]) => {
      const from = join(here, "..", "..", gallery.source);

      if (! existsSync(from)) {
        throw new Error(
          `The ${half} half's examples are not there. The library is checked out beside this one, `
            + "and they are what the bundle's own catalogue is.",
        );
      }

      await cp(from, join(bundle, gallery.path), {
        recursive: true,
        filter: (path) => ! GALLERIES.exclude.includes(basename(path)),
      });
    }),
  );

  const version = await versionOf(bundle);

  await writeFile(join(bundle, "VERSION"), `${version}\n`);

  // The catalogue OpenCode reads over the network, and the archive everybody else unpacks.
  const files = await listFiles(bundle);

  await writeFile(
    join(dir.output, "skills", "index.json"),
    `${JSON.stringify(
      {
        skills: [
          {
            name: "mdword",
            version,
            files: files.map((file) => relative(bundle, file).replaceAll("\\", "/")),
          },
        ],
      },
      null,
      2,
    )}\n`,
  );

  await archive(bundle, join(dir.output, "skills", "mdword.zip"));
}

/**
 * What was built for each example, keyed by the hash of the fence it was written as.
 */
function readManifest() {
  try {
    return JSON.parse(readFileSync(join(here, "..", "_data", "examples.json"), "utf8"));
  } catch {
    return {};
  }
}

/**
 * The directory an example is carried in: a name a reader can recognise, and the hash that
 * makes it unique.
 */
function directoryFor(name, hash) {
  return `${slug(name)}-${hash}`;
}

/**
 * Every file in the bundle, in the order they are listed.
 *
 * @returns {Promise<string[]>}
 */
async function listFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true });

  const found = await Promise.all(
    entries.map(async (entry) => {
      const path = join(directory, entry.name);

      return entry.isDirectory() ? listFiles(path) : [path];
    }),
  );

  const every = found.flat();

  // The comparison is written out rather than left to the default, because the default is
  // a comparison by code unit and this is a comparison by name.
  return every.sort(byName);
}

/**
 * How two names stand to one another.
 */
function byName(left, right) {
  if (left < right) {
    return -1;
  }

  if (left > right) {
    return 1;
  }

  return 0;
}

/**
 * The version of the bundle: the day it was made, and what it held.
 */
async function versionOf(bundle) {
  const digest = createHash("sha256");
  const files = await listFiles(bundle);

  // The files are read together and hashed in order, because the digest is of the bundle
  // and not of the order the files happened to be read in.
  const contents = await Promise.all(files.map((file) => readFile(file)));

  files.forEach((file, index) => {
    digest.update(relative(bundle, file));
    digest.update(contents[index]);
  });

  const day = new Date().toISOString().slice(0, 10).replaceAll("-", ".");

  return `${day}-${digest.digest("hex").slice(0, 6)}`;
}

/**
 * The archive everybody but OpenCode unpacks.
 *
 * The `zip` command rather than a library, because there is no zip library in this project
 * and there is a `zip` on every machine the build runs on. A build that cannot make the
 * archive says so rather than publishing a bundle nobody can install.
 */
async function archive(bundle, target) {
  await rm(target, { force: true });

  try {
    // The target is absolute because the command runs inside the bundle: a relative one
    // would be written into the directory being zipped, and the archive would then be
    // trying to include itself.
    execFileSync(ZIP, ["-r", "-q", resolve(target), "."], { cwd: bundle });
  } catch (error) {
    throw new Error(
      `The archive could not be made: ${error.message}. The build needs the \`zip\` command, and `
        + "every machine the site is built on has it.",
    );
  }

  if (! existsSync(target)) {
    throw new Error("The archive was not written, so the bundle cannot be installed.");
  }
}
