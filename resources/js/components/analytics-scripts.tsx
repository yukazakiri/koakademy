import type { AnalyticsConfig } from "@/types/analytics";
import { usePage } from "@inertiajs/react";
import { useEffect } from "react";

declare global {
    interface Window {
        __koakademyAnalyticsState?: {
            signature?: string;
            cleanup?: () => void;
        };
        dataLayer?: unknown[];
        gtag?: (...args: unknown[]) => void;
    }
}

interface AnalyticsPageProps extends Record<string, unknown> {
    analytics?: AnalyticsConfig;
}

/**
 * Injects every configured analytics snippet.
 *
 * Any number of providers can be active at once, and each one is injected and
 * torn down independently, so toggling a provider off in settings removes its
 * script without affecting the others.
 */
export function AnalyticsScripts() {
    const { props, url } = usePage<AnalyticsPageProps>();
    const analytics = props.analytics;

    useEffect(() => {
        if (typeof window === "undefined") {
            return;
        }

        const signature = JSON.stringify(analytics ?? null);
        const state = window.__koakademyAnalyticsState ?? {};

        if (state.signature === signature) {
            window.__koakademyAnalyticsState = state;

            return;
        }

        state.cleanup?.();
        state.signature = signature;

        if (!analytics?.enabled) {
            state.cleanup = undefined;
            window.__koakademyAnalyticsState = state;

            return;
        }

        const cleanups = (analytics.providers ?? []).map((provider) => injectHtmlSnippet(provider.snippet)).filter(Boolean);

        state.cleanup = () => {
            cleanups.forEach((cleanup) => cleanup());
        };

        window.__koakademyAnalyticsState = state;
    }, [analytics]);

    useEffect(() => {
        const gaProvider = analytics?.providers?.find((provider) => provider.key === "google" && provider.session);

        if (!analytics?.enabled || !gaProvider) {
            return;
        }

        if (typeof window.gtag !== "function") {
            return;
        }

        // GA4 is configured with send_page_view: false so that Inertia's
        // client-side navigation does not double count. Page views are sent
        // here instead, once per navigation.
        window.gtag("event", "page_view", {
            page_location: window.location.href,
            page_path: url,
            page_title: document.title,
        });
    }, [analytics, url]);

    return null;
}

/**
 * Injects a raw snippet. Script elements are recreated so they execute, since
 * a script inserted from a template's innerHTML is inert.
 */
function injectHtmlSnippet(snippet: string): (() => void) | null {
    if (typeof document === "undefined" || snippet.trim() === "") {
        return null;
    }

    const template = document.createElement("template");
    template.innerHTML = snippet.trim();

    const createdNodes: HTMLElement[] = [];

    Array.from(template.content.childNodes).forEach((node) => {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        const executableNode = createExecutableNode(node);

        if (!executableNode) {
            return;
        }

        const target = executableNode.tagName === "NOSCRIPT" ? document.body : document.head;
        target.appendChild(executableNode);
        createdNodes.push(executableNode);
    });

    if (createdNodes.length === 0) {
        return null;
    }

    return () => {
        createdNodes.forEach((node) => node.remove());
        resetAnalyticsGlobals();
    };
}

function createExecutableNode(node: HTMLElement): HTMLElement | null {
    if (node.tagName === "SCRIPT") {
        const scriptNode = document.createElement("script");

        Array.from(node.attributes).forEach((attribute) => {
            scriptNode.setAttribute(attribute.name, attribute.value);
        });

        scriptNode.textContent = node.textContent;

        return scriptNode;
    }

    return node.cloneNode(true) as HTMLElement;
}

function resetAnalyticsGlobals(): void {
    if (typeof window === "undefined") {
        return;
    }

    resetWindowProperty("gtag", undefined);
    resetWindowProperty("dataLayer", []);
}

function resetWindowProperty(key: "gtag" | "dataLayer", fallback: unknown): void {
    try {
        if (Reflect.deleteProperty(window, key)) {
            return;
        }
    } catch {
        // Some injected analytics scripts define non-configurable globals.
    }

    try {
        Object.defineProperty(window, key, {
            configurable: true,
            writable: true,
            value: fallback,
        });
    } catch {
        try {
            (window as unknown as Record<string, unknown>)[key] = fallback;
        } catch {
            // If the property is also non-writable, leave the provider global in place.
        }
    }
}
