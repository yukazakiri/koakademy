import { createInertiaApp } from "@inertiajs/react";
import createServer from "@inertiajs/react/server";
import ReactDOMServer from "react-dom/server";

import { resolveBranding, type Branding } from "@/lib/branding";
import { resolveInertiaPage, type InertiaPageModule, type InertiaPageModules } from "@/lib/inertia-page-resolver";
import "./bones/registry";

const appPages: InertiaPageModules = import.meta.glob<InertiaPageModule>("./pages/**/*.tsx");
const modulePages: InertiaPageModules = {
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
        resolve: (name) => resolveInertiaPage(name, appPages, modulePages),
        setup: ({ App, props }) => <App {...props} />,
    }),
);
