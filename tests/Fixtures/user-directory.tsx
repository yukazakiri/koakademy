import { createInertiaApp } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import "../../resources/css/app.css";
import UserIndex from "../../resources/js/pages/administrators/users/index";
const users = [
    {
        id: 1,
        name: "Mika Santos",
        email: "mika@example.test",
        role: "student",
        suspended_at: null,
        email_verified_at: "2026-01-01",
        mfa_configured: true,
        security_two_factor_enabled: true,
        school: { id: 1, name: "School of Computing" },
    },
    {
        id: 2,
        name: "Arun Reyes",
        email: "arun@example.test",
        role: "instructor",
        suspended_at: "2026-01-01",
        email_verified_at: null,
        mfa_configured: false,
        security_two_factor_enabled: true,
    },
].map((user) => ({
    ...user,
    avatar_url: null,
    last_active_at: "2026-01-01T08:00:00Z",
    last_login_at: null,
    created_at: "2026-01-01",
    deleted_at: null,
    capabilities: { view: true, update: true, delete: true, manage_access: true },
}));
createInertiaApp({
    id: "root",
    page: {
        component: "directory",
        props: {
            users: { data: users, total: users.length },
            filters: {},
            analytics: {
                total_users: 2,
                new_users_today: 0,
                new_users_30_days: 0,
                verification_rate: 50,
                online_users: 1,
                online_rate: 50,
                two_factor_rate: 50,
                assignment_rate: 50,
                registrations_chart: [],
                role_distribution: [],
                school_distribution: [],
                department_distribution: [],
                recent_users: [],
                top_active_users: [],
            },
            online_user_ids: [1],
            options: { roles: ["student", "instructor"], schools: [{ id: 1, name: "School of Computing" }], departments: [] },
            user: { name: "Administrator" },
            can_create: true,
        },
        url: "/administrators/users",
        version: "fixture",
        clearHistory: false,
        encryptHistory: false,
    },
    resolve: () => UserIndex,
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
