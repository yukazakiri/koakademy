import starlight from "@astrojs/starlight";
import { defineConfig } from "astro/config";

export default defineConfig({
    site: "https://yukazakiri.github.io",
    base: "/koakademy",
    integrations: [
        starlight({
            title: "KoAkademy Docs",
            description: "KoAkademy platform documentation",
            favicon: "/favicon.ico",
            logo: {
                src: "./public/logo.png",
                alt: "KoAkademy",
            },
            head: [
                { tag: "link", attrs: { rel: "icon", href: "/koakademy/favicon.ico", sizes: "any" } },
                { tag: "link", attrs: { rel: "icon", href: "/koakademy/favicon.svg", type: "image/svg+xml" } },
                { tag: "link", attrs: { rel: "icon", href: "/koakademy/favicon-96x96.png", type: "image/png", sizes: "96x96" } },
                { tag: "link", attrs: { rel: "apple-touch-icon", href: "/koakademy/favicon-96x96.png" } },
            ],
            lastUpdated: true,
            editLink: {
                baseUrl: "https://github.com/yukazakiri/koakademy/edit/master/docs/",
            },
            tableOfContents: { minHeadingLevel: 2, maxHeadingLevel: 3 },
            customCss: ["./src/styles/custom.css"],
            social: [{ icon: "github", label: "GitHub", href: "https://github.com/yukazakiri/koakademy" }],
            sidebar: [
                { label: "Home", link: "/" },
                {
                    label: "Start Here",
                    items: [
                        { slug: "start-here/introduction" },
                        { slug: "start-here/development" },
                        { slug: "start-here/architecture" },
                        { slug: "start-here/contributing" },
                    ],
                },
                {
                    label: "System Internals",
                    items: [
                        { slug: "system/architecture-domain" },
                        { slug: "system/auth-authorization" },
                        { slug: "system/modules-flags" },
                        { slug: "system/enrollment-engine" },
                        { slug: "system/queues-pdf" },
                        { slug: "system/frontend" },
                        { slug: "development/enrollment-policy-extensions" },
                    ],
                },
                {
                    label: "Maintainers",
                    items: [
                        { slug: "maintainers/ci" },
                        { slug: "maintainers/releases" },
                        { slug: "maintainers/create-module" },
                        { slug: "maintainers/module-registry" },
                        { slug: "maintainers/automation" },
                        { slug: "maintainers/documentation" },
                    ],
                },
                {
                    label: "Self-Hosting",
                    collapsed: true,
                    items: [
                        { slug: "self-hosting/installation" },
                        { slug: "self-hosting/deployment" },
                        { slug: "self-hosting/configuration" },
                        { slug: "self-hosting/troubleshooting" },
                        { slug: "self-hosting/faq" },
                    ],
                },
                {
                    label: "API Reference",
                    collapsed: true,
                    items: [
                        { slug: "api/api-overview" },
                        { slug: "api/developer-api" },
                        { slug: "api/student-verification-api" },
                    ],
                },
                {
                    label: "User Guide",
                    collapsed: true,
                    items: [
                        { slug: "user-guide/introduction" },
                        { slug: "user-guide/features-overview" },
                        { slug: "user-guide/admin-portal" },
                        { slug: "user-guide/faculty-portal" },
                        { slug: "user-guide/student-portal" },
                        { slug: "user-guide/modules" },
                        {
                            label: "Enrollment Blueprints",
                            collapsed: true,
                            items: [
                                { slug: "enrollment-policies/overview" },
                                { slug: "enrollment-policies/quick-start" },
                                { slug: "enrollment-policies/scopes-inheritance" },
                                { slug: "enrollment-policies/availability-eligibility-documents" },
                                { slug: "enrollment-policies/subjects-classes-tuition" },
                                { slug: "enrollment-policies/approvals-notifications" },
                                { slug: "enrollment-policies/simulation-publication" },
                                { slug: "enrollment-policies/troubleshooting-deployment" },
                            ],
                        },
                    ],
                },
            ],
            expressiveCode: {
                themes: ["github-dark"],
                styleOverrides: {
                    borderRadius: "0.5rem",
                },
            },
        }),
    ],
});
