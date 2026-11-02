import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import path from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";
import { createServer } from "vite";

// Isolated visual fixture: no school database, real logins, or production writes.
const root = fileURLToPath(new URL("../../", import.meta.url));
const server = await createServer({
    configFile: false,
    root,
    appType: "custom",
    plugins: [tailwindcss(), react()],
    resolve: {
        alias: [
            { find: "@/components/administrators/admin-layout", replacement: path.join(root, "tests/Fixtures/user-directory-layout.tsx") },
            { find: "@", replacement: path.join(root, "resources/js") },
        ],
        dedupe: ["react", "react-dom"],
    },
    server: { host: "127.0.0.1", port: Number(process.env.STUDENT_POLICY_PREVIEW_PORT ?? 52971), strictPort: true },
});
server.middlewares.use(async (request, response, next) => {
    if (request.url?.includes("generate-id")) {
        response.setHeader("Content-Type", "application/json");
        response.end(JSON.stringify({ id: "000042" }));
        return;
    }
    if (!request.url?.startsWith("/student-policy-")) return next();
    response.setHeader("Content-Type", "text/html");
    response.end(
        await server.transformIndexHtml(
            request.url,
            '<!doctype html><html><head></head><body><div id="root"></div><script type="module" src="/tests/Fixtures/student-configuration.tsx"></script></body></html>',
        ),
    );
});
await server.listen();
server.printUrls();
