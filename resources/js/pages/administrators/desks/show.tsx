import AdminLayout from "@/components/administrators/admin-layout";
import { DeskShell } from "@/components/dashboards/desk-shell";
import type { DeskProps } from "@/components/dashboards/types";
import { Head } from "@inertiajs/react";

/**
 * Renders any administrative desk.
 *
 * All layout and widget composition lives in the shared kit under
 * `components/dashboards/`, so this stays a thin wrapper and a desk only has to change its
 * PHP class, never this file.
 */
export default function AdministratorDesk({ desk, desks, context, scope, kpis, queues, trends, tables }: DeskProps) {
    return (
        <AdminLayout title={desk.title}>
            <Head title={`${desk.title} Dashboard`} />

            <DeskShell desk={desk} desks={desks} context={context} scope={scope} kpis={kpis} queues={queues} trends={trends} tables={tables} />
        </AdminLayout>
    );
}