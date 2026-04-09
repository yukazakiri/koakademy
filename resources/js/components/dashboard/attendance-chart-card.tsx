import { Badge } from "@/components/ui/badge";
import { CardContent } from "@/components/ui/card";
import { ChartConfig, ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent } from "@/components/ui/chart";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { IconCalendar, IconChartArea, IconTrendingUp } from "@tabler/icons-react";
import { motion } from "framer-motion";
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from "recharts";

interface AttendanceChartData {
    date: string;
    present: number;
    absent: number;
    late: number;
    excused: number;
}

interface AttendanceChartCardProps {
    chartData: AttendanceChartData[];
    classes?: Array<{ id: number; label: string }>;
}

const chartConfig = {
    present: {
        label: "Present",
        color: "hsl(142.1 76.2% 36.3%)",
    },
    absent: {
        label: "Absent",
        color: "hsl(0 84.2% 60.2%)",
    },
    late: {
        label: "Late",
        color: "hsl(45.4 93.4% 47.5%)",
    },
    excused: {
        label: "Excused",
        color: "hsl(217.2 91.2% 59.8%)",
    },
} satisfies ChartConfig;

export function AttendanceChartCard({ chartData, classes = [] }: AttendanceChartCardProps) {
    const [timeRange, setTimeRange] = useState("30d");
    const [selectedClass, setSelectedClass] = useState("all");

    const now = new Date();
    let daysToSubtract = 30;
    if (timeRange === "90d") daysToSubtract = 90;
    if (timeRange === "7d") daysToSubtract = 7;
    if (timeRange === "3d") daysToSubtract = 3;

    const startDate = new Date(now);
    startDate.setDate(startDate.getDate() - daysToSubtract);

    const filteredData = chartData.filter((item) => {
        const itemDate = new Date(item.date);
        return itemDate >= startDate && itemDate <= now;
    });

    const totals = filteredData.reduce(
        (acc, item) => ({
            present: acc.present + item.present,
            absent: acc.absent + item.absent,
            late: acc.late + item.late,
            excused: acc.excused + item.excused,
        }),
        { present: 0, absent: 0, late: 0, excused: 0 },
    );

    const totalRecords = totals.present + totals.absent + totals.late + totals.excused;
    const attendanceRate = totalRecords > 0 ? Math.round(((totals.present + totals.late) / totalRecords) * 100) : 0;

    const hasData = filteredData.length > 0;

    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5 }}
            className="border-border/60 bg-card/80 rounded-2xl border backdrop-blur-sm"
        >
            <div className="border-border/60 from-muted/30 flex flex-col gap-3 border-b bg-gradient-to-r to-transparent p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2.5">
                    <div className="from-primary/20 to-primary/5 flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br">
                        <IconChartArea className="text-primary h-4 w-4" />
                    </div>
                    <div>
                        <h2 className="text-foreground text-base font-semibold">Attendance Overview</h2>
                        <p className="text-muted-foreground text-xs">Track student attendance patterns</p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Select value={timeRange} onValueChange={setTimeRange}>
                        <SelectTrigger className="h-8 w-auto rounded-lg text-xs">
                            <IconCalendar className="mr-1 h-3 w-3" />
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="rounded-xl">
                            <SelectItem value="7d" className="rounded-lg text-xs">
                                Last 7 days
                            </SelectItem>
                            <SelectItem value="30d" className="rounded-lg text-xs">
                                Last 30 days
                            </SelectItem>
                            <SelectItem value="90d" className="rounded-lg text-xs">
                                Last 90 days
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    {classes.length > 0 && (
                        <Select value={selectedClass} onValueChange={setSelectedClass}>
                            <SelectTrigger className="hidden h-8 w-32 rounded-lg text-xs sm:flex">
                                <SelectValue placeholder="All Classes" />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="all" className="rounded-lg text-xs">
                                    All Classes
                                </SelectItem>
                                {classes.map((cls) => (
                                    <SelectItem key={cls.id} value={cls.id.toString()} className="rounded-lg text-xs">
                                        {cls.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>
            </div>

            <CardContent className="p-4">
                <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <motion.div
                        whileHover={{ scale: 1.02 }}
                        className="rounded-xl border border-emerald-500/20 bg-gradient-to-br from-emerald-500/10 to-transparent p-3"
                    >
                        <p className="text-muted-foreground text-xs">Present</p>
                        <p className="text-xl font-bold text-emerald-600">{totals.present}</p>
                    </motion.div>
                    <motion.div
                        whileHover={{ scale: 1.02 }}
                        className="rounded-xl border border-rose-500/20 bg-gradient-to-br from-rose-500/10 to-transparent p-3"
                    >
                        <p className="text-muted-foreground text-xs">Absent</p>
                        <p className="text-xl font-bold text-rose-600">{totals.absent}</p>
                    </motion.div>
                    <motion.div
                        whileHover={{ scale: 1.02 }}
                        className="rounded-xl border border-amber-500/20 bg-gradient-to-br from-amber-500/10 to-transparent p-3"
                    >
                        <p className="text-muted-foreground text-xs">Late</p>
                        <p className="text-xl font-bold text-amber-600">{totals.late}</p>
                    </motion.div>
                    <motion.div
                        whileHover={{ scale: 1.02 }}
                        className="rounded-xl border border-sky-500/20 bg-gradient-to-br from-sky-500/10 to-transparent p-3"
                    >
                        <p className="text-muted-foreground text-xs">Excused</p>
                        <p className="text-xl font-bold text-sky-600">{totals.excused}</p>
                    </motion.div>
                </div>

                {hasData ? (
                    <ChartContainer config={chartConfig} className="aspect-auto h-[200px] w-full sm:h-[250px]">
                        <AreaChart data={filteredData}>
                            <defs>
                                <linearGradient id="fillPresent" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="var(--color-present)" stopOpacity={0.6} />
                                    <stop offset="95%" stopColor="var(--color-present)" stopOpacity={0.1} />
                                </linearGradient>
                                <linearGradient id="fillAbsent" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="var(--color-absent)" stopOpacity={0.6} />
                                    <stop offset="95%" stopColor="var(--color-absent)" stopOpacity={0.1} />
                                </linearGradient>
                                <linearGradient id="fillLate" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="var(--color-late)" stopOpacity={0.6} />
                                    <stop offset="95%" stopColor="var(--color-late)" stopOpacity={0.1} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid vertical={false} strokeDasharray="3 3" className="stroke-border/50" />
                            <XAxis
                                dataKey="date"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                minTickGap={20}
                                tickFormatter={(value) => {
                                    const date = new Date(value);
                                    return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
                                }}
                                className="text-xs"
                            />
                            <YAxis tickLine={false} axisLine={false} tickMargin={8} width={35} className="text-xs" />
                            <ChartTooltip
                                cursor={false}
                                content={
                                    <ChartTooltipContent
                                        labelFormatter={(value) =>
                                            new Date(value).toLocaleDateString("en-US", {
                                                weekday: "short",
                                                month: "short",
                                                day: "numeric",
                                            })
                                        }
                                        indicator="dot"
                                        className="rounded-xl"
                                    />
                                }
                            />
                            <ChartLegend content={<ChartLegendContent className="text-xs" />} />
                            <Area dataKey="present" type="monotone" fill="url(#fillPresent)" stroke="var(--color-present)" strokeWidth={2} />
                            <Area dataKey="absent" type="monotone" fill="url(#fillAbsent)" stroke="var(--color-absent)" strokeWidth={2} />
                            <Area dataKey="late" type="monotone" fill="url(#fillLate)" stroke="var(--color-late)" strokeWidth={2} />
                        </AreaChart>
                    </ChartContainer>
                ) : (
                    <div className="flex h-[200px] flex-col items-center justify-center gap-3 text-center">
                        <div className="bg-muted flex h-14 w-14 items-center justify-center rounded-2xl">
                            <IconChartArea className="text-muted-foreground h-7 w-7" />
                        </div>
                        <div>
                            <p className="text-foreground font-medium">No attendance data</p>
                            <p className="text-muted-foreground mt-1 text-xs">Start taking attendance to see trends</p>
                        </div>
                    </div>
                )}

                {hasData && totalRecords > 0 && (
                    <div className="from-primary/5 mt-4 flex items-center justify-between rounded-xl bg-gradient-to-r to-transparent p-3">
                        <div className="flex items-center gap-2">
                            <div className="bg-primary/10 flex h-8 w-8 items-center justify-center rounded-lg">
                                <IconTrendingUp className="text-primary h-4 w-4" />
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs">Attendance Rate</p>
                                <p className="text-foreground font-semibold">{attendanceRate}%</p>
                            </div>
                        </div>
                        <Badge
                            className={cn(
                                "rounded-full text-xs",
                                attendanceRate >= 90
                                    ? "bg-emerald-500 text-white"
                                    : attendanceRate >= 75
                                      ? "bg-amber-500 text-white"
                                      : "bg-rose-500 text-white",
                            )}
                        >
                            {attendanceRate >= 90 ? "Excellent" : attendanceRate >= 75 ? "Good" : "Needs Attention"}
                        </Badge>
                    </div>
                )}
            </CardContent>
        </motion.div>
    );
}

import { useState } from "react";
