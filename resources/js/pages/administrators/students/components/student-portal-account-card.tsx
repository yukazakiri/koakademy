import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import axios from "axios";
import { CheckCircle2, KeyRound, Link2, Loader2, Mail, ShieldAlert, UserCheck, UserPlus } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

export interface StudentPortalAccountSummary {
    id: number;
    name?: string;
    email: string;
    is_linked?: boolean;
    email_verified?: boolean;
    suspended?: boolean;
}

interface StudentPortalAccountCardProps {
    studentId: number;
    studentEmail: string | null | undefined;
    portalAccount: StudentPortalAccountSummary | null | undefined;
    compact?: boolean;
    onUpdated?: (account: StudentPortalAccountSummary) => void;
}

export function StudentPortalAccountCard({
    studentId,
    studentEmail,
    portalAccount,
    compact = false,
    onUpdated,
}: StudentPortalAccountCardProps) {
    const [account, setAccount] = useState<StudentPortalAccountSummary | null>(portalAccount ?? null);
    const [sendResetLink, setSendResetLink] = useState(true);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        setAccount(portalAccount ?? null);
    }, [portalAccount]);

    const hasEmail = Boolean(studentEmail && studentEmail.trim() !== "");
    const isLinked = Boolean(account && (account.is_linked ?? true));

    const handleProvision = async (mode: "create" | "repair" | "auto") => {
        if (!hasEmail || submitting) {
            return;
        }

        setSubmitting(true);
        try {
            const response = await axios.post(
                `/administrators/students/${studentId}/portal-account`,
                {
                    mode,
                    send_reset_link: sendResetLink,
                },
                {
                    headers: { Accept: "application/json" },
                },
            );

            const nextAccount = response.data?.portal_account as StudentPortalAccountSummary | undefined;
            if (nextAccount) {
                setAccount(nextAccount);
                onUpdated?.(nextAccount);
            }
            toast.success(response.data?.message || "Student portal account is ready.");
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Failed to provision student portal account.");
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Card className="border-border/70 shadow-2xs">
            <CardHeader className={compact ? "p-4 pb-2" : "pb-3"}>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                        <KeyRound className="text-primary size-4" />
                        Portal Account
                    </CardTitle>
                    {account ? (
                        account.suspended ? (
                            <Badge variant="destructive" className="text-[11px]">
                                Suspended
                            </Badge>
                        ) : isLinked ? (
                            <Badge variant="secondary" className="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[11px]">
                                <CheckCircle2 className="mr-1 size-3" />
                                Linked
                            </Badge>
                        ) : (
                            <Badge variant="outline" className="border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300 text-[11px]">
                                Unlinked User Found
                            </Badge>
                        )
                    ) : (
                        <Badge variant="outline" className="text-muted-foreground text-[11px]">
                            Not Provisioned
                        </Badge>
                    )}
                </div>
                {!compact && (
                    <CardDescription className="text-xs">
                        Manage the modern <code className="font-mono text-[11px]">User</code> login bound to this student record.
                    </CardDescription>
                )}
            </CardHeader>

            <CardContent className={compact ? "space-y-3 p-4 pt-2" : "space-y-3"}>
                {!hasEmail ? (
                    <div className="border-amber-500/30 bg-amber-500/5 flex items-start gap-2 rounded-lg border p-3 text-xs text-amber-800 dark:text-amber-200">
                        <ShieldAlert className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <span>Add an institutional email address to this student before creating or linking a portal account.</span>
                    </div>
                ) : (
                    <>
                        <div className="bg-muted/40 border-border/60 flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs">
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Login Email</p>
                                <p className="text-foreground truncate font-mono text-xs">{account?.email || studentEmail}</p>
                            </div>
                            {account && (
                                <Badge variant="outline" className="shrink-0 font-mono text-[10px] tabular-nums">
                                    UID #{account.id}
                                </Badge>
                            )}
                        </div>

                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id={`portal-send-reset-${studentId}`}
                                checked={sendResetLink}
                                onCheckedChange={(checked) => setSendResetLink(Boolean(checked))}
                                disabled={submitting}
                            />
                            <Label htmlFor={`portal-send-reset-${studentId}`} className="text-muted-foreground cursor-pointer text-xs font-normal">
                                Email password-setup / activation link
                            </Label>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {!account ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => void handleProvision("create")}
                                    disabled={submitting}
                                    className="h-8 gap-1.5 text-xs active:scale-[0.97]"
                                >
                                    {submitting ? <Loader2 className="size-3.5 animate-spin" /> : <UserPlus className="size-3.5" />}
                                    Provision Portal Account
                                </Button>
                            ) : !isLinked ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => void handleProvision("repair")}
                                    disabled={submitting}
                                    className="h-8 gap-1.5 text-xs active:scale-[0.97]"
                                >
                                    {submitting ? <Loader2 className="size-3.5 animate-spin" /> : <Link2 className="size-3.5" />}
                                    Link &amp; Sync Role
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => void handleProvision("repair")}
                                    disabled={submitting}
                                    className="h-8 gap-1.5 text-xs active:scale-[0.97]"
                                >
                                    {submitting ? (
                                        <Loader2 className="size-3.5 animate-spin" />
                                    ) : sendResetLink ? (
                                        <Mail className="size-3.5" />
                                    ) : (
                                        <UserCheck className="size-3.5" />
                                    )}
                                    {sendResetLink ? "Resync & Send Reset Link" : "Resync Role & Org"}
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
