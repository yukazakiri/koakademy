"use client";

import * as React from "react";
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from "recharts";

import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ChartConfig, ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent } from "@/components/ui/chart";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { useIsMobile } from "@/hooks/use-mobile";

interface AttendanceChartData {
    date: string;
    present: number;
    absent: number;
    late: number;
    excused: number;
}

interface ClassOption {
    id: number;
    label: string;
}

interface ChartAreaInteractiveProps {
    chartData: AttendanceChartData[];
    classes: ClassOption[];
}

const chartConfig = {
    attendance: {
        label: "Attendance",
    },
    present: {
        label: "Present",
        color: "hsl(142.1 76.2% 36.3%)", // green-600
    },
    absent: {
        label: "Absent",
        color: "hsl(0 84.2% 60.2%)", // red-500
    },
    late: {
        label: "Late",
        color: "hsl(45.4 93.4% 47.5%)", // amber-500
    },
    excused: {
        label: "Excused",
        color: "hsl(217.2 91.2% 59.8%)", // blue-500
    },
} satisfies ChartConfig;

export function ChartAreaInteractive({ chartData, classes }: ChartAreaInteractiveProps) {
    const isMobile = useIsMobile();
    const [timeRange, setTimeRange] = React.useState("90d");
    const [selectedClass, setSelectedClass] = React.useState<string>("all");

    React.useEffect(() => {
        if (isMobile) {
            setTimeRange("7d");
        }
    }, [isMobile]);

    const filteredData = React.useMemo(() => {
        if (!chartData || chartData.length === 0) {
            return [];
        }

        const now = new Date();
        const referenceDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());

        let daysToSubtract = 90;
        if (timeRange === "30d") {
            daysToSubtract = 30;
        } else if (timeRange === "7d") {
            daysToSubtract = 7;
        }

        const startDate = new Date(referenceDate);
        startDate.setDate(startDate.getDate() - daysToSubtract);

        return chartData.filter((item) => {
            const itemDate = new Date(item.date);
            return itemDate >= startDate && itemDate <= referenceDate;
        });
    }, [chartData, timeRange]);

    // Calculate totals for the selected period
    const periodTotals = React.useMemo(() => {
        return filteredData.reduce(
            (acc, item) => ({
                present: acc.present + item.present,
                absent: acc.absent + item.absent,
                late: acc.late + item.late,
                excused: acc.excused + item.excused,
            }),
            { present: 0, absent: 0, late: 0, excused: 0 },
        );
    }, [filteredData]);

    const hasData = filteredData.length > 0;

    return (
        <Card className="@container/card">
            <CardHeader>
                <CardTitle>Student Attendance</CardTitle>
                <CardDescription>
                    <span className="hidden @[540px]/card:block">
                        {hasData
                            ? `${periodTotals.present} present, ${periodTotals.absent} absent in selected period`
                            : "No attendance data recorded yet"}
                    </span>
                    <span className="@[540px]/card:hidden">{hasData ? "Weekly Overview" : "No data yet"}</span>
                </CardDescription>
                <CardAction>
                    <div className="flex items-center gap-2">
                        {/* Class Filter */}
                        {classes.length > 0 && (
                            <Select value={selectedClass} onValueChange={setSelectedClass}>
                                <SelectTrigger className="hidden w-40 @[540px]/card:flex" size="sm" aria-label="Select a class">
                                    <SelectValue placeholder="All Classes" />
                                </SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    <SelectItem value="all" className="rounded-lg">
                                        All Classes
                                    </SelectItem>
                                    {classes.map((classOption) => (
                                        <SelectItem key={classOption.id} value={classOption.id.toString()} className="rounded-lg">
                                            {classOption.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}

                        {/* Time Range Toggle */}
                        <ToggleGroup
                            type="single"
                            value={timeRange}
                            onValueChange={(value) => value && setTimeRange(value)}
                            variant="outline"
                            className="hidden *:data-[slot=toggle-group-item]:!px-4 @[767px]/card:flex"
                        >
                            <ToggleGroupItem value="90d">Last 3 months</ToggleGroupItem>
                            <ToggleGroupItem value="30d">Last 30 days</ToggleGroupItem>
                            <ToggleGroupItem value="7d">Last 7 days</ToggleGroupItem>
                        </ToggleGroup>
                        <Select value={timeRange} onValueChange={setTimeRange}>
                            <SelectTrigger
                                className="flex w-40 **:data-[slot=select-value]:block **:data-[slot=select-value]:truncate @[767px]/card:hidden"
                                size="sm"
                                aria-label="Select a value"
                            >
                                <SelectValue placeholder="Last 3 months" />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                <SelectItem value="90d" className="rounded-lg">
                                    Last 3 months
                                </SelectItem>
                                <SelectItem value="30d" className="rounded-lg">
                                    Last 30 days
                                </SelectItem>
                                <SelectItem value="7d" className="rounded-lg">
                                    Last 7 days
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardAction>
            </CardHeader>
            <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                {hasData ? (
                    <ChartContainer config={chartConfig} className="aspect-auto h-[250px] w-full">
                        <AreaChart data={filteredData}>
                            <defs>
                                <linearGradient id="fillPresent" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="var(--color-present)" stopOpacity={0.8} />
                                    <stop offset="95%" stopColor="var(--color-present)" stopOpacity={0.1} />
                                </linearGradient>
                                <linearGradient id="fillAbsent" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="var(--color-absent)" stopOpacity={0.8} />
                                    <stop offset="95%" stopColor="var(--color-absent)" stopOpacity={0.1} />
                                </linearGradient>
                                <linearGradient id="fillLate" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="var(--color-late)" stopOpacity={0.8} />
                                    <stop offset="95%" stopColor="var(--color-late)" stopOpacity={0.1} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid vertical={false} />
                            <XAxis
                                dataKey="date"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                minTickGap={32}
                                tickFormatter={(value) => {
                                    const date = new Date(value);
                                    return date.toLocaleDateString("en-US", {
                                        month: "short",
                                        day: "numeric",
                                    });
                                }}
                            />
                            <YAxis tickLine={false} axisLine={false} tickMargin={8} width={40} />
                            <ChartTooltip
                                cursor={false}
                                content={
                                    <ChartTooltipContent
                                        labelFormatter={(value) => {
                                            return new Date(value).toLocaleDateString("en-US", {
                                                weekday: "short",
                                                month: "short",
                                                day: "numeric",
                                            });
                                        }}
                                        indicator="dot"
                                    />
                                }
                            />
                            <ChartLegend content={<ChartLegendContent />} />
                            <Area dataKey="present" type="monotone" fill="url(#fillPresent)" stroke="var(--color-present)" strokeWidth={2} />
                            <Area dataKey="absent" type="monotone" fill="url(#fillAbsent)" stroke="var(--color-absent)" strokeWidth={2} />
                            <Area dataKey="late" type="monotone" fill="url(#fillLate)" stroke="var(--color-late)" strokeWidth={2} />
                        </AreaChart>
                    </ChartContainer>
                ) : (
                    <div className="flex h-[250px] flex-col items-center justify-center text-center">
                        <div className="bg-muted mb-4 rounded-full p-4">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="24"
                                height="24"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                className="text-muted-foreground"
                            >
                                <path d="M3 3v18h18" />
                                <path d="m19 9-5 5-4-4-3 3" />
                            </svg>
                        </div>
                        <h3 className="text-foreground font-semibold">No attendance data</h3>
                        <p className="text-muted-foreground mt-1 max-w-xs text-sm">Start taking attendance in your classes to see the trend here.</p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
