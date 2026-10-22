#!/usr/bin/env node
/**
 * Verify that the Inertia SSR bundle can be loaded with the production
 * dependency tree that ships in the runtime image.
 *
 * Vite splits the SSR build into an entry chunk (bootstrap/ssr/ssr.js) plus one
 * chunk per lazily loaded page. The entry chunk only names a subset of the
 * packages the server touches; the page chunks pull in the rest (pdfjs-dist,
 * recharts, shiki, d3, ...). Resolving only the entry chunk would let a
 * missing package reach production and turn into a 500 on the first visit to
 * the affected page, so every emitted file is scanned here.
 *
 * The check fails the image build when a bare import cannot be resolved or
 * cannot be loaded, which keeps `npm prune --omit=dev --omit=optional` honest:
 * optional dependencies (native canvas bindings, platform specific binaries)
 * may only be dropped when nothing in the SSR graph actually needs them.
 */
import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, resolve } from "node:path";
import { builtinModules } from "node:module";

const bundleDirectory = resolve(process.argv[2] ?? "bootstrap/ssr");

/**
 * Matches a package name, optionally scoped and optionally followed by a
 * subpath. Bundled code contains plenty of quoted text that is not a module
 * specifier, so candidates are filtered through this before they are used.
 */
const PACKAGE_NAME = /^(?:@[a-z0-9-~][a-z0-9-._~]*\/)?[a-z0-9-~][a-z0-9-._~]*(?:\/[^\s]*)?$/;

/**
 * Collect every JavaScript file that belongs to the SSR bundle.
 */
function collectBundleFiles(directory) {
    const files = [];

    for (const entry of readdirSync(directory, { withFileTypes: true })) {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            files.push(...collectBundleFiles(path));
            continue;
        }

        if (path.endsWith(".js")) {
            files.push(path);
        }
    }

    return files;
}

/**
 * Extract the bare module specifiers from a bundle file.
 *
 * Matches static imports/exports, side-effect imports, `require()` calls and
 * dynamic `import()` calls. Relative, absolute and `node:` specifiers are
 * ignored because they are resolved by Node itself.
 */
function extractBareSpecifiers(source) {
    const patterns = [
        /\bfrom\s*["']([^"']+)["']/g,
        /\bimport\s*["']([^"']+)["']/g,
        /\bimport\s*\(\s*["']([^"']+)["']\s*\)/g,
        /\brequire\s*\(\s*["']([^"']+)["']\s*\)/g,
    ];

    const specifiers = new Set();

    for (const pattern of patterns) {
        for (const match of source.matchAll(pattern)) {
            const specifier = match[1];

            if (specifier.startsWith(".") || specifier.startsWith("/")) {
                continue;
            }

            if (specifier.startsWith("node:")) {
                continue;
            }

            if (builtinModules.includes(specifier)) {
                continue;
            }

            if (!PACKAGE_NAME.test(specifier)) {
                continue;
            }

            specifiers.add(specifier);
        }
    }

    return specifiers;
}

let files;

try {
    files = collectBundleFiles(bundleDirectory);
} catch {
    console.error(`SSR bundle not found at ${bundleDirectory}.`);
    process.exit(1);
}

if (!files.includes(join(bundleDirectory, "ssr.js"))) {
    console.error(`SSR bundle entry not found in ${bundleDirectory}.`);
    process.exit(1);
}

const specifiers = new Set();

for (const file of files) {
    for (const specifier of extractBareSpecifiers(readFileSync(file, "utf8"))) {
        specifiers.add(specifier);
    }
}

if (specifiers.size === 0) {
    console.error(`No bare imports found in the SSR bundle under ${bundleDirectory}.`);
    process.exit(1);
}

const failures = [];

for (const specifier of [...specifiers].sort()) {
    try {
        await import(specifier);
    } catch (error) {
        failures.push(`${specifier}: ${error.code ?? error.message}`);
    }
}

if (failures.length > 0) {
    console.error("SSR runtime dependencies could not be loaded from the production tree:");
    for (const failure of failures) {
        console.error(`  - ${failure}`);
    }
    process.exit(1);
}

console.log(`SSR runtime dependencies OK: ${specifiers.size} specifiers across ${files.length} bundle files.`);
