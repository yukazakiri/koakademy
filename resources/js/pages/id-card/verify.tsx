import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { Head, usePage } from "@inertiajs/react";
import { motion } from "framer-motion";
import {
    AlertTriangle,
    Briefcase,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    GraduationCap,
    Mail,
    ShieldAlert,
    ShieldCheck,
    User,
    XCircle,
} from "lucide-react";

interface Branding {
    organizationShortName: string;
    appName: string;
}

interface VerificationData {
    type: "student" | "faculty";
    id: string | number;
    name: string;
    email?: string;
    course?: string;
    year_level?: number;
    department?: string;
    status: string;
    photo_url?: string | null;
}

interface VerifyPageProps {
    valid: boolean;
    data?: VerificationData;
    error?: string;
    issued_at?: string;
    is_stale?: boolean;
    branding?: Branding;
}

export default function VerifyPage({ valid, data, error, issued_at, is_stale }: VerifyPageProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const orgShortName = props.branding?.organizationShortName || "UNI";
    const isStudent = data?.type === "student";
    const TypeIcon = isStudent ? GraduationCap : Briefcase;

    return (
        <>
            <Head title="ID Card Verification" />

            <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
                {/* Background effects */}
                <div className="pointer-events-none fixed inset-0 overflow-hidden">
                    <div className="bg-primary/10 absolute top-0 left-1/4 h-96 w-96 rounded-full blur-3xl" />
                    <div className="absolute right-1/4 bottom-0 h-96 w-96 rounded-full bg-purple-500/10 blur-3xl" />
                </div>

                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.5 }}
                    className="relative z-10 w-full max-w-md"
                >
                    {valid && data ? (
                        <ValidVerification data={data} issued_at={issued_at} is_stale={is_stale} TypeIcon={TypeIcon} isStudent={isStudent} />
                    ) : (
                        <InvalidVerification error={error} />
                    )}

                    {/* Footer */}
                    <div className="mt-6 text-center">
                        <p className="text-xs text-white/40">Verified by {orgShortName} Digital ID System</p>
                    </div>
                </motion.div>
            </div>
        </>
    );
}

function ValidVerification({
    data,
    issued_at,
    is_stale,
    TypeIcon,
    isStudent,
}: {
    data: VerificationData;
    issued_at?: string;
    is_stale?: boolean;
    TypeIcon: typeof GraduationCap | typeof Briefcase;
    isStudent: boolean;
}) {
    const statusLower = data.status?.toLowerCase();
    const isActive = statusLower === "enrolled" || statusLower === "active";

    return (
        <Card className="overflow-hidden border-white/10 bg-slate-800/50 shadow-2xl backdrop-blur-xl">
            {/* Success Header */}
            <div
                className={cn(
                    "p-6 text-center",
                    isActive
                        ? "border-b border-emerald-500/20 bg-gradient-to-r from-emerald-500/20 to-teal-500/20"
                        : "border-b border-amber-500/20 bg-gradient-to-r from-amber-500/20 to-orange-500/20",
                )}
            >
                <motion.div
                    initial={{ scale: 0 }}
                    animate={{ scale: 1 }}
                    transition={{ delay: 0.2, type: "spring", stiffness: 200 }}
                    className={cn(
                        "mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full",
                        isActive ? "bg-emerald-500/20 ring-4 ring-emerald-500/30" : "bg-amber-500/20 ring-4 ring-amber-500/30",
                    )}
                >
                    {isActive ? <CheckCircle2 className="h-10 w-10 text-emerald-400" /> : <AlertTriangle className="h-10 w-10 text-amber-400" />}
                </motion.div>

                <h1 className="mb-1 text-xl font-bold text-white">{isActive ? "Identity Verified" : "Identity Found"}</h1>
                <p className="text-sm text-white/60">{isActive ? "This ID card is valid and active" : `Status: ${data.status}`}</p>
            </div>

            <CardContent className="p-6">
                {/* Photo and Basic Info */}
                <div className="mb-6 flex gap-4">
                    <div className={cn("relative h-24 w-20 shrink-0 overflow-hidden rounded-xl", "border-2 border-white/20 bg-slate-700/50")}>
                        {data.photo_url ? (
                            <img src={data.photo_url} alt={data.name} className="h-full w-full object-cover" />
                        ) : (
                            <div className="flex h-full w-full items-center justify-center">
                                <User className="h-8 w-8 text-white/30" />
                            </div>
                        )}
                    </div>

                    <div className="min-w-0 flex-1">
                        <div className="mb-2 flex items-center gap-2">
                            <Badge
                                className={cn(
                                    "border",
                                    isActive
                                        ? "border-emerald-500/30 bg-emerald-500/20 text-emerald-400"
                                        : "border-amber-500/30 bg-amber-500/20 text-amber-400",
                                )}
                            >
                                {isActive ? <ShieldCheck className="mr-1 h-3 w-3" /> : <ShieldAlert className="mr-1 h-3 w-3" />}
                                {data.status}
                            </Badge>
                            <Badge className="border border-white/10 bg-white/5 text-white/70">
                                <TypeIcon className="mr-1 h-3 w-3" />
                                {isStudent ? "Student" : "Faculty"}
                            </Badge>
                        </div>
                        <h2 className="truncate text-lg font-bold text-white">{data.name}</h2>
                        <p className="font-mono text-sm text-white/60">ID: {data.id}</p>
                    </div>
                </div>

                {/* Details Grid */}
                <div className="space-y-3 rounded-xl border border-white/5 bg-slate-900/50 p-4">
                    {data.email && (
                        <div className="flex items-center gap-3">
                            <Mail className="h-4 w-4 text-white/40" />
                            <div>
                                <p className="text-xs tracking-wider text-white/40 uppercase">Email</p>
                                <p className="text-sm text-white/80">{data.email}</p>
                            </div>
                        </div>
                    )}

                    {isStudent ? (
                        <>
                            <div className="flex items-center gap-3">
                                <GraduationCap className="h-4 w-4 text-white/40" />
                                <div>
                                    <p className="text-xs tracking-wider text-white/40 uppercase">Program</p>
                                    <p className="text-sm text-white/80">{data.course || "N/A"}</p>
                                </div>
                            </div>
                            {data.year_level && (
                                <div className="flex items-center gap-3">
                                    <Calendar className="h-4 w-4 text-white/40" />
                                    <div>
                                        <p className="text-xs tracking-wider text-white/40 uppercase">Year Level</p>
                                        <p className="text-sm text-white/80">{ordinal(data.year_level)} Year</p>
                                    </div>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="flex items-center gap-3">
                            <Building2 className="h-4 w-4 text-white/40" />
                            <div>
                                <p className="text-xs tracking-wider text-white/40 uppercase">Department</p>
                                <p className="text-sm text-white/80">{data.department || "N/A"}</p>
                            </div>
                        </div>
                    )}

                    {issued_at && (
                        <div className="flex items-center gap-3">
                            <Clock className="h-4 w-4 text-white/40" />
                            <div>
                                <p className="text-xs tracking-wider text-white/40 uppercase">QR Issued</p>
                                <p className="text-sm text-white/80">
                                    {issued_at}
                                    {is_stale && <span className="ml-2 text-xs text-amber-400">(Stale - should refresh)</span>}
                                </p>
                            </div>
                        </div>
                    )}
                </div>
            </CardContent>

            {/* Bottom accent */}
            <div
                className={cn(
                    "h-1.5 w-full",
                    isActive
                        ? "bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500"
                        : "bg-gradient-to-r from-amber-500 via-orange-500 to-red-500",
                )}
            />
        </Card>
    );
}

function InvalidVerification({ error }: { error?: string }) {
    return (
        <Card className="overflow-hidden border-white/10 bg-slate-800/50 shadow-2xl backdrop-blur-xl">
            {/* Error Header */}
            <div className="border-b border-red-500/20 bg-gradient-to-r from-red-500/20 to-rose-500/20 p-8 text-center">
                <motion.div
                    initial={{ scale: 0 }}
                    animate={{ scale: 1 }}
                    transition={{ delay: 0.2, type: "spring", stiffness: 200 }}
                    className="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-red-500/20 ring-4 ring-red-500/30"
                >
                    <XCircle className="h-10 w-10 text-red-400" />
                </motion.div>

                <h1 className="mb-2 text-xl font-bold text-white">Verification Failed</h1>
                <p className="text-sm text-white/60">{error || "This ID card could not be verified"}</p>
            </div>

            <CardContent className="p-6">
                <div className="rounded-xl border border-red-500/20 bg-red-500/10 p-4">
                    <div className="flex items-start gap-3">
                        <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-red-400" />
                        <div>
                            <h3 className="mb-1 font-semibold text-red-400">Invalid QR Code</h3>
                            <p className="text-sm text-white/60">
                                The scanned QR code is either expired, tampered with, or does not belong to a valid ID card. Please ask the cardholder
                                to refresh their QR code or contact the registrar's office.
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>

            {/* Bottom accent */}
            <div className="h-1.5 w-full bg-gradient-to-r from-red-500 via-rose-500 to-pink-500" />
        </Card>
    );
}

function ordinal(n: number): string {
    const s = ["th", "st", "nd", "rd"];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
}
