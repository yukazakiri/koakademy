#!/usr/bin/env node

import { access, readdir, readFile } from "node:fs/promises";
import path from "node:path";
import process from "node:process";

const root = process.cwd();
const rootDocuments = [
    "README.md",
    "CONTRIBUTING.md",
    "SECURITY.md",
    "CHANGELOG.md",
];

async function contentFiles(directory) {
    const entries = await readdir(directory, { withFileTypes: true });
    const files = [];

    for (const entry of entries) {
        const entryPath = path.join(directory, entry.name);

        if (entry.isDirectory()) {
            files.push(...await contentFiles(entryPath));
        } else if (/\.mdx?$/.test(entry.name)) {
            files.push(entryPath);
        }
    }

    return files;
}

const files = [
    ...rootDocuments.map((file) => path.join(root, file)),
    ...await contentFiles(path.join(root, "docs/src/content/docs")),
];
const errors = [];

for (const file of files) {
    let content;

    try {
        content = await readFile(file, "utf8");
    } catch {
        errors.push(`${path.relative(root, file)} is missing`);
        continue;
    }

    for (const match of content.matchAll(/(?<!!)\[[^\]]*\]\(([^)]+)\)/g)) {
        const rawTarget = match[1].trim().replace(/^<|>$/g, "");

        if (/^(?:https?:|mailto:|#|\/)/.test(rawTarget)) {
            continue;
        }

        const localTarget = rawTarget.split("#", 1)[0].split("?", 1)[0];

        if (!/\.mdx?$/.test(localTarget)) {
            continue;
        }

        try {
            await access(path.resolve(path.dirname(file), localTarget));
        } catch {
            errors.push(`${path.relative(root, file)} links to missing ${rawTarget}`);
        }
    }
}

const apiDirectory = path.join(root, "docs/src/content/docs/api");
const apiContent = (await contentFiles(apiDirectory))
    .map(async (file) => readFile(file, "utf8"))
    .map((promise) => promise);
const combinedApi = (await Promise.all(apiContent)).join("\n");

for (const endpoint of ["GET /api/v1/public/settings", "GET /api/settings", "POST /api/students/verify"]) {
    if (!combinedApi.includes(endpoint)) {
        errors.push(`API documentation is missing verified endpoint: ${endpoint}`);
    }
}

for (const fictional of ["GET /api/students ", "POST /api/students ", "PUT /api/students/", "DELETE /api/students/"]) {
    if (combinedApi.includes(fictional)) {
        errors.push(`API documentation contains unsupported student CRUD: ${fictional.trim()}`);
    }
}

if (errors.length > 0) {
    console.error(`Documentation validation failed:\n${errors.map((error) => `- ${error}`).join("\n")}`);
    process.exitCode = 1;
} else {
    console.log(`Documentation validation passed for ${files.length} files.`);
}
