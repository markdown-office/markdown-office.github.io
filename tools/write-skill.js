import { cp, mkdir, readFile, readdir, rm, writeFile } from "node:fs/promises";
import { existsSync, readFileSync } from "node:fs";
import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import { fileURLToPath } from "node:url";
import { dirname, join, relative, resolve } from "node:path";

import { expandExamples, slug } from "./markdown.js";

const here = dirname(fileURLToPath(import.meta.url));

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
 * Writes the skill: this site's documentation as a directory an agent can read.
 *
 * The bundle is a copy, and it is made by the same build that makes the site — so it
 * cannot say anything the site does not say, and it cannot be out of date in a way the site
 * is not.
 *
 * Four things are in it: the pages of the site, the examples the site built, the examples
 * the library ships, and the tools that render a document so that it can be looked at.
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

  for (const script of await readdir(join(here, "..", "skill", "mdword", "scripts"))) {
    await cp(
      join(here, "..", "skill", "mdword", "scripts", script),
      join(bundle, "scripts", script),
    );
  }

  for (const tool of LIBRARY_TOOLS) {
    const from = join(LIBRARY, "tools", tool);

    if (! existsSync(from)) {
      throw new Error(
        `The tool "${tool}" is not there. The library is checked out beside this one, and it is `
          + "where the bundle's scripts come from.",
      );
    }

    await cp(from, join(bundle, "scripts", tool));
  }

  // The pages, one file each, with the frontmatter off.
  await mkdir(join(bundle, "references"), { recursive: true });

  for (const reference of manifest.references ?? []) {
    const page = join(here, "..", "content", reference.page);

    if (! existsSync(page)) {
      throw new Error(`The page "${reference.page}" is not there, and the bundle says it carries it.`);
    }

    const source = readFileSync(page, "utf8");

    await writeFile(
      join(bundle, "references", reference.file),
      `${expandExamples(source, examples)}\n`,
    );
  }

  // The examples the site built, one directory each.
  await mkdir(join(bundle, "examples", "site"), { recursive: true });

  for (const [hash, built] of Object.entries(examples)) {
    const from = join(here, "..", "_examples", built.language ?? "word", hash);

    if (! existsSync(from)) {
      continue;
    }

    await cp(from, join(bundle, "examples", "site", directoryFor(built.name ?? hash, hash)), {
      recursive: true,
    });
  }

  // The examples the library ships: the catalogue of what it can do.
  const libraryExamples = join(LIBRARY, "examples");

  if (! existsSync(libraryExamples)) {
    throw new Error(
      "The library's examples are not there. The library is checked out beside this one, and they "
        + "are what the bundle's own catalogue is.",
    );
  }

  await cp(libraryExamples, join(bundle, "examples", "library"), {
    recursive: true,
    filter: (from) => !from.includes("/out/") && !from.endsWith(".DS_Store"),
  });

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
            files: files.map((file) => relative(bundle, file).split("\\").join("/")),
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
  const found = [];

  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name);

    if (entry.isDirectory()) {
      found.push(...(await listFiles(path)));
      continue;
    }

    found.push(path);
  }

  return found.sort();
}

/**
 * The version of the bundle: the day it was made, and what it held.
 */
async function versionOf(bundle) {
  const digest = createHash("sha256");
  const files = await listFiles(bundle);

  for (const file of files) {
    digest.update(relative(bundle, file));
    digest.update(await readFile(file));
  }

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
    execFileSync("zip", ["-r", "-q", resolve(target), "."], { cwd: bundle });
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
