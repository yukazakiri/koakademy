import AdminLayout from "@/components/administrators/admin-layout";
import type { EmailTemplateConfiguration, EmailTemplateEditorData } from "@/lib/email-template-canvas";
import {
    index as emailTemplatesIndex,
    preview as previewEmailTemplate,
    update as updateEmailTemplate,
} from "@/routes/administrators/system-management/email-templates";
import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import { toast } from "sonner";

import { EmailTemplateEditor } from "./components/email-template-canvas/email-template-editor";
import type { SystemManagementPageProps } from "./types";

type EditableEmailTemplate = {
    policy_id: number;
    policy_name: string;
    version_id: number;
    version_state: "draft" | "published" | "archived";
    step_key: string;
    step_label: string;
    step_status: string | null;
    terminal: boolean;
    position: number;
    configured: boolean;
    action_key: string;
    notification: string | null;
    configuration: Record<string, unknown>;
};

export default function SystemManagementEmailTemplateEditorPage({
    user,
    access,
    email_template,
    email_template_editor,
}: SystemManagementPageProps & {
    email_template: EditableEmailTemplate;
    email_template_editor: EmailTemplateEditorData;
}) {
    const canUpdate = access.sections.email_templates?.can_update ?? false;
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [editorRevision, setEditorRevision] = useState(0);

    const backToIndex = () => router.visit(emailTemplatesIndex.url());

    const handleSave = (updatedConfig: EmailTemplateConfiguration) => {
        if (!canUpdate) {
            toast.error("You do not have permission to edit email templates.");
            return;
        }

        setSaving(true);
        setErrors({});
        router.put(
            updateEmailTemplate.url(email_template.policy_id),
            {
                step_key: email_template.step_key,
                configuration: { ...updatedConfig },
                change_notes: `Email template updated for step [${email_template.step_key}].`,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Email template saved to the draft for “${email_template.step_label}”.`);
                    setEditorRevision((current) => current + 1);
                },
                onError: (errors) => {
                    setErrors(errors);
                    const first = Object.values(errors)[0];
                    toast.error(typeof first === "string" ? first : "The email template could not be saved.");
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <AdminLayout user={user} title="Email Templates" flush>
            <Head title={`Email Template · ${email_template.step_label}`} />
            <EmailTemplateEditor
                key={`${email_template.policy_id}:${email_template.step_key}:${editorRevision}`}
                configuration={email_template.configuration}
                data={email_template_editor}
                stepKey={email_template.step_key}
                stepLabel={email_template.step_label}
                terminal={email_template.terminal}
                policyName={email_template.policy_name}
                previewUrl={previewEmailTemplate.url(email_template.policy_id)}
                onSave={handleSave}
                onCancel={backToIndex}
                saving={saving}
                readOnly={!canUpdate}
                errors={errors}
            />
        </AdminLayout>
    );
}
