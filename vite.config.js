import tailwindcss from "@tailwindcss/vite";
import legacy from "@vitejs/plugin-legacy";
import react from "@vitejs/plugin-react";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [
        {
            name: "sanitize-tabler-icons",
            enforce: "pre",
            transform(code, id) {
                if (!id.includes("/node_modules/@tabler/icons-react/dist/esm/")) {
                    return null;
                }

                const sanitizedCode = code.replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, "");

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
        exclude: ["@tabler/icons-react"],
    },
    build: {
        cssCodeSplit: true,
        chunkSizeWarningLimit: 1000,
    },
});
