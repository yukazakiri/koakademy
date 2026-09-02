import type { ResolvedComponent } from "@inertiajs/react";
import { createInertiaApp } from "@inertiajs/react";
import createServer from "@inertiajs/react/server";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import ReactDOMServer from "react-dom/server";

import { resolveBranding, type Branding } from "@/lib/branding";
import "./bones/registry";

type InertiaPageModule = {
    default: ResolvedComponent;
};

const appPages = import.meta.glob<InertiaPageModule>("./pages/**/*.tsx");
const modulePages = {
    ...import.meta.glob<InertiaPageModule>("../../Modules/**/resources/assets/js/Pages/**/*.tsx"),
    ...import.meta.glob<InertiaPageModule>("../../vendor/*/*/resources/assets/js/Pages/**/*.tsx"),
};

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

            const module = modulePagePath ? await modulePages[modulePagePath]() : await resolvePageComponent(`./pages/${name}.tsx`, appPages);

            return module.default;
        },
        setup: ({ App, props }) => <App {...props} />,
    }),
);
