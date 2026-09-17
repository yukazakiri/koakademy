import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    BarChart3,
    Copy,
    Download,
    PieChart,
    TrendingUp,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";

export interface ChartDataItem {
    label: string;
    value: number;
    color?: string;
}

export interface ChartArtifact {
    chart_type: "bar" | "area" | "ring" | "line" | "gauge";
    title: string;
    description?: string;
    data: ChartDataItem[];
    metric_unit?: string;
}

interface AnalyticsChartRendererProps {
    chart: ChartArtifact;
}

const DEFAULT_COLORS = [
    "#6366f1", // indigo
    "#10b981", // emerald
    "#f59e0b", // amber
    "#0ea5e9", // sky
    "#8b5cf6", // violet
    "#ec4899", // pink
    "#14b8a6", // teal
];

export function AnalyticsChartRenderer({ chart }: AnalyticsChartRendererProps) {
    const data = chart.data || [];
    const maxValue = React.useMemo(() => {
        return Math.max(...data.map((d) => d.value), 1);
    }, [data]);

    const totalSum = React.useMemo(() => {
        return data.reduce((acc, curr) => acc + curr.value, 0);
    }, [data]);

    const exportCsv = () => {
        const header = "Label,Value\n";
        const rows = data.map((d) => `"${d.label}",${d.value}`).join("\n");
        const blob = new Blob([header + rows], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${chart.title.replace(/\s+/g, "_")}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        toast.success("Exported chart data as CSV.");
    };

    return (
        <Card className="border border-border/80 bg-background/95 shadow-sm overflow-hidden my-3">
            <CardHeader className="py-2.5 px-3.5 border-b bg-muted/20 flex flex-row items-center justify-between">
                <div className="space-y-0.5">
                    <div className="flex items-center gap-2">
                        {chart.chart_type === "ring" ? (
                            <PieChart className="size-4 text-indigo-500" />
                        ) : (
                            <BarChart3 className="size-4 text-indigo-500" />
                        )}
                        <CardTitle className="text-xs font-semibold">{chart.title}</CardTitle>
                        <Badge variant="outline" className="text-[10px] uppercase font-mono py-0">
                            {chart.chart_type}
                        </Badge>
                    </div>
                    {chart.description && (
                        <p className="text-[11px] text-muted-foreground">{chart.description}</p>
                    )}
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={exportCsv}
                    className="text-[11px] h-7 gap-1 px-2 text-muted-foreground hover:text-foreground"
                >
                    <Download className="size-3" />
                    Export CSV
                </Button>
            </CardHeader>

            <CardContent className="p-4">
                {/* Bar Chart View */}
                {(chart.chart_type === "bar" || chart.chart_type === "line" || chart.chart_type === "area") && (
                    <div className="space-y-2.5">
                        {data.map((item, idx) => {
                            const percent = Math.min(100, Math.max(4, (item.value / maxValue) * 100));
                            const color = item.color || DEFAULT_COLORS[idx % DEFAULT_COLORS.length];

                            return (
                                <div key={item.label} className="space-y-1">
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="font-medium text-foreground/90 truncate max-w-[65%]">
                                            {item.label}
                                        </span>
                                        <span className="font-mono text-muted-foreground tabular-nums">
                                            {item.value.toLocaleString()} {chart.metric_unit || ""}
                                        </span>
                                    </div>
                                    <div className="h-2 w-full rounded-full bg-muted/60 overflow-hidden">
                                        <div
                                            className="h-full rounded-full transition-all duration-500"
                                            style={{
                                                width: `${percent}%`,
                                                backgroundColor: color,
                                            }}
                                        />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Ring / Donut Chart View */}
                {chart.chart_type === "ring" && (
                    <div className="flex flex-col sm:flex-row items-center justify-around gap-4 py-1">
                        {/* SVG Donut */}
                        <div className="relative size-28 shrink-0 flex items-center justify-center">
                            <svg viewBox="0 0 36 36" className="size-28 -rotate-90">
                                {(() => {
                                    let accumulatedPercent = 0;
                                    return data.map((item, idx) => {
                                        const slicePercent = totalSum > 0 ? (item.value / totalSum) * 100 : 0;
                                        const strokeDasharray = `${slicePercent} ${100 - slicePercent}`;
                                        const strokeDashoffset = -accumulatedPercent;
                                        accumulatedPercent += slicePercent;
                                        const color = item.color || DEFAULT_COLORS[idx % DEFAULT_COLORS.length];

                                        return (
                                            <circle
                                                key={item.label}
                                                cx="18"
                                                cy="18"
                                                r="15.915"
                                                fill="transparent"
                                                stroke={color}
                                                strokeWidth="3.2"
                                                strokeDasharray={strokeDasharray}
                                                strokeDashoffset={strokeDashoffset}
                                                className="transition-all duration-300"
                                            />
                                        );
                                    });
                                })()}
                            </svg>
                            <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span className="text-[10px] text-muted-foreground uppercase tracking-widest font-mono">Total</span>
                                <span className="text-xs font-bold font-mono tabular-nums">{totalSum.toLocaleString()}</span>
                            </div>
                        </div>

                        {/* Legend */}
                        <div className="grid grid-cols-1 gap-1.5 min-w-[50%]">
                            {data.map((item, idx) => {
                                const color = item.color || DEFAULT_COLORS[idx % DEFAULT_COLORS.length];
                                const percent = totalSum > 0 ? ((item.value / totalSum) * 100).toFixed(1) : "0";

                                return (
                                    <div key={item.label} className="flex items-center justify-between text-xs gap-2">
                                        <div className="flex items-center gap-1.5 truncate">
                                            <span className="size-2.5 rounded-sm shrink-0" style={{ backgroundColor: color }} />
                                            <span className="truncate text-foreground/80">{item.label}</span>
                                        </div>
                                        <div className="font-mono text-[11px] tabular-nums text-muted-foreground shrink-0">
                                            {item.value.toLocaleString()} ({percent}%)
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Gauge Chart View */}
                {chart.chart_type === "gauge" && (
                    <div className="flex flex-col items-center justify-center p-3 text-center space-y-2">
                        {(() => {
                            const val = data[0]?.value ?? 0;
                            const max = maxValue > val ? maxValue : 100;
                            const percentage = Math.min(100, Math.round((val / max) * 100));

                            return (
                                <>
                                    <div className="relative size-24 flex items-center justify-center">
                                        <svg viewBox="0 0 36 36" className="size-24 -rotate-90">
                                            <circle
                                                cx="18"
                                                cy="18"
                                                r="15.915"
                                                fill="transparent"
                                                stroke="currentColor"
                                                strokeWidth="3.2"
                                                className="text-muted/40"
                                            />
                                            <circle
                                                cx="18"
                                                cy="18"
                                                r="15.915"
                                                fill="transparent"
                                                stroke="#6366f1"
                                                strokeWidth="3.2"
                                                strokeDasharray={`${percentage} ${100 - percentage}`}
                                                className="transition-all duration-500"
                                            />
                                        </svg>
                                        <span className="absolute text-base font-bold font-mono">
                                            {percentage}%
                                        </span>
                                    </div>
                                    <div className="text-xs font-semibold text-foreground">
                                        {data[0]?.label || "Completion Index"}: {val.toLocaleString()} / {max.toLocaleString()} {chart.metric_unit || ""}
                                    </div>
                                </>
                            );
                        })()}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
