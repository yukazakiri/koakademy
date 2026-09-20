import { createInertiaApp } from "@inertiajs/react";
import createServer from "@inertiajs/react/server";
import ReactDOMServer from "react-dom/server";
import { route } from "ziggy-js";

import { resolveBranding, type Branding } from "@/lib/branding";
import { resolveInertiaPage, type InertiaPageModule, type InertiaPageModules } from "@/lib/inertia-page-resolver";

const appPages: InertiaPageModules = import.meta.glob<InertiaPageModule>("./pages/**/*.tsx");
const modulePages: InertiaPageModules = {
    ...import.meta.glob<InertiaPageModule>("../../Modules/**/resources/assets/js/Pages/**/*.tsx"),
    ...import.meta.glob<InertiaPageModule>("../../vendor/*/*/resources/assets/js/Pages/**/*.tsx"),
};

if (typeof globalThis.localStorage === "undefined") {
    // @ts-expect-error Safe SSR localStorage polyfill
    globalThis.localStorage = {
        getItem: () => null,
        setItem: () => {},
        removeItem: () => {},
        clear: () => {},
        key: () => null,
        length: 0,
    };
}

createServer((page) => {
    const ziggy = (page.props as { ziggy?: any })?.ziggy;
    if (ziggy) {
        // @ts-expect-error globalThis.Ziggy is required by ziggy-js route() during SSR
        globalThis.Ziggy = {
            ...ziggy,
            location: new URL(ziggy.location || "http://localhost"),
        };
        // @ts-expect-error global.route fallback
        global.route = (name: any, params: any, absolute: any) =>
            route(name, params, absolute, {
                ...ziggy,
                location: new URL(ziggy.location || "http://localhost"),
            });
    }

    return createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => {
            const props = page.props as { branding?: Partial<Branding> | null };
            const appName = resolveBranding(props.branding).appName;
            return title ? `${title} - ${appName}` : appName;
        },
        resolve: (name) => resolveInertiaPage(name, appPages, modulePages),
        setup: ({ App, props }) => <App {...props} />,
    });
});
