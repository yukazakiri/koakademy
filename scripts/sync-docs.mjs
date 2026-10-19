#!/usr/bin/env node

import { mkdir, readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import process from "node:process";

const root = process.cwd();
const checkOnly = process.argv.includes("--check");

const documents = [
    { source: "CONTRIBUTING.md", target: "docs/src/content/docs/start-here/contributing.mdx", title: "Contributing", description: "Contribution workflow, validation, and documentation ownership." },
];

const targetBySource = new Map(documents.map((document) => [document.source, document.target]));
// Retained link mappings for foundational docs that are now native MDX pages.
for (const [source, target] of [
    ["GETTING_STARTED.md", "docs/src/content/docs/self-hosting/installation.mdx"],
    ["DEPLOYMENT.md", "docs/src/content/docs/self-hosting/deployment.mdx"],
    ["CONFIGURATION.md", "docs/src/content/docs/self-hosting/configuration.mdx"],
    ["TROUBLESHOOTING.md", "docs/src/content/docs/self-hosting/troubleshooting.mdx"],
    ["FAQ.md", "docs/src/content/docs/self-hosting/faq.mdx"],
    ["DEVELOPMENT.md", "docs/src/content/docs/start-here/development.mdx"],
    ["ARCHITECTURE.md", "docs/src/content/docs/start-here/architecture.mdx"],
]) {
    targetBySource.set(source, target);
}
const repositoryDocuments = new Set([
    "README.md",
    "SECURITY.md",
    "CHANGELOG.md",
    "LICENSE.md",
]);

function documentationLink(fromTarget, sourceTarget, anchor = "") {
    let target = targetBySource.get(sourceTarget);

    if (!target && /^docs\/src\/content\/docs\/.+\.mdx$/.test(sourceTarget)) {
        target = sourceTarget;
    }

    if (!target) {
        if (repositoryDocuments.has(sourceTarget)) {
            return `https://github.com/yukazakiri/koakademy/blob/master/${sourceTarget}${anchor}`;
        }

        return `${sourceTarget}${anchor}`;
    }

    const fromDirectory = path.posix.dirname(fromTarget.replace(/^docs\/src\/content\/docs\//, ""));
    const targetRoute = target
        .replace(/^docs\/src\/content\/docs\//, "")
        .replace(/\.mdx$/, "");
    let relative = path.posix.relative(fromDirectory, targetRoute);

    if (!relative.startsWith(".")) {
        relative = `./${relative}`;
    }

    return `${relative}/${anchor}`;
}

function render(document, source) {
    const body = source
        .replace(/^# .+\r?\n+/, "")
        .replace(/\]\(([A-Z][A-Z_]+\.md)(#[^)]+)?\)/g, (_match, target, anchor = "") => `](${documentationLink(document.target, target, anchor)})`)
        .replace(/\]\((docs\/src\/content\/docs\/[^)]+?\.mdx)(#[^)]+)?\)/g, (_match, target, anchor = "") => `](${documentationLink(document.target, target, anchor)})`)
        .trim();

    return `---\ntitle: ${document.title}\ndescription: ${document.description}\n---\n\n{/* GENERATED FILE. Source: /${document.source}. Run: npm run docs:sync */}\n\n${body}\n`;
}

const stale = [];

for (const document of documents) {
    const source = await readFile(path.join(root, document.source), "utf8");
    const expected = render(document, source);
    let actual = "";

    try {
        actual = await readFile(path.join(root, document.target), "utf8");
    } catch {
        // A missing mirror is stale and will be created outside check mode.
    }

    if (actual === expected) {
        continue;
    }

    stale.push(document.target);

    if (!checkOnly) {
        await mkdir(path.dirname(path.join(root, document.target)), { recursive: true });
        await writeFile(path.join(root, document.target), expected, "utf8");
    }
}

if (stale.length > 0 && checkOnly) {
    console.error(`Generated documentation is stale:\n${stale.map((file) => `- ${file}`).join("\n")}`);
    process.exitCode = 1;
} else if (stale.length > 0) {
    console.log(`Updated ${stale.length} generated documentation mirror(s).`);
} else {
    console.log("Generated documentation mirrors are current.");
}
