import starlight from "@astrojs/starlight";
import { defineConfig } from "astro/config";

export default defineConfig({
    site: "https://yukazakiri.github.io",
    base: "/koakademy",
    integrations: [
        starlight({
            title: "KoAkademy",
            description: "Developer, operator, and user documentation for KoAkademy.",
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
                {
                    label: "Getting Started",
                    items: [
                        { slug: "start-here/introduction", label: "Introduction" },
                        { slug: "self-hosting/installation", label: "Quickstart" },
                        { slug: "start-here/architecture", label: "Architecture" },
                        { slug: "start-here/development", label: "Local Development" },
                        { slug: "start-here/contributing", label: "Contributing" },
                    ],
                },
                {
                    label: "Self-Hosting",
                    items: [
                        { slug: "self-hosting/installation", label: "Overview & Matrix" },
                        { slug: "self-hosting/docker-compose", label: "Docker Compose" },
                        { slug: "self-hosting/dokploy", label: "Dokploy" },
                        { slug: "self-hosting/coolify", label: "Coolify" },
                        { slug: "self-hosting/docker-swarm", label: "Docker Swarm" },
                        { slug: "self-hosting/kubernetes", label: "Kubernetes (K8s)" },
                        { slug: "self-hosting/openshift", label: "Red Hat OpenShift" },
                        { slug: "self-hosting/deployment", label: "Operations & Runbook" },
                        { slug: "self-hosting/configuration", label: "Configuration" },
                        { slug: "self-hosting/troubleshooting", label: "Troubleshooting" },
                        { slug: "self-hosting/faq", label: "FAQ" },
                    ],
                },
                {
                    label: "Core Concepts",
                    items: [
                        { slug: "system/architecture-domain", label: "Domain Model" },
                        { slug: "system/auth-authorization", label: "Authentication" },
                        { slug: "system/frontend", label: "Frontend" },
                        { slug: "system/modules-flags", label: "Modules & Flags" },
                        { slug: "system/enrollment-engine", label: "Enrollment Engine" },
                        { slug: "system/queues-pdf", label: "Queues & PDFs" },
                    ],
                },
                {
                    label: "Extensibility",
                    collapsed: true,
                    items: [
                        { slug: "development/enrollment-policy-extensions", label: "Policy Extensions" },
                        { slug: "maintainers/create-module", label: "Module Authoring" },
                        { slug: "maintainers/module-registry", label: "Module Registry" },
                    ],
                },
                {
                    label: "User Guide",
                    collapsed: true,
                    items: [
                        { slug: "user-guide/introduction", label: "Overview" },
                        { slug: "user-guide/features-overview", label: "Features" },
                        { slug: "user-guide/admin-portal", label: "Admin Portal" },
                        { slug: "user-guide/faculty-portal", label: "Faculty Portal" },
                        { slug: "user-guide/student-portal", label: "Student Portal" },
                        { slug: "user-guide/modules", label: "Modules" },
                    ],
                },
                {
                    label: "Enrollment Blueprints",
                    collapsed: true,
                    items: [
                        { slug: "enrollment-policies/overview", label: "Overview" },
                        { slug: "enrollment-policies/quick-start", label: "Quickstart" },
                        { slug: "enrollment-policies/scopes-inheritance", label: "Scopes" },
                        { slug: "enrollment-policies/availability-eligibility-documents", label: "Eligibility" },
                        { slug: "enrollment-policies/subjects-classes-tuition", label: "Subjects & Fees" },
                        { slug: "enrollment-policies/approvals-notifications", label: "Approvals" },
                        { slug: "enrollment-policies/simulation-publication", label: "Rollout" },
                        { slug: "enrollment-policies/troubleshooting-deployment", label: "Diagnostics" },
                    ],
                },
                {
                    label: "API Reference",
                    collapsed: true,
                    items: [
                        { slug: "api/api-overview", label: "Overview" },
                        { slug: "api/developer-api", label: "Settings API" },
                        { slug: "api/student-verification-api", label: "Verification API" },
                    ],
                },
                {
                    label: "Maintainers",
                    collapsed: true,
                    items: [
                        { slug: "maintainers/ci", label: "CI Pipeline" },
                        { slug: "maintainers/releases", label: "Releases" },
                        { slug: "maintainers/automation", label: "Automation" },
                        { slug: "maintainers/documentation", label: "Docs Engine" },
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
