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
        posthog?: { opt_out_capturing?: () => void; reset?: () => void };
        op?: (...args: unknown[]) => void;
        umami?: { track?: (...args: unknown[]) => void };
        _paq?: unknown[];
        countly?: unknown;
        clarity?: (...args: unknown[]) => void;
        ym?: (...args: unknown[]) => void;
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
 *
 * Nodes are injected in document order, and an external `<script src>` is
 * awaited before any following inline script runs. Several providers ship an
 * external script followed by inline initialisation that depends on it (the
 * Countly snippet calls `countly.init()`), and appending them together would
 * run the inline code while the SDK was still loading.
 */
function injectHtmlSnippet(snippet: string): (() => void) | null {
    if (typeof document === "undefined" || snippet.trim() === "") {
        return null;
    }

    const template = document.createElement("template");
    template.innerHTML = snippet.trim();

    const createdNodes: HTMLElement[] = [];
    let disposed = false;

    // Tear down whatever exists at call time, so the returned cleanup works
    // even if a later node is still waiting on the network.
    const cleanup = () => {
        disposed = true;
        createdNodes.forEach((node) => node.remove());
        resetAnalyticsGlobals();
    };

    const queue = Array.from(template.content.childNodes).filter((node): node is HTMLElement => node instanceof HTMLElement);

    const injectNext = (index: number): void => {
        if (disposed || index >= queue.length) {
            return;
        }

        const executableNode = createExecutableNode(queue[index]);

        if (!executableNode) {
            injectNext(index + 1);

            return;
        }

        const target = executableNode.tagName === "NOSCRIPT" ? document.body : document.head;
        target.appendChild(executableNode);
        createdNodes.push(executableNode);

        if (executableNode instanceof HTMLScriptElement && executableNode.src) {
            // Wait for the external script to load (or fail) before running
            // anything that depends on it.
            const proceed = () => {
                if (disposed) {
                    return;
                }

                // Give the freshly loaded SDK a turn to define its global
                // before the next inline script references it.
                window.setTimeout(() => injectNext(index + 1), 0);
            };

            if (executableNode.dataset.analyticsLoaded === "true") {
                proceed();

                return;
            }

            executableNode.addEventListener("load", proceed, { once: true });
            executableNode.addEventListener("error", proceed, { once: true });

            // A cached script can finish before the listener is attached.
            if (executableNode.dataset.analyticsLoadState === "complete") {
                proceed();
            }

            return;
        }

        injectNext(index + 1);
    };

    injectNext(0);

    if (createdNodes.length === 0) {
        return null;
    }

    return cleanup;
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

/**
 * Stop provider runtimes and clear their globals.
 *
 * Removing the injected `<script>` tags only removes markup. Each provider's
 * SDK installs globals, timers, and listeners of its own once it has executed,
 * so a disabled provider would otherwise keep collecting until a hard reload.
 * Providers expose an opt-out for exactly this; where one exists, call it
 * before clearing state, and fall back to resetting the globals.
 */
function resetAnalyticsGlobals(): void {
    if (typeof window === "undefined") {
        return;
    }

    if (typeof window.posthog?.opt_out_capturing === "function") {
        window.posthog.opt_out_capturing();
    }

    if (typeof window.op === "function") {
        // OpenPanel's SDK has no teardown call, so dropping the queue global is
        // the strongest signal available to it.
        try {
            window.op("shutdown");
        } catch {
            // Providers may reject unknown commands; the global reset below is
            // what actually stops new events.
        }
    }

    if (typeof window.umami?.track === "function") {
        try {
            window.umami.track = undefined;
        } catch {
            // Fall through to the global reset.
        }
    }

    if (Array.isArray(window._paq)) {
        // Matomo: clear the command queue so no further tracking commands run.
        window._paq.length = 0;
    }

    ["gtag", "dataLayer", "posthog", "op", "umami", "_paq", "countly", "clarity", "ym"].forEach((key) => {
        resetWindowProperty(key as ProviderGlobalKey, providerGlobalFallback(key as ProviderGlobalKey));
    });
}

type ProviderGlobalKey = "gtag" | "dataLayer" | "posthog" | "op" | "umami" | "_paq" | "countly" | "clarity" | "ym";

/**
 * Queue-shaped globals get a fresh array so a late-arriving call from a
 * torn-down provider cannot repopulate the previous one.
 */
function providerGlobalFallback(key: ProviderGlobalKey): unknown {
    return key === "dataLayer" || key === "_paq" ? [] : undefined;
}

function resetWindowProperty(key: ProviderGlobalKey, fallback: unknown): void {
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
