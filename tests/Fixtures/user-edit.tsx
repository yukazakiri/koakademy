import { createInertiaApp } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import "../../resources/css/app.css";
import UserEdit from "../../resources/js/pages/administrators/users/edit";

// The app exposes Ziggy's global `route()` helper through the layout shell.
// The fixture layout is a stub, so the page under test needs the same shim.
(window as unknown as { route: (name: string, param?: number) => string }).route = (name: string, param?: number) => {
    if (name === "administrators.users.index") return "/administrators/users";
    return `/administrators/users/${param ?? ""}`;
};

createInertiaApp({
    id: "root",
    page: {
        component: "edit",
        props: {
            user: {
                id: 7,
                name: "Mika Santos",
                email: "mika@example.test",
                role: "student",
                school_id: 1,
                department_id: 2,
                faculty_id_number: "F-1024",
                record_id: "REC-88",
                roles: [{ id: 4, name: "Library support bundle" }],
            },
            roles: { student: "Student", instructor: "Instructor" },
            schools: [{ id: 1, name: "School of Computing" }],
            departments: [{ id: 2, name: "Information Technology", school_id: 1 }],
            permissions: [{ id: 4, name: "Library support bundle" }],
            auth_user: { name: "Administrator" },
        },
        url: "/administrators/users/7/edit",
        version: "fixture",
        clearHistory: false,
        encryptHistory: false,
    },
    resolve: () => UserEdit,
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
