import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/utils";
import { IconArrowsDiff, IconCalendarEvent, IconSearch, IconSparkles } from "@tabler/icons-react";
import { AnimatePresence, motion } from "framer-motion";

interface ScheduleToolbarProps {
    query: string;
    setQuery: (val: string) => void;
    day: string;
    setDay: (val: string) => void;
    dayOptions: string[];
    showOnlyChanges: boolean;
    setShowOnlyChanges: (val: boolean) => void;
    baseline: boolean;
    hasChanges: boolean;
    today: string;
    handleSaveBaseline: () => void;
    handleClearBaseline: () => void;
}

export function ScheduleToolbar({
    query,
    setQuery,
    day,
    setDay,
    dayOptions,
    showOnlyChanges,
    setShowOnlyChanges,
    baseline,
    hasChanges,
    today,
    handleSaveBaseline,
    handleClearBaseline,
}: ScheduleToolbarProps) {
    return (
        <Card className="border-border/60 shadow-sm">
            <CardContent className="p-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    {/* Filters Section */}
                    <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                        <div className="relative w-full sm:w-[320px]">
                            <IconSearch className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search subject, room, section..."
                                className="h-9 pl-9"
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <Select value={day} onValueChange={setDay}>
                                <SelectTrigger className="h-9 w-full sm:w-[150px]">
                                    <SelectValue placeholder="All days" />
                                </SelectTrigger>
                                <SelectContent>
                                    {dayOptions.map((d) => (
                                        <SelectItem key={d} value={d}>
                                            {d}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <div className="flex items-center gap-2 pl-1 whitespace-nowrap">
                                <Switch
                                    checked={showOnlyChanges}
                                    onCheckedChange={setShowOnlyChanges}
                                    disabled={!baseline}
                                    id="schedule-changes"
                                    className="scale-90"
                                />
                                <label
                                    htmlFor="schedule-changes"
                                    className={cn("cursor-pointer text-sm select-none", baseline ? "text-foreground" : "text-muted-foreground")}
                                >
                                    Changes
                                </label>
                            </div>
                        </div>
                    </div>

                    {/* Status Badges Section */}
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        <AnimatePresence>
                            {!baseline && (
                                <motion.div initial={{ opacity: 0, scale: 0.9 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0, scale: 0.9 }}>
                                    <Button variant="outline" size="sm" className="h-8 gap-1.5 text-xs" onClick={handleSaveBaseline}>
                                        <IconSparkles className="text-primary size-3.5" />
                                        Set Baseline
                                    </Button>
                                </motion.div>
                            )}
                        </AnimatePresence>

                        {baseline ? (
                            <Badge variant={hasChanges ? "secondary" : "outline"} className="h-8 gap-1.5 px-3">
                                <IconArrowsDiff className="size-3.5" />
                                {hasChanges ? "Diff detected" : "Synced"}
                            </Badge>
                        ) : (
                            <Badge variant="outline" className="text-muted-foreground bg-muted/20 h-8 gap-1.5 border-dashed px-3">
                                <IconArrowsDiff className="size-3.5" />
                                No baseline
                            </Badge>
                        )}

                        <Badge variant="outline" className="bg-primary/5 border-primary/20 text-primary h-8 gap-1.5 px-3">
                            <IconCalendarEvent className="size-3.5" />
                            {today}
                        </Badge>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
