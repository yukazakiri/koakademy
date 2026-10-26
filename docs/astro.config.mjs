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
                    label: "Platform Features",
                    items: [
                        { slug: "features", label: "Features Overview" },
                        { slug: "features/student-information-system", label: "Student Records & SIS" },
                        { slug: "features/academics-scheduling", label: "Academics & Timetabling" },
                        { slug: "features/enrollment-management", label: "Enrollment Management" },
                        { slug: "features/grading-transcripts", label: "Grading & Transcripts" },
                        { slug: "features/finance-cashiering", label: "Financials & Cashiering" },
                        { slug: "features/portals-workspaces", label: "Portals & Workspaces" },
                        { slug: "user-guide/color-themes", label: "Color Themes & Appearance" },
                        { slug: "features/bundled-modules", label: "Bundled Modules" },
                        { slug: "features/ai-mcp", label: "AI & MCP Server" },
                    ],
                },
                {
                    label: "Self-Hosting & Operations",
                    items: [
                        { slug: "self-hosting/installation", label: "Overview & Matrix" },
                        { slug: "self-hosting/docker-compose", label: "Docker Compose" },
                        { slug: "self-hosting/docker-swarm", label: "Docker Swarm" },
                        { slug: "self-hosting/dokploy", label: "Dokploy" },
                        { slug: "self-hosting/coolify", label: "Coolify" },
                        { slug: "self-hosting/kubernetes", label: "Kubernetes (K8s)" },
                        { slug: "self-hosting/openshift", label: "Red Hat OpenShift" },
                        { slug: "self-hosting/deployment", label: "Operations & Runbook" },
                        { slug: "self-hosting/configuration", label: "Configuration" },
                        { slug: "self-hosting/troubleshooting", label: "Troubleshooting" },
                        { slug: "self-hosting/faq", label: "FAQ" },
                    ],
                },
                {
                    label: "Administration & Security",
                    items: [
                        { slug: "system/architecture-domain", label: "Domain Model & Tenancy" },
                        { slug: "system/auth-authorization", label: "Auth, Roles & Permissions" },
                        { slug: "system/modules-flags", label: "Modules & Feature Flags" },
                        { slug: "system/observability", label: "Observability & Audit Logs" },
                    ],
                },
                {
                    label: "Enrollment Blueprints",
                    collapsed: true,
                    items: [
                        { slug: "enrollment-policies/overview", label: "Overview" },
                        { slug: "enrollment-policies/quick-start", label: "Quickstart" },
                        { slug: "enrollment-policies/scopes-inheritance", label: "Scopes" },
                        { slug: "enrollment-policies/availability-eligibility-documents", label: "Eligibility & Documents" },
                        { slug: "enrollment-policies/subjects-classes-tuition", label: "Subjects & Fees" },
                        { slug: "enrollment-policies/approvals-notifications", label: "Approvals" },
                        { slug: "enrollment-policies/simulation-publication", label: "Rollout & Simulation" },
                        { slug: "enrollment-policies/troubleshooting-deployment", label: "Diagnostics & Safety" },
                    ],
                },
                {
                    label: "Extensibility & Modules",
                    collapsed: true,
                    items: [
                        { slug: "development/enrollment-policy-extensions", label: "Policy Extensions" },
                        { slug: "development/regulatory-report-providers", label: "Regulatory Reports" },
                        { slug: "development/authority-code-imports", label: "Authority Code Imports" },
                        { slug: "maintainers/create-module", label: "Module Authoring" },
                        { slug: "maintainers/module-registry", label: "Module Registry" },
                    ],
                },
                {
                    label: "Developer Guide",
                    collapsed: true,
                    items: [
                        { slug: "system/frontend", label: "Frontend Architecture" },
                        { slug: "system/enrollment-engine", label: "Enrollment Engine Core" },
                        { slug: "system/queues-pdf", label: "Queues & PDF Pipeline" },
                    ],
                },
                {
                    label: "API Reference",
                    collapsed: true,
                    items: [
                        { slug: "api/api-overview", label: "Overview" },
                        { slug: "api/mcp", label: "Model Context Protocol (MCP)" },
                        { slug: "api/developer-api", label: "Settings API" },
                        { slug: "api/student-verification-api", label: "Verification API" },
                    ],
                },
                {
                    label: "Maintainers & Governance",
                    collapsed: true,
                    items: [
                        { slug: "maintainers/ci", label: "CI Pipeline" },
                        { slug: "maintainers/releases", label: "Releases" },
                        { slug: "maintainers/automation", label: "Automation" },
                        { slug: "maintainers/documentation", label: "Docs Engine" },
                        { slug: "maintainers/oss-documentation", label: "OSS Standards" },
                        { slug: "maintainers/oss-hardening", label: "OSS Hardening" },
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
