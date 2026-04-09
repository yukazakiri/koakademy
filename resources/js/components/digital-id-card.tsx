import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogClose, DialogContent, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { cn } from "@/lib/utils";
import { usePage } from "@inertiajs/react";
import { motion } from "framer-motion";
import { CheckCircle2, RefreshCw, Scan, School, User, X, XCircle } from "lucide-react";
import * as React from "react";

interface Branding {
    organizationShortName: string;
}

export interface IdCardData {
    type: "student" | "faculty";
    id: string | number;
    name: string;
    email?: string;
    course?: string;
    department?: string;
    status: string;
    school_year?: string;
    year_level?: number;
}

export interface DigitalIdCardProps {
    cardData: IdCardData;
    photoUrl?: string | null;
    qrCode: string;
    isValid: boolean;
    isCompact?: boolean;
    onRefresh?: () => void;
    onExpand?: () => void;
    isRefreshing?: boolean;
    className?: string;
}

export function DigitalIdCard({
    cardData,
    photoUrl,
    qrCode,
    isValid,
    isCompact = false,
    onRefresh,
    isRefreshing = false,
    className,
}: DigitalIdCardProps) {
    const [isOpen, setIsOpen] = React.useState(false);

    if (isCompact) {
        return (
            <CompactSmartCard
                cardData={cardData}
                photoUrl={photoUrl}
                qrCode={qrCode}
                isValid={isValid}
                onRefresh={onRefresh}
                isRefreshing={isRefreshing}
                className={className}
                setIsOpen={setIsOpen}
                isOpen={isOpen}
            />
        );
    }

    return (
        <CompactSmartCard
            cardData={cardData}
            photoUrl={photoUrl}
            qrCode={qrCode}
            isValid={isValid}
            onRefresh={onRefresh}
            isRefreshing={isRefreshing}
            className={cn("mx-auto w-full max-w-md transform transition-transform duration-300 hover:scale-[1.01]", className)}
            setIsOpen={setIsOpen}
            isOpen={isOpen}
            isFullSize={true}
        />
    );
}

function CompactSmartCard({ cardData, photoUrl, qrCode, isValid, onRefresh, isRefreshing, className, setIsOpen, isOpen, isFullSize = false }: any) {
    const isStudent = cardData.type === "student";
    const { props } = usePage<{ branding?: Branding }>();
    const orgShortName = props.branding?.organizationShortName || "UNI";

    return (
        <>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogTrigger asChild>
                    <div
                        className={cn(
                            "group border-border relative cursor-pointer overflow-hidden rounded-3xl border shadow-sm select-none",
                            "bg-card text-card-foreground", // Strictly using ShadCN theme tokens
                            className,
                        )}
                        onClick={() => setIsOpen(true)}
                    >
                        {/* Subtle Primary Gradient Overlay for Branding */}
                        <div className="from-primary/10 to-primary/5 pointer-events-none absolute inset-0 bg-gradient-to-br via-transparent opacity-50" />

                        {/* Hover Highlight */}
                        <div className="bg-primary/5 pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100" />

                        {/* Card Content */}
                        <div className="relative flex h-full min-h-[220px] flex-col justify-between p-6">
                            {/* Header: Chip & Organization */}
                            <div className="mb-2 flex items-start justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="bg-primary/10 border-primary/20 flex h-10 w-10 items-center justify-center rounded-xl border">
                                        <School className="text-primary h-5 w-5" />
                                    </div>
                                    <div className="flex flex-col">
                                        <span className="text-lg leading-none font-bold tracking-tight">{orgShortName}</span>
                                        <span className="text-muted-foreground mt-0.5 text-[10px] font-medium tracking-widest uppercase">
                                            Official ID
                                        </span>
                                    </div>
                                </div>
                                {/* Decorative "Chip" */}
                                <div className="relative h-8 w-11 overflow-hidden rounded-md border border-amber-300/50 bg-gradient-to-br from-amber-200 to-amber-400 opacity-90 shadow-sm">
                                    <div className="absolute inset-0 rounded-md border-[0.5px] border-black/10" />
                                    <div className="absolute top-1/2 left-0 h-[0.5px] w-full bg-black/10" />
                                    <div className="absolute top-0 left-1/2 h-full w-[0.5px] bg-black/10" />
                                </div>
                            </div>

                            {/* Middle: Identity Info */}
                            <div className="my-4 flex items-center gap-5">
                                <div className="relative shrink-0">
                                    <div className="border-background ring-primary/10 bg-muted h-20 w-20 overflow-hidden rounded-2xl border-2 shadow-md ring-2">
                                        {photoUrl ? (
                                            <img src={photoUrl} alt={cardData.name} className="h-full w-full object-cover" />
                                        ) : (
                                            <div className="flex h-full w-full items-center justify-center">
                                                <User className="text-muted-foreground h-8 w-8" />
                                            </div>
                                        )}
                                    </div>
                                    <div
                                        className={cn(
                                            "border-card absolute -right-1.5 -bottom-1.5 flex h-7 w-7 items-center justify-center rounded-full border-[2.5px] shadow-md",
                                            isValid ? "bg-primary text-primary-foreground" : "bg-destructive text-destructive-foreground",
                                        )}
                                    >
                                        {isValid ? <CheckCircle2 className="h-3.5 w-3.5" /> : <XCircle className="h-3.5 w-3.5" />}
                                    </div>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <h2 className="truncate text-xl leading-tight font-bold tracking-tight">{cardData.name}</h2>
                                    <p className="text-muted-foreground mb-2 truncate text-sm font-medium">
                                        {isStudent ? cardData.course : cardData.department}
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant="secondary"
                                            className="bg-secondary/50 hover:bg-secondary/70 h-6 border-0 px-2 py-0.5 font-mono text-xs"
                                        >
                                            {cardData.id}
                                        </Badge>
                                        <Badge variant="outline" className="border-primary/20 text-primary bg-primary/5 h-6 px-2 text-[10px]">
                                            {isStudent ? "STUDENT" : "FACULTY"}
                                        </Badge>
                                    </div>
                                </div>
                            </div>

                            {/* Bottom: Action Footer */}
                            <div className="border-border/60 mt-auto flex items-center justify-between border-t pt-4">
                                <div className="flex flex-col">
                                    <span className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">Status</span>
                                    <div className="flex items-center gap-1.5">
                                        <span className={cn("h-2 w-2 animate-pulse rounded-full", isValid ? "bg-green-500" : "bg-red-500")} />
                                        <span className="text-xs font-bold">{isValid ? "Active" : "Inactive"}</span>
                                    </div>
                                </div>
                                <div className="bg-primary text-primary-foreground group-hover:bg-primary/90 flex items-center gap-2 rounded-full py-1.5 pr-2 pl-3 shadow-sm transition-colors">
                                    <span className="text-xs font-semibold">Tap to Scan</span>
                                    <Scan className="h-3.5 w-3.5" />
                                </div>
                            </div>
                        </div>
                    </div>
                </DialogTrigger>

                {/* Full Screen Overlay */}
                <DialogContent className="bg-background/95 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 m-0 flex h-full w-full max-w-full flex-col items-center justify-center border-0 p-0 backdrop-blur-xl">
                    <DialogTitle className="sr-only">Full Screen ID</DialogTitle>

                    {/* Close Button */}
                    <DialogClose className="bg-muted text-muted-foreground hover:bg-muted/80 absolute top-6 right-6 rounded-full p-2 transition-colors">
                        <X className="h-6 w-6" />
                    </DialogClose>

                    <motion.div
                        initial={{ scale: 0.95, opacity: 0 }}
                        animate={{ scale: 1, opacity: 1 }}
                        transition={{ type: "spring", duration: 0.5 }}
                        className="flex w-full max-w-lg flex-col items-center gap-8 px-6"
                    >
                        {/* Header Info */}
                        <div className="space-y-2 text-center">
                            <div className="border-background ring-border mx-auto mb-4 h-20 w-20 overflow-hidden rounded-full border-4 shadow-lg ring-2">
                                {photoUrl ? (
                                    <img src={photoUrl} alt={cardData.name} className="h-full w-full object-cover" />
                                ) : (
                                    <div className="bg-muted flex h-full w-full items-center justify-center">
                                        <User className="text-muted-foreground h-10 w-10" />
                                    </div>
                                )}
                            </div>
                            <h2 className="text-foreground text-3xl font-bold tracking-tight">{cardData.name}</h2>
                            <Badge variant="outline" className="border-primary/30 text-primary bg-primary/5 h-9 px-4 py-1 text-lg">
                                {isStudent ? "Student Pass" : "Faculty Pass"}
                            </Badge>
                        </div>

                        {/* High Contrast QR Container */}
                        <div className="relative flex aspect-square w-full max-w-[min(85vw,500px)] items-center justify-center rounded-[2rem] bg-white p-6 shadow-2xl ring-1 ring-black/5">
                            <img src={qrCode} alt="Access QR Code" className="h-full w-full scale-110 object-contain mix-blend-multiply" />
                            {/* Corner Accents */}
                            <div className="absolute top-5 left-5 h-12 w-12 rounded-tl-xl border-t-[5px] border-l-[5px] border-black opacity-10" />
                            <div className="absolute top-5 right-5 h-12 w-12 rounded-tr-xl border-t-[5px] border-r-[5px] border-black opacity-10" />
                            <div className="absolute bottom-5 left-5 h-12 w-12 rounded-bl-xl border-b-[5px] border-l-[5px] border-black opacity-10" />
                            <div className="absolute right-5 bottom-5 h-12 w-12 rounded-br-xl border-r-[5px] border-b-[5px] border-black opacity-10" />
                        </div>

                        {/* ID Number Display */}
                        <div className="flex flex-col items-center gap-1">
                            <p className="text-muted-foreground text-[10px] font-bold tracking-[0.2em] uppercase">ID Number</p>
                            <p className="text-foreground font-mono text-5xl font-black tracking-widest">{cardData.id}</p>
                        </div>

                        {/* Actions */}
                        {onRefresh && (
                            <Button
                                size="lg"
                                className="h-12 w-full max-w-xs rounded-full px-8 font-medium shadow-md"
                                onClick={onRefresh}
                                disabled={isRefreshing}
                            >
                                <RefreshCw className={cn("mr-2 h-4 w-4", isRefreshing && "animate-spin")} />
                                Refresh Code
                            </Button>
                        )}
                    </motion.div>
                </DialogContent>
            </Dialog>
        </>
    );
}

export function DigitalIdCardSkeleton({ isCompact = false }: { isCompact?: boolean }) {
    return (
        <div
            className={cn(
                "bg-card border-border/50 relative overflow-hidden rounded-3xl border shadow-sm",
                isCompact ? "h-[220px]" : "mx-auto h-[240px] w-full max-w-md",
            )}
        >
            <div className="flex h-full flex-col justify-between p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="bg-muted h-10 w-10 animate-pulse rounded-xl" />
                        <div className="space-y-2">
                            <div className="bg-muted h-4 w-20 animate-pulse rounded" />
                            <div className="bg-muted h-3 w-16 animate-pulse rounded" />
                        </div>
                    </div>
                    <div className="bg-muted h-8 w-12 animate-pulse rounded-md" />
                </div>

                <div className="my-4 flex items-center gap-5">
                    <div className="bg-muted h-20 w-20 animate-pulse rounded-2xl" />
                    <div className="flex-1 space-y-3">
                        <div className="bg-muted h-6 w-3/4 animate-pulse rounded" />
                        <div className="bg-muted h-4 w-1/2 animate-pulse rounded" />
                        <div className="flex gap-2">
                            <div className="bg-muted h-5 w-16 animate-pulse rounded" />
                            <div className="bg-muted h-5 w-16 animate-pulse rounded" />
                        </div>
                    </div>
                </div>

                <div className="border-border/30 flex items-center justify-between border-t pt-4">
                    <div className="bg-muted h-8 w-24 animate-pulse rounded" />
                    <div className="bg-muted h-8 w-28 animate-pulse rounded-full" />
                </div>
            </div>
        </div>
    );
}

export default DigitalIdCard;
