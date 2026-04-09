import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { IconArrowUpRight, IconId, IconRefresh } from "@tabler/icons-react";
import { motion } from "framer-motion";

interface CompactIdCardProps {
    name: string;
    role?: string;
    department?: string;
    photoUrl?: string | null;
    qrCode?: string;
    isValid?: boolean;
    onRefresh?: () => void;
    onExpand?: () => void;
    isRefreshing?: boolean;
}

export function CompactIdCard({
    name,
    role,
    department,
    photoUrl,
    qrCode,
    isValid = true,
    onRefresh,
    onExpand,
    isRefreshing = false,
}: CompactIdCardProps) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="border-border/60 from-primary/5 via-card/80 to-card/80 overflow-hidden rounded-2xl border bg-gradient-to-br backdrop-blur-sm"
        >
            <div className="border-border/40 from-primary/10 flex items-center justify-between border-b bg-gradient-to-r to-transparent p-3">
                <div className="flex items-center gap-2">
                    <div className="bg-primary/20 flex h-7 w-7 items-center justify-center rounded-lg">
                        <IconId className="text-primary h-3.5 w-3.5" />
                    </div>
                    <span className="text-foreground text-sm font-semibold">Digital ID</span>
                </div>
                {isValid && (
                    <span className="flex items-center gap-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-medium text-emerald-600">
                        <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
                        Active
                    </span>
                )}
            </div>

            <CardContent className="p-3">
                <div className="flex items-center gap-3">
                    <Avatar className="ring-primary/20 h-12 w-12 ring-2">
                        <AvatarImage src={photoUrl ?? undefined} alt={name} />
                        <AvatarFallback className="bg-primary/10 text-primary font-semibold">{name.slice(0, 2).toUpperCase()}</AvatarFallback>
                    </Avatar>

                    <div className="min-w-0 flex-1">
                        <p className="text-foreground truncate text-sm font-semibold">{name}</p>
                        <p className="text-muted-foreground truncate text-xs">{role}</p>
                        {department && <p className="text-muted-foreground/70 truncate text-[10px]">{department}</p>}
                    </div>

                    {qrCode && (
                        <div className="flex flex-col items-center gap-1">
                            <div className="relative">
                                <img src={`data:image/svg+xml;base64,${qrCode}`} alt="QR Code" className="h-14 w-14 rounded-lg bg-white p-1" />
                                <div className="absolute inset-0 flex items-center justify-center">
                                    <div className="bg-primary/80 h-3 w-3 rounded-sm" />
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                <div className="mt-3 flex gap-2">
                    {onRefresh && (
                        <Button variant="outline" size="sm" className="h-8 flex-1 gap-1 text-xs" onClick={onRefresh} disabled={isRefreshing}>
                            <IconRefresh className={cn("h-3 w-3", isRefreshing && "animate-spin")} />
                            Refresh
                        </Button>
                    )}
                    {onExpand && (
                        <Button variant="default" size="sm" className="h-8 flex-1 gap-1 text-xs" onClick={onExpand}>
                            <IconArrowUpRight className="h-3 w-3" />
                            View Full
                        </Button>
                    )}
                </div>
            </CardContent>
        </motion.div>
    );
}
