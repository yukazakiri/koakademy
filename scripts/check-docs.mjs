#!/usr/bin/env node

import { access, readdir, readFile } from "node:fs/promises";
import path from "node:path";
import process from "node:process";

function validateYamlBlock(yamlText) {
    const documents = yamlText.split(/^---\s*$/m);
    for (const doc of documents) {
        const scopes = new Map();
        const lines = doc.split(/\r?\n/);
        for (let i = 0; i < lines.length; i++) {
            const rawLine = lines[i];
            if (/^\s*(?:#.*)?$/.test(rawLine)) continue;
            if (/^\t/.test(rawLine)) {
                throw new Error(`Tab indentation is not allowed in YAML (line ${i + 1})`);
            }
            const indentMatch = rawLine.match(/^( *)/);
            const indent = indentMatch ? indentMatch[1].length : 0;
            const trimmed = rawLine.trim();

            for (const level of [...scopes.keys()]) {
                if (level > indent) {
                    scopes.delete(level);
                }
            }

            if (trimmed.startsWith("- ")) {
                for (const level of [...scopes.keys()]) {
                    if (level >= indent) {
                        scopes.delete(level);
                    }
                }
                continue;
            }

            const keyMatch = trimmed.match(/^([A-Za-z0-9_.-]+|"[^"]+"|'[^']+')\s*:(?:\s+.*)?$/);
            if (keyMatch) {
                const key = keyMatch[1];
                let keysAtIndent = scopes.get(indent);
                if (!keysAtIndent) {
                    keysAtIndent = new Set();
                    scopes.set(indent, keysAtIndent);
                }
                if (keysAtIndent.has(key)) {
                    throw new Error(`Duplicate YAML key '${key}' at line ${i + 1}`);
                }
                keysAtIndent.add(key);
            }
        }
    }
}

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

const selfHostingDirectory = path.join(root, "docs/src/content/docs/self-hosting");
const selfHostingFiles = await contentFiles(selfHostingDirectory);
const guidesRequiringScheduler = new Set([
    "docker-compose.mdx",
    "dokploy.mdx",
    "coolify.mdx",
    "docker-swarm.mdx",
    "kubernetes.mdx",
    "openshift.mdx",
]);

for (const file of selfHostingFiles) {
    const relativeFile = path.relative(root, file);
    const baseName = path.basename(file);
    const content = await readFile(file, "utf8");

    // Check for unbalanced or nested fenced code blocks.
    let inFence = false;
    let fenceMarker = "";
    const lines = content.split(/\r?\n/);
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        const match = line.match(/^(`{3,}|~{3,})(.*)$/);
        if (!match) continue;
        const marker = match[1];
        const info = match[2].trim();
        if (!inFence) {
            inFence = true;
            fenceMarker = marker[0].repeat(marker.length);
        } else if (marker === fenceMarker && info === "") {
            inFence = false;
            fenceMarker = "";
        } else if (marker === fenceMarker && info !== "") {
            errors.push(`${relativeFile}:${i + 1} has a nested or unclosed code fence (${line})`);
        }
    }
    if (inFence) {
        errors.push(`${relativeFile} has an unclosed code fence at EOF`);
    }

    // Check for image repository typos or non-FrankenPHP production tags.
    if (content.includes("yukazaki/koakademy")) {
        errors.push(`${relativeFile} contains typo 'yukazaki/koakademy' (expected 'yukazakiri/koakademy')`);
    }
    if (/ghcr\.io\/yukazakiri\/koakademy:(?:latest|v\d+\.\d+\.\d+)(?!-franken)\b/.test(content)) {
        errors.push(`${relativeFile} references a non-FrankenPHP image tag without '-franken'`);
    }
    if (content.includes("/var/lib/postgresql/data")) {
        errors.push(`${relativeFile} mounts PostgreSQL 18 at '/var/lib/postgresql/data' instead of '/var/lib/postgresql'`);
    }

    const hasScheduler =
        /CONTAINER_MODE:\s*"?scheduler"?/.test(content) ||
        /name:\s*CONTAINER_MODE[\s\S]{0,80}?value:\s*"?scheduler"?/.test(content);
    if (guidesRequiringScheduler.has(baseName) && !hasScheduler) {
        errors.push(`${relativeFile} is missing a scheduler service/deployment (CONTAINER_MODE: scheduler)`);
    }

    // Parse every YAML fence to catch syntax errors and duplicate keys.
    for (const yamlMatch of content.matchAll(/```yaml\r?\n([\s\S]*?)```/g)) {
        try {
            validateYamlBlock(yamlMatch[1]);
        } catch (error) {
            errors.push(`${relativeFile} contains invalid YAML: ${error.message}`);
        }
    }
}

if (errors.length > 0) {
    console.error(`Documentation validation failed:\n${errors.map((error) => `- ${error}`).join("\n")}`);
    process.exitCode = 1;
} else {
    console.log(`Documentation validation passed for ${files.length} files.`);
}
