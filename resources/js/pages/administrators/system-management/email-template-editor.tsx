import { Head, router } from "@inertiajs/react";
import { ArrowLeft, MailCheck } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

import { update as updateEmailTemplate } from "@/routes/administrators/system-management/email-templates";
import SystemManagementLayout from "./layout";
import type { Action, WorkflowStep } from "./enrollment-policy/types";
import { EmailTemplateEditor } from "./enrollment-policy/components/workflow-canvas/email-template-editor";
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

/** The exact shape `EnrollmentEmailTemplateService` persists for `enrollment.notify`. */
type EmailTemplatePayload = {
    notification: string;
    subject?: string;
    headline?: string;
    custom_body?: string;
    button_label?: string;
    button_url?: string;
    include_details?: boolean;
    include_subjects?: boolean;
    include_tuition?: boolean;
};

export default function SystemManagementEmailTemplateEditorPage({
    user,
    access,
    email_template,
}: SystemManagementPageProps & {
    email_template: EditableEmailTemplate;
}) {
    const canUpdate = access.sections.email_templates?.can_update ?? false;
    const [saving, setSaving] = useState(false);

    const backToIndex = () => router.visit("/administrators/system-management/email-templates");

    const step: WorkflowStep = {
        key: email_template.step_key,
        label: email_template.step_label,
        entry: email_template.position === 0,
        terminal: email_template.terminal,
        status: email_template.step_status ?? "Under Review",
        actions: [],
        transitions: [],
    };

    const action: Action = {
        key: email_template.action_key,
        handler: "enrollment.notify",
        configuration: email_template.configuration,
    };

    const handleSave = (updatedConfig: Record<string, unknown>) => {
        if (!canUpdate) {
            toast.error("You do not have permission to edit email templates.");
            return;
        }

        setSaving(true);
        router.put(
            updateEmailTemplate.url(email_template.policy_id),
            {
                step_key: email_template.step_key,
                configuration: updatedConfig as EmailTemplatePayload,
                change_notes: `Email template updated for step [${email_template.step_key}].`,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Email template saved for “${email_template.step_label}”.`);
                    router.visit("/administrators/system-management/email-templates");
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0];
                    toast.error(typeof first === "string" ? first : "The email template could not be saved.");
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="email_templates"
            heading="Email Templates"
            description="Customize the enrollment email sent when this workflow milestone is reached."
        >
            <Head title={`Email Template · ${email_template.step_label}`} />

            <div className="flex h-[calc(100vh-11rem)] flex-col gap-4">
                {/* Breadcrumb */}
                <button
                    type="button"
                    onClick={backToIndex}
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-xs transition-colors"
                >
                    <ArrowLeft className="size-3.5" /> All email templates
                </button>

                {/* Editor fills the viewport width — a modal could not. */}
                <div className="border-border/60 bg-card/40 min-h-0 flex-1 overflow-hidden rounded-xl border shadow-xs backdrop-blur-xs">
                    <EmailTemplateEditor
                        key={`${email_template.policy_id}:${email_template.step_key}`}
                        step={step}
                        action={action}
                        onSave={handleSave}
                        title={email_template.step_label}
                        description="Customize the automated email dispatched to students when this milestone is reached."
                        contextLabel={email_template.policy_name}
                        saving={saving}
                        onCancel={backToIndex}
                        cancelLabel="Discard & back"
                        saveLabel="Save & Apply Template"
                    />
                </div>

                {!canUpdate && (
                    <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                        <MailCheck className="size-3.5" />
                        You have read-only access to email templates. Ask an administrator to grant “Update System Management Email
                        Templates” to make changes.
                    </p>
                )}
            </div>
        </SystemManagementLayout>
    );
}
