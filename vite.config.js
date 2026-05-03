import tailwindcss from "@tailwindcss/vite";
import { wayfinder } from "@laravel/vite-plugin-wayfinder";
import legacy from "@vitejs/plugin-legacy";
import react from "@vitejs/plugin-react";
import fs from "node:fs";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

const CONTROL_CHARS_RE = /[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g;

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
        tailwindcss({
            config: {
                content: [
                    "./app/Filament/**/*.php",
                    "./Modules/**/app/Filament/**/*.php",
                    "./Modules/**/resources/views/**/*.blade.php",
                    "./Modules/**/resources/assets/js/**/*.tsx",
                    "./resources/views/**/*.blade.php",
                    "./vendor/filament/**/*.blade.php",
                    "./resources/js/**/*.tsx",
                    "./vendor/andreia/filament-nord-theme/resources/views/**/*.blade.php",
                ],
            },
        }),
        wayfinder(),
        laravel({
            input: ["resources/css/app.css", "resources/js/App.tsx", "resources/css/filament/admin/theme.css"],
            refresh: true,
            ssr: "resources/js/ssr.tsx",
        }),
        react(),
        legacy({
            targets: ["defaults"],
            modernPolyfills: true,
            renderModernChunks: true,
        }),
    ],
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
});
