import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { payments } from "@/routes/administrators/finance";
import type { User } from "@/types/user";
import { Head, Link, usePage } from "@inertiajs/react";
import { ArrowLeft, Grid2X2, ReceiptText, Settings2 } from "lucide-react";

import { GuidedPaymentWorkspace } from "./guided-payment-workspace";
import {
    defaultPaymentWorkspace,
    type FeeOption,
    type InventoryItem,
    type PaymentMethodOption,
    type PaymentWorkspacePreference,
} from "./payment-workspace-types";
import { SpreadsheetPaymentWorkspace } from "./spreadsheet-payment-workspace";

type CreatePaymentProps = {
    user: User;
    items: InventoryItem[];
    fee_options: FeeOption[];
    currency: string;
    payment_workspace?: PaymentWorkspacePreference;
    payment_methods?: PaymentMethodOption[];
    ledger_resolve_url: string;
    batch_payment_url: string;
};

type Branding = { currency: string };

export default function CreatePaymentPage({
    user,
    items,
    fee_options,
    currency: propCurrency,
    payment_workspace,
    payment_methods = [],
    ledger_resolve_url,
    batch_payment_url,
}: CreatePaymentProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const workspace = payment_workspace ?? defaultPaymentWorkspace;
    const currency = props.branding?.currency || propCurrency || "PHP";
    const paymentMethods = payment_methods.length > 0 ? payment_methods : [{ value: "Cash", label: "Cash" }];
    const isSpreadsheet = workspace.layout === "spreadsheet";

    return (
        <AdminLayout user={user} title="Receive Payment">
            <Head title="Finance · Receive Payment" />
            <div
                className={cn(
                    "mx-auto w-full max-w-[1500px] min-w-0 antialiased",
                    workspace.density === "compact" ? "[&_.workspace-density-row]:py-2" : "[&_.workspace-density-row]:py-4",
                )}
            >
                <header className="border-border/70 mb-4 flex min-w-0 flex-col gap-4 border-b pb-5 @lg/main:flex-row @lg/main:items-end @lg/main:justify-between">
                    <div className="space-y-1.5">
                        <div className="text-muted-foreground flex items-center gap-2 text-xs font-semibold tracking-[0.16em] uppercase">
                            <span className="size-2 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.12)]" />
                            Finance workspace
                        </div>
                        <h1 className="text-foreground text-2xl font-bold tracking-tight sm:text-3xl">
                            {isSpreadsheet ? "Payment ledger" : "Receive a student payment"}
                        </h1>
                        <p className="text-muted-foreground max-w-2xl text-sm">
                            {isSpreadsheet
                                ? "Paste and validate independent payments, then record every safe row with its own receipt."
                                : "Review a student’s balances, collect payment, and reconcile the receipt in one focused desk."}
                        </p>
                    </div>
                    <div className="grid w-full min-w-0 grid-cols-1 gap-2 @lg/main:w-auto @lg/main:grid-cols-3">
                        <Badge variant="secondary" className="h-10 gap-2 px-3 font-medium">
                            {isSpreadsheet ? <Grid2X2 className="size-4" /> : <ReceiptText className="size-4" />}
                            {isSpreadsheet ? "Spreadsheet desk" : "Guided desk"}
                        </Badge>
                        <Button variant="outline" asChild className="min-h-10">
                            <Link href="/administrators/settings#personalization">
                                <Settings2 className="mr-2 size-4" />
                                Workspace settings
                            </Link>
                        </Button>
                        <Button variant="outline" asChild className="min-h-10">
                            <Link href={payments.url()} prefetch>
                                <ArrowLeft className="mr-2 size-4" />
                                Payment history
                            </Link>
                        </Button>
                    </div>
                </header>

                {isSpreadsheet ? (
                    <SpreadsheetPaymentWorkspace
                        batchUrl={batch_payment_url}
                        currency={currency}
                        defaultPaymentMethod={workspace.default_payment_method}
                        feeOptions={fee_options}
                        inventoryItems={items}
                        paymentMethods={paymentMethods}
                        resolveUrl={ledger_resolve_url}
                    />
                ) : (
                    <GuidedPaymentWorkspace currency={currency} inventoryItems={items} paymentMethods={paymentMethods} preference={workspace} />
                )}
            </div>
        </AdminLayout>
    );
}
