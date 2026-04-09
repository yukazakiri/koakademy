import { createInertiaApp } from "@inertiajs/react";
import createServer from "@inertiajs/react/server";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import ReactDOMServer from "react-dom/server";

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => {
            const props = page.props as { branding?: { appName: string } };
            const appName = props.branding?.appName || "KoAkademy";
            return title ? `${title} - ${appName}` : appName;
        },
        resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob("./pages/**/*.tsx")),
        setup: ({ App, props }) => <App {...props} />,
    }),
);
