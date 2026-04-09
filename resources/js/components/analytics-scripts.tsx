import type { AnalyticsConfig } from "@/types/analytics";
import { usePage } from "@inertiajs/react";
import { useEffect } from "react";

declare global {
    interface Window {
        __dccpAnalyticsState?: {
            signature?: string;
            cleanup?: () => void;
        };
        dataLayer?: unknown[];
        gtag?: (...args: unknown[]) => void;
        op?: (...args: unknown[]) => void;
        umami?: {
            track?: (...args: unknown[]) => void;
        };
    }
}

interface AnalyticsPageProps {
    analytics?: AnalyticsConfig;
}

export function AnalyticsScripts() {
    const { props, url } = usePage<AnalyticsPageProps>();
    const analytics = props.analytics;

    useEffect(() => {
        if (typeof window === "undefined") {
            return;
        }

        const signature = JSON.stringify(analytics ?? null);
        const state = window.__dccpAnalyticsState ?? {};

        if (state.signature === signature) {
            window.__dccpAnalyticsState = state;
            return;
        }

        state.cleanup?.();
        state.signature = signature;

        if (!analytics?.enabled) {
            state.cleanup = undefined;
            window.__dccpAnalyticsState = state;

            return;
        }

        state.cleanup = analytics.script.trim() !== "" ? injectHtmlSnippet(analytics.script) : injectProviderScripts(analytics);
        window.__dccpAnalyticsState = state;
    }, [analytics]);

    useEffect(() => {
        if (!analytics?.enabled || analytics.script.trim() !== "" || analytics.provider !== "google") {
            return;
        }

        const measurementId = analytics.settings.google_measurement_id;

        if (!measurementId || typeof window.gtag !== "function") {
            return;
        }

        window.gtag("event", "page_view", {
            page_location: window.location.href,
            page_path: url,
            page_title: document.title,
            send_to: measurementId,
        });
    }, [analytics, url]);

    return null;
}

function injectProviderScripts(analytics: AnalyticsConfig): () => void {
    switch (analytics.provider) {
        case "google":
            return injectGoogleScripts(analytics.settings.google_measurement_id);
        case "ackee":
            return injectAckeeScripts(analytics.settings.ackee_script_url, analytics.settings.ackee_server_url, analytics.settings.ackee_domain_id);
        case "umami":
            return injectUmamiScripts(
                analytics.settings.umami_script_url,
                analytics.settings.umami_website_id,
                analytics.settings.umami_host_url,
                analytics.settings.umami_domains,
            );
        case "openpanel":
            return injectOpenPanelScripts(analytics.settings);
        default:
            return () => resetAnalyticsGlobals();
    }
}

function injectGoogleScripts(measurementId: string): () => void {
    if (!measurementId) {
        return () => resetAnalyticsGlobals();
    }

    const createdNodes = [
        appendScript({
            async: true,
            src: `https://www.googletagmanager.com/gtag/js?id=${measurementId}`,
        }),
        appendScript({
            text: `
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
window.gtag = window.gtag || gtag;
gtag('js', new Date());
gtag('config', '${escapeInlineScriptValue(measurementId)}', { send_page_view: false });
            `.trim(),
        }),
    ];

    return () => cleanupAnalyticsNodes(createdNodes);
}

function injectAckeeScripts(scriptUrl: string, serverUrl: string, domainId: string): () => void {
    if (!scriptUrl || !serverUrl || !domainId) {
        return () => resetAnalyticsGlobals();
    }

    const createdNodes = [
        appendScript({
            async: true,
            src: scriptUrl,
            dataset: {
                ackeeServer: serverUrl,
                ackeeDomainId: domainId,
            },
        }),
    ];

    return () => cleanupAnalyticsNodes(createdNodes);
}

function injectUmamiScripts(scriptUrl: string, websiteId: string, hostUrl: string, domains: string): () => void {
    if (!scriptUrl || !websiteId) {
        return () => resetAnalyticsGlobals();
    }

    const createdNodes = [
        appendScript({
            defer: true,
            src: scriptUrl,
            dataset: {
                websiteId,
                ...(hostUrl ? { hostUrl } : {}),
                ...(domains ? { domains } : {}),
            },
        }),
    ];

    return () => cleanupAnalyticsNodes(createdNodes);
}

function injectOpenPanelScripts(settings: AnalyticsConfig["settings"]): () => void {
    if (!settings.openpanel_script_url || !settings.openpanel_api_url || !settings.openpanel_client_id) {
        return () => resetAnalyticsGlobals();
    }

    const openPanelConfig: Record<string, unknown> = {
        apiUrl: settings.openpanel_api_url,
        clientId: settings.openpanel_client_id,
        trackScreenViews: settings.openpanel_track_screen_views,
        trackOutgoingLinks: settings.openpanel_track_outgoing_links,
        trackAttributes: settings.openpanel_track_attributes,
    };

    if (settings.openpanel_session_replay) {
        openPanelConfig.sessionReplay = {
            enabled: true,
        };
    }

    const createdNodes = [
        appendScript({
            text: `
window.op = window.op || function () {
    var queue = [];
    return new Proxy(function () {
        if (arguments.length) {
            queue.push([].slice.call(arguments));
        }
    }, {
        get: function (target, property) {
            if (property === 'q') {
                return queue;
            }

            return function () {
                queue.push([property].concat([].slice.call(arguments)));
            };
        },
        has: function (target, property) {
            return property === 'q';
        }
    });
}();
window.op('init', ${JSON.stringify(openPanelConfig)});
            `.trim(),
        }),
        appendScript({
            async: true,
            defer: true,
            src: settings.openpanel_script_url,
        }),
    ];

    return () => cleanupAnalyticsNodes(createdNodes);
}

function injectHtmlSnippet(snippet: string): () => void {
    if (!snippet.trim()) {
        return () => resetAnalyticsGlobals();
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

    return () => cleanupAnalyticsNodes(createdNodes);
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

function appendScript(options: {
    async?: boolean;
    defer?: boolean;
    src?: string;
    text?: string;
    dataset?: Record<string, string>;
}): HTMLScriptElement {
    const script = document.createElement("script");

    if (options.async) {
        script.async = true;
    }

    if (options.defer) {
        script.defer = true;
    }

    if (options.src) {
        script.src = options.src;
    }

    if (options.text) {
        script.textContent = options.text;
    }

    if (options.dataset) {
        Object.entries(options.dataset).forEach(([key, value]) => {
            script.dataset[key] = value;
        });
    }

    document.head.appendChild(script);

    return script;
}

function cleanupAnalyticsNodes(nodes: HTMLElement[]): void {
    nodes.forEach((node) => node.remove());
    resetAnalyticsGlobals();
}

function resetAnalyticsGlobals(): void {
    if (typeof window === "undefined") {
        return;
    }

    delete window.gtag;
    delete window.op;
    delete window.umami;
    delete window.dataLayer;
}

function escapeInlineScriptValue(value: string): string {
    return value.replace(/\\/g, "\\\\").replace(/'/g, "\\'");
}
