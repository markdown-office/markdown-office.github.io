import { createRequire } from "node:module";

// js-yaml has moved between ESM and CommonMark across majors, so going through
// `createRequire` loads whichever this project has installed rather than betting on an
// import shape.
const yaml = createRequire(import.meta.url)("js-yaml");

// js-yaml renamed `parse` to `load` in v5 and moved between import systems at the same
// time, so both names are asked for rather than betting on one.
export const parseYaml = yaml.parse ?? yaml.load;
