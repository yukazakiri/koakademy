"use strict";

const CACHE_NAME = "offline-cache-v2";
const OFFLINE_URL = "/offline.html";

const filesToCache = [OFFLINE_URL];

self.addEventListener("install", (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(filesToCache)));
});

self.addEventListener("fetch", (event) => {
    const { request } = event;

    if (request.method !== "GET") {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Never intercept versioned Vite assets, build-version probes, health checks,
    // API endpoints, or Inertia JSON requests so active tabs always talk directly
    // to the deployment's HTTP caching layer across zero-downtime rollouts.
    if (
        request.headers.get("X-Inertia") ||
        url.pathname.startsWith("/build/") ||
        url.pathname === "/build-version" ||
        url.pathname.startsWith("/api/") ||
        url.pathname === "/up" ||
        url.pathname === "/health"
    ) {
        return;
    }

    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request).catch(() => {
                return caches.match(OFFLINE_URL);
            }),
        );
    }
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                    return undefined;
                }),
            );
        }),
    );
});
