import { createInertiaApp } from "@inertiajs/react";
import createServer from "@inertiajs/react/server";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import ReactDOMServer from "react-dom/server";

import { resolveBranding, type Branding } from "@/lib/branding";

const appPages = import.meta.glob("./pages/**/*.tsx");
const modulePages = import.meta.glob("../../Modules/**/resources/assets/js/Pages/**/*.tsx");

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => {
            const props = page.props as { branding?: Partial<Branding> | null };
            const appName = resolveBranding(props.branding).appName;
            return title ? `${title} - ${appName}` : appName;
        },
        resolve: async (name) => {
            const modulePagePath = Object.keys(modulePages).find((path) => path.endsWith(`/resources/assets/js/Pages/${name}.tsx`));

            return modulePagePath
                ? modulePages[modulePagePath]()
                : resolvePageComponent(`./pages/${name}.tsx`, appPages);
        },
        setup: ({ App, props }) => <App {...props} />,
    }),
);
