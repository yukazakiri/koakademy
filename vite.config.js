import tailwindcss from "@tailwindcss/vite";

import { wayfinder } from "@laravel/vite-plugin-wayfinder";
import react from "@vitejs/plugin-react";
import laravel from "laravel-vite-plugin";
import { execSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { defineConfig } from "vite";

const CONTROL_CHARS_RE = /[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g;

function resolvePhpBinary() {
    if (process.env.PHP_BINARY && fs.existsSync(process.env.PHP_BINARY)) {
        return process.env.PHP_BINARY;
    }
    try {
        const detected = execSync("command -v php || which php", { stdio: ["ignore", "pipe", "ignore"] })
            .toString()
            .trim();
        if (detected && fs.existsSync(detected)) {
            return detected;
        }
    } catch {
        // Fall back to well-known PHP binary locations
    }

    const wellKnownPaths = [
        path.join(os.homedir(), ".config/herd-lite/bin/php"),
        path.join(os.homedir(), "Library/Application Support/Herd/bin/php"),
        path.join(os.homedir(), ".config/herd/bin/php"),
        "/usr/local/bin/php",
        "/usr/bin/php",
    ];

    for (const binaryPath of wellKnownPaths) {
        if (fs.existsSync(binaryPath)) {
            return binaryPath;
        }
    }

    return null;
}

const phpBinary = resolvePhpBinary();
const shouldGenerateWayfinder = process.env.WAYFINDER_GENERATE !== "false" && Boolean(phpBinary);

// Rolldown plugin so optimizeDeps prebundling (Vite 8+) doesn't choke on
// the stray control characters shipped inside @tabler/icons-react ESM files.
const sanitizeTablerIconsRolldown = {
    name: "sanitize-tabler-icons",
    async load(id) {
        if (!id.includes("/node_modules/@tabler/icons-react/dist/esm/")) {
            return null;
        }
        const filePath = id.split("?")[0];
        try {
            const source = await fs.promises.readFile(filePath, "utf8");
            return source.replace(CONTROL_CHARS_RE, "");
        } catch {
            return null;
        }
    },
};

export default defineConfig({
    plugins: [
        {
            name: "sanitize-tabler-icons",
            enforce: "pre",
            transform(code, id) {
                if (!id.includes("/node_modules/@tabler/icons-react/dist/esm/")) {
                    return null;
                }

                const sanitizedCode = code.replace(CONTROL_CHARS_RE, "");

                if (sanitizedCode === code) {
                    return null;
                }

                return {
                    code: sanitizedCode,
                    map: null,
                };
            },
        },
        ...(shouldGenerateWayfinder
            ? [
                  wayfinder({
                      command: `"${phpBinary}" -d memory_limit=512M artisan wayfinder:generate`,
                  }),
              ]
            : []),
        tailwindcss({
            config: {
                content: [
                    "./app/Filament/**/*.php",
                    "./Modules/**/app/Filament/**/*.php",
                    "./Modules/**/resources/views/**/*.blade.php",
                    "./Modules/**/resources/assets/js/**/*.tsx",
                    "./vendor/*/*/resources/assets/js/**/*.tsx",
                    "./resources/views/**/*.blade.php",
                    "./vendor/filament/**/*.blade.php",
                    "./resources/js/**/*.tsx",
                    "./vendor/andreia/filament-nord-theme/resources/views/**/*.blade.php",
                ],
            },
        }),
        laravel({
            input: ["resources/css/app.css", "resources/js/App.tsx", "resources/css/filament/admin/theme.css"],
            refresh: true,
            ssr: "resources/js/ssr.tsx",
        }),
        react(),
    ],
    resolve: {
        preserveSymlinks: true,
        dedupe: ["react", "react-dom"],
        alias: [
            { find: "@/components", replacement: path.resolve(import.meta.dirname, "resources/js/components") },
            { find: "@/context", replacement: path.resolve(import.meta.dirname, "resources/js/context") },
            { find: "@/hooks", replacement: path.resolve(import.meta.dirname, "resources/js/hooks") },
            { find: "@/lib", replacement: path.resolve(import.meta.dirname, "resources/js/lib") },
            { find: "@/types", replacement: path.resolve(import.meta.dirname, "resources/js/types") },
            { find: "@/wrappers", replacement: path.resolve(import.meta.dirname, "resources/js/wrappers") },
            { find: "@", replacement: path.resolve(import.meta.dirname, "resources/js") },
        ],
    },
    server: {
        cors: true,
    },
    optimizeDeps: {
        include: ["@tabler/icons-react"],
        rolldownOptions: {
            plugins: [sanitizeTablerIconsRolldown],
        },
    },
    build: {
        cssCodeSplit: true,
        chunkSizeWarningLimit: 1000,
    },
    ssr: {
        noExternal: [/@visx\/.*/],
    },
});
