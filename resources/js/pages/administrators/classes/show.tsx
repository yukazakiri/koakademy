import AdminLayout from "@/components/administrators/admin-layout";
import { Head } from "@inertiajs/react";
import { CommandDossierHub } from "./dossier/command-dossier-hub";
import type { ClassShowProps } from "./record/types";

export default function AdministratorClassShow(props: ClassShowProps) {
    return (
        <AdminLayout user={props.user} title="Class dossier">
            <Head title={`Class • ${props.class.record_title}`} />
            <CommandDossierHub {...props} />
        </AdminLayout>
    );
}
