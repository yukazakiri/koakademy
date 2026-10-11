import { Badge } from "@/components/reui/badge";
import { IconTile } from "@/components/reui/icon-tile";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { edit as emailTemplateEdit } from "@/routes/administrators/system-management/email-templates";
import { Head, Link } from "@inertiajs/react";
import { ArrowRight, CheckCircle2, Eye, FileWarning, Info, MailCheck, Pencil, Search, Send, Sparkles } from "lucide-react";
import { useMemo, useState } from "react";

import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

type EmailTemplate = {
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

type EmailTemplatePolicy = {
    id: number;
    name: string;
    scope: Record<string, string>;
    is_enabled: boolean;
    templates: EmailTemplate[];
};

type EmailTemplateNotificationType = {
    value: string;
    label: string;
};

export default function SystemManagementEmailTemplatesPage({
    user,
    access,
    email_template_policies,
    email_template_notification_types,
}: SystemManagementPageProps & {
    email_template_policies: EmailTemplatePolicy[];
    email_template_notification_types: EmailTemplateNotificationType[];
}) {
    const canUpdate = access.sections.email_templates?.can_update ?? false;

    const [search, setSearch] = useState("");
    const [showUnconfiguredOnly, setShowUnconfiguredOnly] = useState(false);

    const notificationLabels = useMemo(
        () => new Map(email_template_notification_types.map((type) => [type.value, type.label] as const)),
        [email_template_notification_types],
    );

    const templates = useMemo(() => email_template_policies.flatMap((policy) => policy.templates), [email_template_policies]);

    const filteredTemplates = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return templates.filter((template) => {
            if (showUnconfiguredOnly && template.configured) return false;
            if (!needle) return true;

            const haystack = [
                template.step_label,
                template.step_key,
                template.policy_name,
                template.step_status ?? "",
                String(template.configuration.subject ?? ""),
                String(template.configuration.headline ?? ""),
                notificationLabels.get(template.notification ?? "") ?? "",
            ]
                .join(" ")
                .toLowerCase();

            return haystack.includes(needle);
        });
    }, [notificationLabels, search, showUnconfiguredOnly, templates]);

    const groupedTemplates = useMemo(() => {
        const groups = new Map<number, { policy: EmailTemplatePolicy; templates: EmailTemplate[] }>();

        filteredTemplates.forEach((template) => {
            const policy = email_template_policies.find((item) => item.id === template.policy_id);
            if (!policy) return;

            const existing = groups.get(policy.id);
            if (existing) {
                existing.templates.push(template);

                return;
            }

            groups.set(policy.id, { policy, templates: [template] });
        });

        return [...groups.values()];
    }, [email_template_policies, filteredTemplates]);

    const configuredCount = templates.filter((template) => template.configured).length;
    const unconfiguredCount = templates.length - configuredCount;

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="email_templates"
            heading="Email Templates"
            description="Customize the enrollment emails applicants receive at each milestone of the admissions pipeline."
        >
            <Head title="Email Templates" />

            <div className="space-y-5">
                {/* Summary + Filters */}
                <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                    <CardHeader className="border-border/40 gap-4 border-b pb-4 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex items-start gap-3">
                            <IconTile variant="soft" size="default" className="text-primary">
                                <MailCheck className="size-5" />
                            </IconTile>
                            <div>
                                <CardTitle className="text-base font-semibold">Enrollment email templates</CardTitle>
                                <CardDescription className="mt-0.5 text-xs">
                                    {configuredCount} of {templates.length} workflow steps dispatch an email. Changes save to the policy draft.
                                </CardDescription>{" "}
                            </div>
                        </div>

                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div className="relative">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search templates..."
                                    aria-label="Search email templates"
                                    className="bg-background/70 h-8 w-full pl-8 text-xs sm:w-64"
                                />
                            </div>
                            <Button
                                type="button"
                                variant={showUnconfiguredOnly ? "default" : "outline"}
                                size="sm"
                                className="h-8 justify-start gap-1.5 px-2.5 text-xs"
                                onClick={() => setShowUnconfiguredOnly((current) => !current)}
                            >
                                <FileWarning className="size-3.5" />
                                {showUnconfiguredOnly ? "Showing gaps" : "Show gaps only"}
                                {unconfiguredCount > 0 && (
                                    <Badge variant="secondary" className="ml-0.5 h-4 px-1 text-[10px]">
                                        {unconfiguredCount}
                                    </Badge>
                                )}
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent className="p-3">
                        <div className="text-muted-foreground bg-background/70 border-border/50 flex items-start gap-2 rounded-lg border p-2.5 text-[11px] leading-relaxed">
                            <Info className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                Each card maps to one workflow step of an enrollment policy. Use{" "}
                                <strong className="text-foreground font-semibold">Edit template</strong> to customize the subject, intro paragraphs,
                                and supported call-to-action fields on the drag-and-drop email canvas. Pipeline Studio opens the same editor. Saves
                                update the policy draft.
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* Template list grouped by policy */}
                {groupedTemplates.length === 0 ? (
                    <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                        <CardContent className="flex flex-col items-center gap-2 p-10 text-center">
                            <MailCheck className="text-muted-foreground size-8" />
                            <p className="text-sm font-medium">No email templates match your filters.</p>
                            <p className="text-muted-foreground max-w-md text-xs">
                                {templates.length === 0
                                    ? "Create an enrollment policy with a workflow to start customizing applicant emails."
                                    : "Try clearing the search box or disabling “Show gaps only”."}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    groupedTemplates.map(({ policy, templates: policyTemplates }) => (
                        <Card key={policy.id} className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                            <CardHeader className="border-border/40 border-b pb-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <CardTitle className="text-sm font-semibold">{policy.name}</CardTitle>
                                        <Badge variant="outline" className="border-border/60 h-5 px-1.5 font-mono text-[10px]">
                                            {policyTemplates.length} step{policyTemplates.length === 1 ? "" : "s"}
                                        </Badge>
                                        {!policy.is_enabled && (
                                            <Badge
                                                variant="outline"
                                                className="h-5 border-amber-500/40 bg-amber-500/10 px-1.5 text-[10px] text-amber-700 dark:text-amber-300"
                                            >
                                                Disabled
                                            </Badge>
                                        )}
                                    </div>
                                    <span className="text-muted-foreground text-[11px]">
                                        {Object.values(policy.scope ?? {})
                                            .filter(Boolean)
                                            .join(" · ")}
                                    </span>
                                </div>
                            </CardHeader>

                            <CardContent className="divide-border/50 divide-y p-0">
                                {policyTemplates.map((template) => {
                                    const subject = String(template.configuration.subject ?? "");
                                    const notificationLabel = notificationLabels.get(template.notification ?? "");

                                    return (
                                        <div
                                            key={`${template.policy_id}-${template.step_key}`}
                                            className="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between"
                                        >
                                            <div className="min-w-0 space-y-1.5">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="text-sm font-medium">{template.step_label}</span>
                                                    {template.step_status && (
                                                        <Badge variant="secondary" className="h-5 px-1.5 font-mono text-[10px]">
                                                            {template.step_status}
                                                        </Badge>
                                                    )}
                                                    {template.configured ? (
                                                        <span className="flex items-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400">
                                                            <CheckCircle2 className="size-3" /> Configured
                                                        </span>
                                                    ) : (
                                                        <span className="flex items-center gap-1 text-[10px] font-medium text-amber-600 dark:text-amber-400">
                                                            <FileWarning className="size-3" /> No email yet
                                                        </span>
                                                    )}
                                                </div>

                                                <p className="text-muted-foreground truncate text-xs">
                                                    <span className="text-foreground font-medium">Subject:</span>{" "}
                                                    {subject || "Uses the default template subject"}
                                                </p>
                                                {!template.configured && (
                                                    <p className="text-[11px] text-amber-600 dark:text-amber-400">
                                                        Sends the built-in default for {notificationLabel ?? "this milestone"}.
                                                    </p>
                                                )}
                                                <p className="text-muted-foreground flex items-center gap-1.5 text-[11px]">
                                                    <Sparkles className="size-3 shrink-0" />
                                                    {notificationLabel ?? "No template selected"}
                                                </p>
                                            </div>

                                            <Link
                                                href={emailTemplateEdit.url(template.policy_id, { query: { step_key: template.step_key } })}
                                                className={buttonVariants({
                                                    variant: "outline",
                                                    size: "sm",
                                                    className: "h-10 shrink-0 gap-1.5 px-2.5 text-xs",
                                                })}
                                            >
                                                {canUpdate ? <Pencil className="size-3.5" /> : <Eye className="size-3.5" />}
                                                {canUpdate ? "Edit template" : "View template"}
                                                <ArrowRight className="size-3.5" />
                                            </Link>
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    ))
                )}

                {!canUpdate && (
                    <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                        <Send className="size-3.5" />
                        You have read-only access to email templates. Ask an administrator to grant “Update System Management Email Templates” to make
                        changes.
                    </p>
                )}
            </div>
        </SystemManagementLayout>
    );
}
