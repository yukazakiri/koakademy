import { updateFinanceDocuments } from "@/actions/App/Http/Controllers/AdministratorSystemManagementController";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/utils";
import { useForm } from "@inertiajs/react";
import {
    AlertTriangle,
    CheckCircle2,
    FileBadge2,
    FileCheck,
    FileText,
    History,
    Loader2,
    Lock,
    MailCheck,
    QrCode,
    ReceiptText,
    Save,
    Shield,
} from "lucide-react";
import { toast } from "sonner";

import SystemManagementLayout from "./layout";
import type { FinanceDocumentSettings, SystemManagementPageProps } from "./types";

type FinanceDocumentForm = Omit<FinanceDocumentSettings, "mail_delivery_available">;

export default function FinanceDocumentsSettingsPage({
    user,
    finance_document_settings: settings,
    access,
}: SystemManagementPageProps) {
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
            description="Control how official student eReceipts and eInvoices are generated, secured, and delivered."
        >
            <div className="space-y-6">
                {!settings.mail_delivery_available ? (
                    <Alert variant="destructive" className="border-destructive/30 bg-destructive/5">
                        <AlertTriangle className="size-4 text-destructive" />
                        <AlertTitle className="text-sm font-semibold">Email delivery is unavailable</AlertTitle>
                        <AlertDescription className="text-xs">
                            Enable the Email notification channel and configure a sender address before documents can be delivered.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Alert className="border-emerald-500/30 bg-emerald-500/5 text-emerald-900 dark:text-emerald-200">
                        <MailCheck className="size-4 text-emerald-600 dark:text-emerald-400" />
                        <AlertTitle className="text-sm font-semibold">Email delivery is active</AlertTitle>
                        <AlertDescription className="text-xs text-muted-foreground">
                            Official finance documents will use the configured application mail provider.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Issuance Policy Card */}
                <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                    <CardHeader className="flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border/40 pb-4">
                        <div className="space-y-0.5">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base font-semibold">Issuance & Delivery Policy</CardTitle>
                                <Badge variant="outline" className="text-[11px] font-normal border-border/60">
                                    Official Records
                                </Badge>
                            </div>
                            <CardDescription className="text-xs">
                                Issued receipts and invoices are cryptographically stamped and include a public QR verification code.
                            </CardDescription>
                        </div>

                        <Button
                            onClick={submit}
                            disabled={form.processing || !access.sections.finance_documents?.can_update}
                            className="h-9 gap-1.5 self-start sm:self-center"
                        >
                            {form.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                            <span>Save Settings</span>
                        </Button>
                    </CardHeader>

                    <CardContent className="pt-4 divide-y divide-border/40">
                        <SettingRow
                            icon={ReceiptText}
                            label="Automatic Official eReceipts"
                            description="Automatically queue and generate an official eReceipt when a positive tuition transaction reaches paid or completed status."
                            checked={form.data.automatic_receipts_enabled}
                            onChange={(checked) => form.setData("automatic_receipts_enabled", checked)}
                        />
                        <SettingRow
                            icon={FileBadge2}
                            label="Require Paper O.R. Reference"
                            description="Hold eReceipt delivery until finance staff record the physical paper Official Receipt booklet reference number."
                            checked={form.data.require_paper_or_reference}
                            onChange={(checked) => form.setData("require_paper_or_reference", checked)}
                        />
                        <SettingRow
                            icon={MailCheck}
                            label="Manual Outstanding Balance eInvoices"
                            description="Allow authorized finance officers to dispatch an official Statement of Account eInvoice directly from unpaid Billing Desk accounts."
                            checked={form.data.manual_invoices_enabled}
                            onChange={(checked) => form.setData("manual_invoices_enabled", checked)}
                        />
                    </CardContent>
                </Card>

                {/* Document Security & Contract Specifications */}
                <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                    <CardHeader className="pb-3 border-b border-border/40">
                        <div className="flex items-center gap-2">
                            <Shield className="size-4 text-primary" />
                            <CardTitle className="text-sm font-semibold">Document Contract & Security Guarantees</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="flex items-start gap-2.5 rounded-lg border border-border/40 bg-background/50 p-3">
                                <FileCheck className="size-4 text-emerald-500 mt-0.5 shrink-0" />
                                <div>
                                    <p className="text-xs font-semibold text-foreground">Immutable PDF Record</p>
                                    <p className="text-[11px] text-muted-foreground mt-0.5">
                                        Once finalized, document contents and totals cannot be altered or overwritten.
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-start gap-2.5 rounded-lg border border-border/40 bg-background/50 p-3">
                                <QrCode className="size-4 text-sky-500 mt-0.5 shrink-0" />
                                <div>
                                    <p className="text-xs font-semibold text-foreground">Public QR Verification</p>
                                    <p className="text-[11px] text-muted-foreground mt-0.5">
                                        Students, banks, and auditors can scan the QR code to verify document authenticity online.
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-start gap-2.5 rounded-lg border border-border/40 bg-background/50 p-3">
                                <History className="size-4 text-violet-500 mt-0.5 shrink-0" />
                                <div>
                                    <p className="text-xs font-semibold text-foreground">Delivery Audit Log</p>
                                    <p className="text-[11px] text-muted-foreground mt-0.5">
                                        Every email delivery attempt, receipt download, and timestamp is tracked.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
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
        <div className="flex items-center justify-between gap-5 py-4 first:pt-2 last:pb-2">
            <div className="flex items-start gap-3.5">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary mt-0.5">
                    <Icon className="size-4" />
                </div>
                <div>
                    <Label className="text-sm font-semibold text-foreground cursor-pointer" onClick={() => onChange(!checked)}>
                        {label}
                    </Label>
                    <p className="text-xs text-muted-foreground mt-0.5 max-w-2xl leading-relaxed">{description}</p>
                </div>
            </div>
            <Switch checked={checked} onCheckedChange={onChange} aria-label={label} />
        </div>
    );
}
