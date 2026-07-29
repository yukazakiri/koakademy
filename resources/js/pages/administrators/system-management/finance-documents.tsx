import { updateFinanceDocuments } from "@/actions/App/Http/Controllers/AdministratorSystemManagementController";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { useForm } from "@inertiajs/react";
import { AlertTriangle, FileBadge2, Loader2, MailCheck, ReceiptText, Save } from "lucide-react";
import { toast } from "sonner";

import SystemManagementLayout from "./layout";
import type { FinanceDocumentSettings, SystemManagementPageProps } from "./types";

type FinanceDocumentForm = Omit<FinanceDocumentSettings, "mail_delivery_available">;

export default function FinanceDocumentsSettingsPage({ user, finance_document_settings: settings, access }: SystemManagementPageProps) {
    const form = useForm<FinanceDocumentForm>({
        automatic_receipts_enabled: settings.automatic_receipts_enabled,
        require_paper_or_reference: settings.require_paper_or_reference,
        manual_invoices_enabled: settings.manual_invoices_enabled,
    });

    const submit = () => {
        form.put(updateFinanceDocuments.url(), {
            preserveScroll: true,
            onSuccess: () => toast.success("Finance document settings updated."),
            onError: () => toast.error("Review the settings and try again."),
        });
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="finance_documents"
            heading="Finance Documents"
            description="Control how official student eReceipts and eInvoices are issued and delivered."
        >
            {!settings.mail_delivery_available ? (
                <Alert variant="destructive">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>Email delivery is unavailable</AlertTitle>
                    <AlertDescription>
                        Enable the Email notification channel and configure a sender address before documents can be delivered.
                    </AlertDescription>
                </Alert>
            ) : (
                <Alert>
                    <MailCheck className="size-4" />
                    <AlertTitle>Email delivery is ready</AlertTitle>
                    <AlertDescription>Official finance documents will use the configured application mail provider.</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader className="flex-row items-start justify-between gap-4">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <FileBadge2 className="size-5" />
                            Issuance policy
                        </CardTitle>
                        <CardDescription className="mt-1">
                            Documents are immutable after issuance and include a public QR verification code.
                        </CardDescription>
                    </div>
                    <Button onClick={submit} disabled={form.processing || !access.sections.finance_documents?.can_update}>
                        {form.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                        Save settings
                    </Button>
                </CardHeader>
                <CardContent className="space-y-4">
                    <SettingRow
                        icon={ReceiptText}
                        label="Automatic official eReceipts"
                        description="Queue one eReceipt when a positive transaction reaches paid or completed status."
                        checked={form.data.automatic_receipts_enabled}
                        onChange={(checked) => form.setData("automatic_receipts_enabled", checked)}
                    />
                    <SettingRow
                        icon={FileBadge2}
                        label="Require a paper O.R. reference"
                        description="Hold eReceipt delivery until staff enter the institution's paper Official Receipt number."
                        checked={form.data.require_paper_or_reference}
                        onChange={(checked) => form.setData("require_paper_or_reference", checked)}
                    />
                    <SettingRow
                        icon={MailCheck}
                        label="Manual outstanding-balance eInvoices"
                        description="Allow finance staff to issue an official eInvoice from an unpaid Billing Desk row."
                        checked={form.data.manual_invoices_enabled}
                        onChange={(checked) => form.setData("manual_invoices_enabled", checked)}
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Document contract</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-wrap gap-2 text-sm">
                    <Badge variant="outline">Institution-issued</Badge>
                    <Badge variant="outline">Immutable snapshot</Badge>
                    <Badge variant="outline">Private PDF storage</Badge>
                    <Badge variant="outline">QR verification</Badge>
                    <Badge variant="outline">Audited delivery attempts</Badge>
                </CardContent>
            </Card>
        </SystemManagementLayout>
    );
}

function SettingRow({
    icon: Icon,
    label,
    description,
    checked,
    onChange,
}: {
    icon: typeof ReceiptText;
    label: string;
    description: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-5 rounded-lg border p-4">
            <div className="flex items-start gap-3">
                <div className="bg-primary/10 text-primary rounded-lg p-2">
                    <Icon className="size-5" />
                </div>
                <div>
                    <Label className="text-sm font-semibold">{label}</Label>
                    <p className="text-muted-foreground mt-1 max-w-2xl text-sm">{description}</p>
                </div>
            </div>
            <Switch checked={checked} onCheckedChange={onChange} aria-label={label} />
        </div>
    );
}
