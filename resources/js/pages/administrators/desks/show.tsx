import AdminLayout from "@/components/administrators/admin-layout";
import { DeskShell } from "@/components/dashboards/desk-shell";
import type { DeskProps } from "@/components/dashboards/types";
import { Head } from "@inertiajs/react";

/**
 * Administrative Desk Page Wrapper.
 *
 * Wraps role-scoped administrative desks in the unified AdminLayout, with
 * semantic SEO/meta tags, breadcrumbs and responsive container chrome.
 */
export default function AdministratorDesk({ user, desk, desks, context, scope, kpis, queues, trends, tables }: DeskProps) {
    const pageTitle = `${desk.title} Desk | Administrative Command`;

    return (
        <AdminLayout user={user as any} title={`${desk.title} Desk`}>
            <Head>
                <title>{pageTitle}</title>
                <meta name="description" content={desk.description} />
                <meta name="robots" content="noindex,nofollow" />
            </Head>

            <div className="py-2">
                <DeskShell desk={desk} desks={desks} context={context} scope={scope} kpis={kpis} queues={queues} trends={trends} tables={tables} />
            </div>
        </AdminLayout>
    );
}
