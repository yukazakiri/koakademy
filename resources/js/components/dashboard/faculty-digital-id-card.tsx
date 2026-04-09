import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Dialog, DialogContent, DialogTitle } from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { usePage } from "@inertiajs/react";
import {
    IconBuilding,
    IconCalendar,
    IconCheck,
    IconCopy,
    IconFingerprint,
    IconQrcode,
    IconRefresh,
    IconSchool,
    IconWifi,
    IconX,
} from "@tabler/icons-react";
import { AnimatePresence, motion } from "framer-motion";
import * as React from "react";
import { toast } from "sonner";

interface FacultyIdCardData {
    id: string | number;
    faculty_id_number?: string;
    employee_id?: string;
    name: string;
    email?: string;
    department?: string;
    position?: string;
    role?: string;
    phone_number?: string;
    specialization?: string;
    status?: string;
    valid_until?: string;
    issued_at?: string;
}

interface FacultyDigitalIdCardProps {
    cardData: FacultyIdCardData;
    photoUrl?: string | null;
    qrCode: string;
    isValid: boolean;
    isCompact?: boolean;
    onRefresh?: () => void;
    onExpand?: () => void;
    isRefreshing?: boolean;
    className?: string;
}

interface Branding {
    organizationShortName: string;
    appName: string;
}

export function FacultyDigitalIdCard({
    cardData,
    photoUrl,
    qrCode,
    isValid,
    isCompact = false,
    onRefresh,
    onExpand,
    isRefreshing = false,
    className,
}: FacultyDigitalIdCardProps) {
    const [isOpen, setIsOpen] = React.useState(false);
    const [isFlipped, setIsFlipped] = React.useState(false);
    const { props } = usePage<{ branding?: Branding }>();
    const orgName = props.branding?.organizationShortName || "UNI";

    return (
        <TooltipProvider>
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.3 }}
                className={cn("perspective-1000", className)}
            >
                <motion.div
                    animate={{ rotateY: isFlipped ? 180 : 0 }}
                    transition={{ duration: 0.6, type: "spring", stiffness: 200 }}
                    className="preserve-3d relative w-full cursor-pointer"
                    style={{ transformStyle: "preserve-3d" }}
                >
                    <Card
                        className={cn(
                            "relative overflow-hidden rounded-2xl border-0 shadow-2xl",
                            "from-primary via-primary to-primary/90 bg-gradient-to-br",
                            "text-primary-foreground",
                            "min-h-[200px]",
                        )}
                        onClick={() => setIsOpen(true)}
                    >
                        <div className="pointer-events-none absolute inset-0 bg-gradient-to-br from-white/10 via-transparent to-transparent" />
                        <div
                            className="pointer-events-none absolute inset-0 opacity-20"
                            style={{
                                backgroundImage: "radial-gradient(circle, rgba(255,255,255,0.8) 1px, transparent 1px)",
                                backgroundSize: "24px 24px",
                            }}
                        />
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(255,255,255,0.15),transparent_50%)]" />

                        <div className="relative flex h-full min-h-[220px] flex-col justify-between p-6">
                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/20 shadow-lg backdrop-blur-sm">
                                        <IconSchool className="h-6 w-6 text-current" />
                                    </div>
                                    <div>
                                        <p className="text-base leading-none font-bold tracking-wide">{orgName}</p>
                                        <p className="mt-1 text-[10px] font-medium tracking-[0.2em] uppercase opacity-70">Faculty Card</p>
                                    </div>
                                </div>

                                <IconWifi className="h-5 w-5 rotate-90 opacity-40" />
                            </div>

                            <div className="mt-2 flex items-end justify-between gap-4">
                                <div className="flex items-center gap-4">
                                    <div className="relative shrink-0">
                                        <Avatar className="h-16 w-16 border-2 border-white/20 shadow-lg ring-2 ring-white/10">
                                            <AvatarImage src={photoUrl ?? undefined} alt={cardData.name} className="object-cover" />
                                            <AvatarFallback className="bg-white/20 text-lg font-bold text-current backdrop-blur-md">
                                                {cardData.name.slice(0, 2).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div
                                            className={cn(
                                                "border-background absolute -right-1 -bottom-1 flex h-5 w-5 items-center justify-center rounded-full border-2 shadow-sm",
                                                isValid ? "bg-emerald-500" : "bg-rose-500",
                                            )}
                                        >
                                            <IconCheck className="h-3 w-3 stroke-[3] text-white" />
                                        </div>
                                    </div>
                                    <div className="min-w-0">
                                        <p className="truncate pr-2 text-xl leading-tight font-bold">{cardData.name}</p>
                                        <p className="truncate text-sm font-medium opacity-80">
                                            {cardData.position ?? cardData.role ?? "Faculty Member"}
                                        </p>
                                        {cardData.role && cardData.role !== cardData.position && (
                                            <Badge variant="outline" className="mt-1 h-5 border-current/20 px-1.5 py-0 text-[10px]">
                                                {cardData.role}
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="mt-2 space-y-4">
                                <div>
                                    <p className="mb-0.5 text-[10px] font-semibold tracking-wider uppercase opacity-60">Faculty ID Number</p>
                                    <div className="flex items-center gap-3">
                                        <p className="font-mono text-2xl font-bold tracking-widest drop-shadow-sm">
                                            {cardData.faculty_id_number || cardData.employee_id || cardData.id || "No ID"}
                                        </p>
                                        <Badge
                                            className={cn(
                                                "border-0 px-2 py-0.5 text-[10px] shadow-sm",
                                                isValid ? "bg-emerald-500 text-white" : "bg-rose-500 text-white",
                                            )}
                                        >
                                            {isValid ? "ACTIVE" : "INACTIVE"}
                                        </Badge>
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4 border-t border-current/10 pt-3 opacity-90">
                                    <div>
                                        <p className="text-[9px] font-semibold tracking-wider uppercase opacity-60">Department</p>
                                        <p className="mt-0.5 flex items-center gap-1.5 truncate text-xs font-semibold">
                                            <IconBuilding className="h-3 w-3 shrink-0 opacity-60" />
                                            <span className="truncate">{cardData.department ?? "N/A"}</span>
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-[9px] font-semibold tracking-wider uppercase opacity-60">Valid Thru</p>
                                        <p className="mt-0.5 flex items-center justify-end gap-1.5 text-xs font-semibold">
                                            <IconCalendar className="h-3 w-3 shrink-0 opacity-60" />
                                            {cardData.valid_until ?? "Current Sem"}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="pointer-events-none absolute right-0 bottom-0 left-0 h-16 bg-gradient-to-t from-black/10 to-transparent" />

                        <div className="absolute right-5 bottom-5">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button
                                        className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-current shadow-lg backdrop-blur-md transition-all hover:scale-105 hover:bg-white/20 active:scale-95"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setIsOpen(true);
                                        }}
                                    >
                                        <IconQrcode className="h-5 w-5" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent side="top">
                                    <p>View QR Code</p>
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    </Card>
                </motion.div>
            </motion.div>

            <AnimatePresence>
                {isOpen && (
                    <Dialog open={isOpen} onOpenChange={setIsOpen}>
                        <DialogContent className="max-w-sm gap-0 overflow-hidden border-0 bg-transparent p-0 shadow-none">
                            <DialogTitle className="sr-only">Faculty Card QR Verification</DialogTitle>

                            <motion.div
                                initial={{ opacity: 0, scale: 0.9, y: 30 }}
                                animate={{ opacity: 1, scale: 1, y: 0 }}
                                exit={{ opacity: 0, scale: 0.9, y: 30 }}
                                transition={{ type: "spring", stiffness: 300, damping: 25 }}
                            >
                                <Card className="mx-auto max-w-[380px] overflow-hidden rounded-3xl border-0 bg-white shadow-2xl dark:bg-zinc-900">
                                    <div className="relative overflow-hidden bg-neutral-900 p-6 text-white">
                                        <div className="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/5 to-transparent" />

                                        <button
                                            onClick={() => setIsOpen(false)}
                                            className="absolute top-4 right-4 flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-md transition-all hover:bg-white/20 active:scale-95"
                                        >
                                            <IconX className="h-4 w-4" />
                                        </button>

                                        <div className="flex flex-col items-center pt-2 text-center">
                                            <div className="relative mb-4">
                                                <div className="h-20 w-20 overflow-hidden rounded-full border-4 border-white/20 shadow-xl ring-1 ring-black/20">
                                                    <Avatar className="h-full w-full bg-neutral-800">
                                                        <AvatarImage src={photoUrl ?? undefined} alt={cardData.name} className="object-cover" />
                                                        <AvatarFallback className="bg-neutral-800 text-xl font-bold text-white">
                                                            {cardData.name.slice(0, 2).toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                </div>
                                                <div
                                                    className={cn(
                                                        "absolute -right-1 -bottom-1 flex h-6 w-6 items-center justify-center rounded-full border-2 border-neutral-900 shadow-md",
                                                        isValid ? "bg-emerald-500" : "bg-rose-500",
                                                    )}
                                                >
                                                    <IconCheck className="h-3 w-3 stroke-[3] text-white" />
                                                </div>
                                            </div>

                                            <h3 className="mb-1 text-xl leading-tight font-bold">{cardData.name}</h3>
                                            <p className="mb-4 text-sm font-medium text-white/70">
                                                {cardData.position ?? cardData.role ?? "Faculty Member"}
                                            </p>

                                            <div className="flex items-center gap-2 rounded-full bg-white/10 px-1 py-1 pr-3 backdrop-blur-sm">
                                                <Badge
                                                    variant="secondary"
                                                    className="h-6 bg-white px-2 font-mono tracking-wider text-neutral-900 shadow-sm"
                                                >
                                                    {cardData.faculty_id_number || cardData.employee_id || cardData.id || "No ID"}
                                                </Badge>
                                                <span
                                                    className={cn(
                                                        "text-[10px] font-bold tracking-wider uppercase",
                                                        isValid ? "text-emerald-400" : "text-rose-400",
                                                    )}
                                                >
                                                    {isValid ? "Active" : "Inactive"}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="bg-white p-6 dark:bg-zinc-950">
                                        <div className="flex flex-col items-center">
                                            <div className="mb-6 w-full text-center">
                                                <div className="inline-block rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                                                    <div className="h-64 w-64 overflow-hidden rounded-xl bg-white">
                                                        <img
                                                            src={qrCode}
                                                            alt="QR Code"
                                                            className="h-full w-full object-contain"
                                                            style={{ imageRendering: "pixelated" }}
                                                        />
                                                    </div>
                                                </div>
                                                <p className="text-muted-foreground mt-3 flex items-center justify-center gap-1.5 text-[10px] font-semibold tracking-widest uppercase">
                                                    <IconFingerprint className="h-3.5 w-3.5" />
                                                    Official Verification
                                                </p>
                                            </div>

                                            <div className="w-full space-y-3">
                                                <div className="grid grid-cols-2 gap-3">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-10 gap-1.5 text-xs"
                                                        onClick={() => {
                                                            navigator.clipboard.writeText(String(cardData.faculty_id_number || cardData.id));
                                                            toast.success("Faculty ID copied!");
                                                        }}
                                                    >
                                                        <IconCopy className="h-3.5 w-3.5" />
                                                        Copy ID
                                                    </Button>

                                                    {onRefresh && (
                                                        <Button
                                                            size="sm"
                                                            className="h-10 gap-1.5 text-xs"
                                                            onClick={onRefresh}
                                                            disabled={isRefreshing}
                                                        >
                                                            <IconRefresh className={cn("h-3.5 w-3.5", isRefreshing && "animate-spin")} />
                                                            {isRefreshing ? "Refresh" : "Refresh"}
                                                        </Button>
                                                    )}
                                                </div>

                                                <div className="border-t border-slate-100 pt-2 text-center dark:border-slate-800">
                                                    <span className="text-muted-foreground text-[10px]">
                                                        Valid until {cardData.valid_until ?? "End of Academic Year"}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </Card>
                            </motion.div>
                        </DialogContent>
                    </Dialog>
                )}
            </AnimatePresence>

            <style>{`
                .perspective-1000 {
                    perspective: 1000px;
                }
                .preserve-3d {
                    transform-style: preserve-3d;
                }
            `}</style>
        </TooltipProvider>
    );
}

function formatCardNumber(id: string): string {
    const cleaned = id.replace(/\D/g, "").padStart(8, "0");
    const parts = cleaned.match(/.{1,4}/g) || [cleaned];
    return parts.slice(0, 2).join("  ");
}

export function FacultyIdCardSkeleton() {
    return (
        <Card className="from-primary via-primary to-primary/90 relative min-h-[220px] overflow-hidden rounded-2xl border-0 bg-gradient-to-br shadow-2xl">
            <div className="flex h-full flex-col justify-between p-6">
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        <Skeleton className="h-11 w-11 rounded-xl bg-white/10" />
                        <div>
                            <Skeleton className="mb-1 h-4 w-24 bg-white/10" />
                            <Skeleton className="h-2 w-16 bg-white/10" />
                        </div>
                    </div>
                </div>

                <div className="mt-6 flex items-end justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Skeleton className="h-16 w-16 rounded-full bg-white/10" />
                        <div>
                            <Skeleton className="mb-1 h-6 w-32 bg-white/10" />
                            <Skeleton className="h-4 w-24 bg-white/10" />
                        </div>
                    </div>
                </div>

                <div className="mt-6 space-y-4">
                    <Skeleton className="h-8 w-48 bg-white/10" />
                    <div className="grid grid-cols-2 gap-4 border-t border-white/10 pt-3">
                        <Skeleton className="h-8 w-24 bg-white/10" />
                        <div className="flex justify-end">
                            <Skeleton className="h-8 w-24 bg-white/10" />
                        </div>
                    </div>
                </div>
            </div>
        </Card>
    );
}
