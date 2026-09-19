import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import {
    Activity,
    BarChart3,
    Download,
    LineChart as LineChartIcon,
    PieChart as PieChartIcon,
} from "lucide-react";
import * as React from "react";
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Sector,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";
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

const DEFAULT_PALETTE = [
    "#6366f1", // indigo
    "#0ea5e9", // sky
    "#10b981", // emerald
    "#f59e0b", // amber
    "#ec4899", // pink
    "#8b5cf6", // violet
    "#14b8a6", // teal
    "#f97316", // orange
];

// Interactive active shape renderer for Pie/Ring chart
const renderActiveShape = (props: any) => {
    const {
        cx,
        cy,
        innerRadius,
        outerRadius,
        startAngle,
        endAngle,
        fill,
        payload,
        value,
        percent,
    } = props;

    return (
        <g>
            <Sector
                cx={cx}
                cy={cy}
                innerRadius={innerRadius - 2}
                outerRadius={outerRadius + 6}
                startAngle={startAngle}
                endAngle={endAngle}
                fill={fill}
                style={{ filter: "drop-shadow(0px 4px 10px rgba(0,0,0,0.25))" }}
            />
            <Sector
                cx={cx}
                cy={cy}
                startAngle={startAngle}
                endAngle={endAngle}
                innerRadius={outerRadius + 8}
                outerRadius={outerRadius + 11}
                fill={fill}
            />
        </g>
    );
};

// Custom Tooltip component for Recharts
function CustomChartTooltip({ active, payload, metricUnit, totalSum }: any) {
    if (!active || !payload || !payload.length) {
        return null;
    }

    const item = payload[0];
    const dataObj = item.payload || {};
    const val = typeof item.value === "number" ? item.value : Number(item.value || 0);
    const label = dataObj.label || item.name || "Item";
    const color = item.color || item.fill || dataObj.fill || "#6366f1";
    const percent = totalSum > 0 ? ((val / totalSum) * 100).toFixed(1) : null;

    return (
        <div className="rounded-xl border border-border/80 bg-background/95 p-3 shadow-xl backdrop-blur-md text-xs space-y-1 min-w-[140px] animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center gap-2 font-medium text-foreground">
                <span className="size-2.5 rounded-full shrink-0" style={{ backgroundColor: color }} />
                <span className="truncate">{label}</span>
            </div>
            <div className="flex items-baseline justify-between gap-3 font-mono pt-1">
                <span className="text-sm font-bold text-foreground">
                    {val.toLocaleString()} {metricUnit || ""}
                </span>
                {percent !== null && (
                    <span className="text-[11px] text-muted-foreground font-medium">
                        ({percent}%)
                    </span>
                )}
            </div>
        </div>
    );
}

export function AnalyticsChartRenderer({ chart }: AnalyticsChartRendererProps) {
    const [activeIndex, setActiveIndex] = React.useState<number | null>(null);

    const rawData = chart.data || [];
    const chartType = chart.chart_type || "bar";
    const metricUnit = chart.metric_unit || "";

    const formattedData = React.useMemo(() => {
        return rawData.map((item, idx) => ({
            ...item,
            value: Number(item.value) || 0,
            fill: item.color || DEFAULT_PALETTE[idx % DEFAULT_PALETTE.length],
        }));
    }, [rawData]);

    const totalSum = React.useMemo(() => {
        return formattedData.reduce((acc, curr) => acc + curr.value, 0);
    }, [formattedData]);

    const exportCsv = () => {
        const header = "Label,Value,Percentage\n";
        const rows = formattedData
            .map((d) => {
                const pct = totalSum > 0 ? ((d.value / totalSum) * 100).toFixed(2) + "%" : "0%";
                return `"${d.label}",${d.value},${pct}`;
            })
            .join("\n");
        const blob = new Blob([header + rows], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${chart.title.replace(/\s+/g, "_")}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        toast.success(`Exported "${chart.title}" as CSV.`);
    };

    const onPieEnter = (_: any, index: number) => {
        setActiveIndex(index);
    };

    const onPieLeave = () => {
        setActiveIndex(null);
    };

    const getChartIcon = () => {
        switch (chartType) {
            case "ring":
                return <PieChartIcon className="size-4 text-indigo-500" />;
            case "line":
                return <LineChartIcon className="size-4 text-emerald-500" />;
            case "area":
                return <Activity className="size-4 text-sky-500" />;
            default:
                return <BarChart3 className="size-4 text-indigo-500" />;
        }
    };

    return (
        <Card className="border border-border/80 bg-card dark:bg-[#121215] shadow-xs overflow-hidden my-3 hover:border-primary/40 transition-colors">
            <CardHeader className="py-2.5 px-3.5 border-b border-border/40 bg-muted/20 flex flex-row items-center justify-between">
                <div className="space-y-0.5 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        {getChartIcon()}
                        <CardTitle className="text-xs font-semibold truncate text-foreground">
                            {chart.title}
                        </CardTitle>
                        <Badge variant="outline" className="text-[9.5px] uppercase font-mono py-0">
                            {chartType}
                        </Badge>
                        {totalSum > 0 && (
                            <span className="text-[10.5px] text-muted-foreground font-mono hidden sm:inline">
                                Total: {totalSum.toLocaleString()} {metricUnit}
                            </span>
                        )}
                    </div>
                    {chart.description && (
                        <p className="text-[11px] text-muted-foreground line-clamp-1">
                            {chart.description}
                        </p>
                    )}
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={exportCsv}
                    className="text-[11px] h-7 gap-1 px-2 text-muted-foreground hover:text-foreground shrink-0 rounded-lg"
                    title="Export data as CSV"
                >
                    <Download className="size-3" />
                    <span className="hidden sm:inline">Export CSV</span>
                </Button>
            </CardHeader>

            <CardContent className="p-3.5 pt-4">
                {/* 1. Ring / Donut / Pie Interactive Chart */}
                {chartType === "ring" && (
                    <div className="h-64 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    activeIndex={activeIndex !== null ? activeIndex : undefined}
                                    activeShape={renderActiveShape}
                                    data={formattedData}
                                    cx="50%"
                                    cy="48%"
                                    innerRadius={55}
                                    outerRadius={85}
                                    paddingAngle={3}
                                    dataKey="value"
                                    nameKey="label"
                                    onMouseEnter={onPieEnter}
                                    onMouseLeave={onPieLeave}
                                    cursor="pointer"
                                >
                                    {formattedData.map((entry, index) => (
                                        <Cell
                                            key={`cell-${index}`}
                                            fill={entry.fill}
                                            stroke="transparent"
                                            className="transition-all duration-200"
                                        />
                                    ))}
                                </Pie>
                                <Tooltip
                                    content={<CustomChartTooltip metricUnit={metricUnit} totalSum={totalSum} />}
                                />
                                <Legend
                                    verticalAlign="bottom"
                                    iconType="circle"
                                    iconSize={8}
                                    formatter={(value: string, entry: any) => {
                                        const item = formattedData.find((d) => d.label === value);
                                        const pct = item && totalSum > 0 ? ` (${((item.value / totalSum) * 100).toFixed(0)}%)` : "";
                                        return (
                                            <span className="text-[11px] text-foreground/80 font-medium">
                                                {value}
                                                <span className="text-muted-foreground font-mono">{pct}</span>
                                            </span>
                                        );
                                    }}
                                />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                )}

                {/* 2. Interactive Bar Chart */}
                {chartType === "bar" && (
                    <div className="h-60 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={formattedData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} opacity={0.2} stroke="currentColor" />
                                <XAxis
                                    dataKey="label"
                                    tickLine={false}
                                    axisLine={false}
                                    fontSize={11}
                                    tick={{ fill: "currentColor", opacity: 0.7 }}
                                />
                                <YAxis
                                    tickLine={false}
                                    axisLine={false}
                                    fontSize={11}
                                    tick={{ fill: "currentColor", opacity: 0.7 }}
                                    tickFormatter={(val) => val.toLocaleString()}
                                />
                                <Tooltip content={<CustomChartTooltip metricUnit={metricUnit} totalSum={totalSum} />} />
                                <Bar
                                    dataKey="value"
                                    radius={[6, 6, 0, 0]}
                                    maxBarSize={48}
                                    cursor="pointer"
                                >
                                    {formattedData.map((entry, index) => (
                                        <Cell
                                            key={`bar-cell-${index}`}
                                            fill={entry.fill}
                                            className="hover:opacity-80 transition-opacity"
                                        />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                )}

                {/* 3. Interactive Area Chart */}
                {chartType === "area" && (
                    <div className="h-60 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={formattedData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="areaGradient" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="#6366f1" stopOpacity={0.4} />
                                        <stop offset="95%" stopColor="#6366f1" stopOpacity={0.0} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} opacity={0.2} stroke="currentColor" />
                                <XAxis
                                    dataKey="label"
                                    tickLine={false}
                                    axisLine={false}
                                    fontSize={11}
                                    tick={{ fill: "currentColor", opacity: 0.7 }}
                                />
                                <YAxis
                                    tickLine={false}
                                    axisLine={false}
                                    fontSize={11}
                                    tick={{ fill: "currentColor", opacity: 0.7 }}
                                    tickFormatter={(val) => val.toLocaleString()}
                                />
                                <Tooltip content={<CustomChartTooltip metricUnit={metricUnit} totalSum={totalSum} />} />
                                <Area
                                    type="monotone"
                                    dataKey="value"
                                    stroke="#6366f1"
                                    strokeWidth={2.5}
                                    fillOpacity={1}
                                    fill="url(#areaGradient)"
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                )}

                {/* 4. Interactive Line Chart */}
                {chartType === "line" && (
                    <div className="h-60 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={formattedData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} opacity={0.2} stroke="currentColor" />
                                <XAxis
                                    dataKey="label"
                                    tickLine={false}
                                    axisLine={false}
                                    fontSize={11}
                                    tick={{ fill: "currentColor", opacity: 0.7 }}
                                />
                                <YAxis
                                    tickLine={false}
                                    axisLine={false}
                                    fontSize={11}
                                    tick={{ fill: "currentColor", opacity: 0.7 }}
                                    tickFormatter={(val) => val.toLocaleString()}
                                />
                                <Tooltip content={<CustomChartTooltip metricUnit={metricUnit} totalSum={totalSum} />} />
                                <Line
                                    type="monotone"
                                    dataKey="value"
                                    stroke="#6366f1"
                                    strokeWidth={2.5}
                                    dot={{ fill: "#6366f1", r: 4 }}
                                    activeDot={{ r: 7, stroke: "#ffffff", strokeWidth: 2 }}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                )}

                {/* 5. Gauge Chart */}
                {chartType === "gauge" && (
                    <div className="h-60 w-full flex flex-col items-center justify-center">
                        {(() => {
                            const val = formattedData[0]?.value ?? 0;
                            const max = 100;
                            const pct = Math.min(100, Math.round((val / max) * 100));

                            const gaugeData = [
                                { name: "Achieved", value: val, fill: "#6366f1" },
                                { name: "Remaining", value: Math.max(0, max - val), fill: "rgba(128,128,128,0.15)" },
                            ];

                            return (
                                <ResponsiveContainer width="100%" height="100%">
                                    <PieChart>
                                        <Pie
                                            data={gaugeData}
                                            cx="50%"
                                            cy="65%"
                                            startAngle={180}
                                            endAngle={0}
                                            innerRadius={65}
                                            outerRadius={95}
                                            paddingAngle={2}
                                            dataKey="value"
                                        >
                                            {gaugeData.map((entry, index) => (
                                                <Cell key={`gauge-cell-${index}`} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                        <Tooltip content={<CustomChartTooltip metricUnit={metricUnit} />} />
                                        <text
                                            x="50%"
                                            y="60%"
                                            textAnchor="middle"
                                            dominantBaseline="middle"
                                            className="fill-foreground font-mono text-xl font-bold"
                                        >
                                            {pct}%
                                        </text>
                                        <text
                                            x="50%"
                                            y="75%"
                                            textAnchor="middle"
                                            dominantBaseline="middle"
                                            className="fill-muted-foreground text-xs"
                                        >
                                            {formattedData[0]?.label || "Index"}
                                        </text>
                                    </PieChart>
                                </ResponsiveContainer>
                            );
                        })()}
                    </div>
                )}

                {/* Interactive Legend / Data Strip */}
                {chartType !== "ring" && formattedData.length > 0 && (
                    <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 pt-3 border-t border-border/40 mt-1">
                        {formattedData.map((item, idx) => (
                            <div key={idx} className="flex items-center gap-1.5 text-[11px]">
                                <span className="size-2 rounded-full" style={{ backgroundColor: item.fill }} />
                                <span className="text-foreground/80 font-medium">{item.label}:</span>
                                <span className="font-mono text-muted-foreground font-medium">
                                    {item.value.toLocaleString()} {metricUnit}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
