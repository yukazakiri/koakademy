import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Check, Copy, Info, Mail, ServerCog, ShieldCheck, Terminal } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

export default function SystemManagementMailPage({ user, mail_config, access }: SystemManagementPageProps) {
    const isLogTransport = mail_config.delivery_mode === "log";
    const [copied, setCopied] = useState(false);
    const commandText = `koakademy configure mail ${isLogTransport ? "smtp" : "log"}`;

    const copyCommand = () => {
        navigator.clipboard.writeText(commandText);
        setCopied(true);
        toast.success("CLI command copied to clipboard.");
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="mail"
            heading="Email Delivery"
            description="Review runtime mail transport configuration. Production credentials are managed by deployment orchestration."
        >
            <div className="space-y-6">
                {/* Main Delivery Status Card */}
                <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                    <CardHeader className="pb-4 border-b border-border/40">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div className="flex items-start gap-3">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <ServerCog className="size-5" />
                                </div>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <CardTitle className="text-base font-semibold">Deployment-managed transport</CardTitle>
                                        <Badge
                                            variant="outline"
                                            className={
                                                isLogTransport
                                                    ? "border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300 text-xs"
                                                    : "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-xs"
                                            }
                                        >
                                            {isLogTransport ? "Log Driver" : "Active Mailer"}
                                        </Badge>
                                    </div>
                                    <CardDescription className="text-xs mt-0.5">
                                        SMTP and API provider credentials are encrypted as Docker secrets on the host server.
                                    </CardDescription>
                                </div>
                            </div>

                            <div className="flex items-center gap-1.5 self-start sm:self-center">
                                <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground font-mono bg-muted/50 px-2.5 py-1 rounded-lg border border-border/50">
                                    <ShieldCheck className="size-3.5 text-emerald-500" />
                                    Zero-trust storage
                                </span>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="space-y-6 pt-5">
                        {/* Parameters Grid */}
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="rounded-xl border border-border/50 bg-background/60 p-3.5">
                                <dt className="text-xs font-medium text-muted-foreground">Active Mail Driver</dt>
                                <dd className="mt-1 text-sm font-semibold capitalize tracking-tight text-foreground flex items-center gap-1.5">
                                    <span className="size-2 rounded-full bg-emerald-500" />
                                    {mail_config.driver}
                                </dd>
                            </div>

                            <div className="rounded-xl border border-border/50 bg-background/60 p-3.5">
                                <dt className="text-xs font-medium text-muted-foreground">System From Address</dt>
                                <dd className="mt-1 text-sm font-semibold font-mono text-foreground truncate">
                                    {mail_config.email_from_address}
                                </dd>
                            </div>

                            <div className="rounded-xl border border-border/50 bg-background/60 p-3.5">
                                <dt className="text-xs font-medium text-muted-foreground">Sender Display Name</dt>
                                <dd className="mt-1 text-sm font-semibold text-foreground truncate">
                                    {mail_config.email_from_name}
                                </dd>
                            </div>
                        </div>

                        {/* CLI Host Management Helper */}
                        <div className="rounded-xl border border-border/60 bg-muted/25 p-4 sm:p-5">
                            <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                <div className="space-y-1.5">
                                    <div className="flex items-center gap-2">
                                        <Terminal className="size-4 text-primary" />
                                        <span className="text-xs font-semibold uppercase tracking-wider text-foreground">
                                            Host Configuration Command
                                        </span>
                                    </div>
                                    <p className="text-xs text-muted-foreground max-w-2xl leading-relaxed">
                                        To update SMTP credentials or switch between live provider and local log mode, run the official KoAkademy
                                        CLI tool directly on the server host:
                                    </p>
                                </div>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={copyCommand}
                                    className="h-8 gap-1.5 text-xs self-start sm:self-auto bg-background/80"
                                >
                                    {copied ? <Check className="size-3.5 text-emerald-500" /> : <Copy className="size-3.5" />}
                                    <span>{copied ? "Copied" : "Copy Command"}</span>
                                </Button>
                            </div>

                            <div className="mt-3 flex items-center justify-between rounded-lg border border-border/60 bg-background px-3.5 py-2 font-mono text-xs">
                                <span className="text-muted-foreground select-all">{commandText}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SystemManagementLayout>
    );
}
