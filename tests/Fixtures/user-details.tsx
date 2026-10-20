import { createInertiaApp } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import "../../resources/css/app.css";
import UserDetails from "../../resources/js/pages/administrators/users/show";

const logs = [
    {
        id: 1,
        event: "signed_in",
        outcome: "success",
        browser: "Chrome",
        platform: "macOS",
        actor: "Mika Santos",
        reason: null,
        created_at: "2026-10-20T08:00:00Z",
    },
    {
        id: 2,
        event: "sign_in_failed",
        outcome: "failure",
        browser: "Safari",
        platform: "iOS",
        actor: "Mika Santos",
        reason: null,
        created_at: "2026-10-19T19:30:00Z",
    },
    {
        id: 3,
        event: "account_suspended",
        outcome: "success",
        browser: "Chrome",
        platform: "Windows",
        actor: "Administrator",
        reason: "Account review",
        created_at: "2026-10-18T11:05:00Z",
    },
];

createInertiaApp({
    id: "root",
    page: {
        component: "details",
        props: {
            account: {
                id: 7,
                name: "Mika Santos",
                email: "mika@example.test",
                role: "student",
                school: "School of Computing",
                department: "Information Technology",
                roles: ["Library support bundle"],
                email_verified_at: "2026-01-01T00:00:00Z",
                created_at: "2026-01-01T00:00:00Z",
                last_login_at: "2026-10-20T08:00:00Z",
                last_active_at: "2026-10-20T08:05:00Z",
                recently_active: true,
                login_challenges_enabled: true,
                authenticator_configured: true,
                email_authentication: false,
                passkeys_count: 2,
                suspended_at: "2026-10-18T11:00:00Z",
                suspension_reason: "Account review",
            },
            can_manage_access: true,
            sessions: { supported: true, data: [{ reference: "enc", browser: "Chrome", platform: "macOS", last_active_at: "2026-10-20T08:05:00Z" }] },
            tokens: [{ id: 3, name: "Phone", abilities: ["read"], last_used_at: "2026-10-20T07:00:00Z", expires_at: null }],
            logs: { data: logs, current_page: 1, last_page: 1, total: logs.length },
            filters: {},
            event_options: ["signed_in", "sign_in_failed", "account_suspended"],
            user: { name: "Administrator" },
        },
        url: "/administrators/users/7",
        version: "fixture",
        clearHistory: false,
        encryptHistory: false,
    },
    resolve: () => UserDetails,
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
