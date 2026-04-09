import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from "@/components/ui/chart";
import { Progress } from "@/components/ui/progress";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import { Activity, ArrowDownRight, ArrowUpRight, CreditCard, GraduationCap, Loader2, Minus, Trash2, TrendingDown, TrendingUp } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, Tooltip as RechartsTooltip, ResponsiveContainer, XAxis, YAxis } from "recharts";
import { route } from "ziggy-js";
import type { EnrollmentRow } from "./columns";
import type { EnrollmentManagementProps, EnrollmentStats } from "./types";

const CHART_COLORS = ["#3b82f6", "#22c55e", "#eab308", "#f97316", "#a855f7", "#ec4899", "#6366f1"];

const CHART_COLORS_OBJ = {
    primary: "#3b82f6",
    success: "#22c55e",
    warning: "#eab308",
    danger: "#ef4444",
    purple: "#a855f7",
    pink: "#ec4899",
    indigo: "#6366f1",
    teal: "#14b8a6",
    orange: "#f97316",
    cyan: "#06b6d4",
};

const departmentChartConfig = { count: { label: "Students", color: "hsl(var(--primary))" } } satisfies ChartConfig;

type EnrollmentAnalyticsSectionProps = {
    analytics: EnrollmentManagementProps["analytics"];
    filters: EnrollmentManagementProps["filters"];
    stats: EnrollmentStats;
    enrollmentsData: EnrollmentRow[];
    enrollmentsTotal: number;
    formatMoney: (value: number | null | undefined) => string;
};

export function EnrollmentAnalyticsSection({
    analytics,
    filters,
    stats,
    enrollmentsData,
    enrollmentsTotal,
    formatMoney,
}: EnrollmentAnalyticsSectionProps) {
    const [mounted, setMounted] = useState(false);

    const [yearLevelDepartmentFilter, setYearLevelDepartmentFilter] = useState<string>("all");
    const [filteredYearLevelData, setFilteredYearLevelData] = useState<{ year_level: number; count: number }[]>([]);
    const [isLoadingYearLevel, setIsLoadingYearLevel] = useState(false);

    const [departmentYearLevelFilter, setDepartmentYearLevelFilter] = useState<string>("all");
    const [filteredDepartmentData, setFilteredDepartmentData] = useState<{ department: string; count: number }[]>([]);
    const [isLoadingDepartment, setIsLoadingDepartment] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    useEffect(() => {
        const fetchYearLevelData = async () => {
            setIsLoadingYearLevel(true);
            try {
                const response = await fetch(
                    route("administrators.enrollments.api.year-level-by-department") + `?department=${yearLevelDepartmentFilter}`,
                );
                if (response.ok) {
                    const data = await response.json();
                    setFilteredYearLevelData(data.by_year_level || []);
                }
            } catch (error) {
                console.error("Failed to fetch year level data:", error);
            } finally {
                setIsLoadingYearLevel(false);
            }
        };
        fetchYearLevelData();
    }, [yearLevelDepartmentFilter]);

    useEffect(() => {
        const fetchDepartmentData = async () => {
            setIsLoadingDepartment(true);
            try {
                const response = await fetch(
                    route("administrators.enrollments.api.department-by-year-level") + `?year_level=${departmentYearLevelFilter}`,
                );
                if (response.ok) {
                    const data = await response.json();
                    setFilteredDepartmentData(data.by_department || []);
                }
            } catch (error) {
                console.error("Failed to fetch department data:", error);
            } finally {
                setIsLoadingDepartment(false);
            }
        };
        fetchDepartmentData();
    }, [departmentYearLevelFilter]);

    const departmentData = useMemo(() => {
        const raw = departmentYearLevelFilter === "all" ? (analytics?.by_department ?? []) : filteredDepartmentData;
        const data = Array.isArray(raw) ? raw : [];
        return data.map((item) => ({
            name: item.department || "Unknown",
            value: item.count,
        }));
    }, [analytics?.by_department, filteredDepartmentData, departmentYearLevelFilter]);

    const yearLevelData = useMemo(() => {
        const raw = yearLevelDepartmentFilter === "all" ? (analytics?.by_year_level ?? []) : filteredYearLevelData;
        const data = Array.isArray(raw) ? raw : [];
        const colorArray = Object.values(CHART_COLORS_OBJ);
        return data
            .sort((a, b) => (a.year_level ?? 0) - (b.year_level ?? 0))
            .map((item, index) => ({
                name: `Year ${item.year_level}`,
                year: `Year ${item.year_level}`,
                value: item.count,
                students: item.count,
                fill: colorArray[index % colorArray.length],
            }));
    }, [analytics?.by_year_level, filteredYearLevelData, yearLevelDepartmentFilter]);

    const yearLevelTotal = useMemo(() => yearLevelData.reduce((sum, d) => sum + d.value, 0), [yearLevelData]);
    const mostPopulatedYear =
        yearLevelData.length > 0 ? yearLevelData.reduce((max, item) => (item.value > max.value ? item : max), yearLevelData[0]) : null;

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-foreground text-2xl font-bold tracking-tight">Enrollment Analytics</h2>
                    <p className="text-muted-foreground text-sm">
                        Comprehensive insights and trends for {filters.currentSchoolYear} - {(filters.currentSchoolYear ?? 0) + 1}
                    </p>
                </div>
            </div>

            <Separator />

            {/* KPI Cards */}
            <div className="grid gap-4 md:grid-cols-5">
                {/* Total Enrolled */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Total Enrolled</CardTitle>
                        <GraduationCap className="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{stats.enrolled}</div>
                        <p className="text-muted-foreground text-xs">This semester</p>
                    </CardContent>
                </Card>

                {/* Active Students */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Active</CardTitle>
                        <Activity className="h-4 w-4 text-emerald-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-emerald-600">{analytics?.active_count ?? 0}</div>
                        <p className="text-muted-foreground text-xs">Currently enrolled</p>
                    </CardContent>
                </Card>

                {/* Deleted/Trashed */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Deleted</CardTitle>
                        <Trash2 className="h-4 w-4 text-red-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-red-600">{analytics?.trashed_count ?? 0}</div>
                        <p className="text-muted-foreground text-xs">Removed records</p>
                    </CardContent>
                </Card>

                {/* Payment Rate */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Payment Rate</CardTitle>
                        <CreditCard className="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            {(() => {
                                const totalPaid = enrollmentsData.filter((e) => e.tuition?.balance === 0).length;
                                const paidPercentage = enrollmentsTotal > 0 ? Math.round((totalPaid / enrollmentsTotal) * 100) : 0;
                                return `${paidPercentage}%`;
                            })()}
                        </div>
                        <p className="text-muted-foreground text-xs">Fully paid students</p>
                    </CardContent>
                </Card>

                {/* Semester Growth */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Semester Growth</CardTitle>
                        {(() => {
                            const currentCount = analytics?.current_semester_count ?? 0;
                            const previousCount = analytics?.previous_semester_count ?? 0;
                            const diff = currentCount - previousCount;
                            const isPositive = diff > 0;
                            const isNegative = diff < 0;

                            return isPositive ? (
                                <TrendingUp className="h-4 w-4 text-emerald-600" />
                            ) : isNegative ? (
                                <TrendingDown className="h-4 w-4 text-red-600" />
                            ) : (
                                <Minus className="text-muted-foreground h-4 w-4" />
                            );
                        })()}
                    </CardHeader>
                    <CardContent>
                        <div
                            className={cn(
                                "text-2xl font-bold",
                                (() => {
                                    const currentCount = analytics?.current_semester_count ?? 0;
                                    const previousCount = analytics?.previous_semester_count ?? 0;
                                    const diff = currentCount - previousCount;
                                    return diff > 0 ? "text-emerald-600" : diff < 0 ? "text-red-600" : "";
                                })(),
                            )}
                        >
                            {(() => {
                                const currentCount = analytics?.current_semester_count ?? 0;
                                const previousCount = analytics?.previous_semester_count ?? 0;
                                const diff = currentCount - previousCount;
                                const percentChange = previousCount > 0 ? Math.round((diff / previousCount) * 100) : currentCount > 0 ? 100 : 0;
                                return `${diff >= 0 ? "+" : ""}${percentChange}%`;
                            })()}
                        </div>
                        <p className="text-muted-foreground text-xs">vs last semester</p>
                    </CardContent>
                </Card>
            </div>

            {/* Main Charts Row */}
            <div className="grid gap-6 lg:grid-cols-2">
                {/* Department Distribution */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <div className="space-y-1">
                            <CardTitle className="text-lg font-semibold">Department Distribution</CardTitle>
                            <CardDescription>Students enrolled by department</CardDescription>
                        </div>
                        <Select value={departmentYearLevelFilter} onValueChange={setDepartmentYearLevelFilter}>
                            <SelectTrigger className="h-8 w-28">
                                <SelectValue placeholder="All Years" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Years</SelectItem>
                                <SelectItem value="1">Year 1</SelectItem>
                                <SelectItem value="2">Year 2</SelectItem>
                                <SelectItem value="3">Year 3</SelectItem>
                                <SelectItem value="4">Year 4</SelectItem>
                            </SelectContent>
                        </Select>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <div className="h-[300px]">
                            {isLoadingDepartment ? (
                                <div className="flex h-full items-center justify-center">
                                    <Loader2 className="text-muted-foreground h-6 w-6 animate-spin" />
                                </div>
                            ) : mounted ? (
                                <ChartContainer config={departmentChartConfig}>
                                    <BarChart data={departmentData} margin={{ top: 10, right: 10, left: -20, bottom: 20 }}>
                                        <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--border))" />
                                        <XAxis
                                            dataKey="name"
                                            axisLine={false}
                                            tickLine={false}
                                            tick={{ fontSize: 12, fill: "hsl(var(--muted-foreground))" }}
                                            angle={-45}
                                            textAnchor="end"
                                            height={80}
                                        />
                                        <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 12, fill: "hsl(var(--muted-foreground))" }} />
                                        <ChartTooltip content={<ChartTooltipContent />} />
                                        <Bar dataKey="value" fill="var(--color-count)" radius={[6, 6, 0, 0]} maxBarSize={60}>
                                            {departmentData.map((entry, index) => (
                                                <Cell key={`cell-${index}`} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                                            ))}
                                        </Bar>
                                    </BarChart>
                                </ChartContainer>
                            ) : (
                                <div className="text-muted-foreground flex h-full items-center justify-center">Loading chart...</div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Year Level Distribution - Revamped */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <div className="space-y-1">
                            <CardTitle className="text-lg font-semibold">Year Level Distribution</CardTitle>
                            <CardDescription>Student breakdown by academic year</CardDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <Select value={yearLevelDepartmentFilter} onValueChange={setYearLevelDepartmentFilter}>
                                <SelectTrigger className="h-8 w-32">
                                    <SelectValue placeholder="All Departments" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Departments</SelectItem>
                                    <SelectItem value="IT">IT</SelectItem>
                                    <SelectItem value="HM">HM</SelectItem>
                                    <SelectItem value="BA">BA</SelectItem>
                                    <SelectItem value="TESDA">TESDA</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-4">
                        {isLoadingYearLevel ? (
                            <div className="flex h-[200px] items-center justify-center">
                                <Loader2 className="text-muted-foreground h-6 w-6 animate-spin" />
                            </div>
                        ) : mounted && yearLevelData.length > 0 ? (
                            <div className="space-y-4">
                                <div className="space-y-3">
                                    {yearLevelData.map((item, index) => {
                                        const percentage = yearLevelTotal > 0 ? (item.value / yearLevelTotal) * 100 : 0;
                                        const yearColors = ["bg-blue-500", "bg-emerald-500", "bg-amber-500", "bg-violet-500", "bg-rose-500"];

                                        return (
                                            <div key={item.name} className="space-y-1.5">
                                                <div className="flex items-center justify-between text-sm">
                                                    <div className="flex items-center gap-2">
                                                        <div className={cn("h-2.5 w-2.5 rounded-full", yearColors[index % yearColors.length])} />
                                                        <span className="font-medium">{item.name}</span>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-semibold tabular-nums">{item.value.toLocaleString()}</span>
                                                        <span className="text-muted-foreground text-xs">({percentage.toFixed(1)}%)</span>
                                                    </div>
                                                </div>
                                                <Progress value={percentage} className="h-2" />
                                            </div>
                                        );
                                    })}
                                </div>

                                <Separator className="my-4" />

                                <div className="grid grid-cols-3 gap-4 text-center">
                                    <div className="space-y-1">
                                        <p className="text-2xl font-bold tabular-nums">{yearLevelTotal.toLocaleString()}</p>
                                        <p className="text-muted-foreground text-xs">Total Students</p>
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-2xl font-bold tabular-nums">
                                            {yearLevelData.length > 0 ? Math.round(yearLevelTotal / yearLevelData.length) : 0}
                                        </p>
                                        <p className="text-muted-foreground text-xs">Avg per Year</p>
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-2xl font-bold">{mostPopulatedYear?.name || "—"}</p>
                                        <p className="text-muted-foreground text-xs">Most Populated</p>
                                    </div>
                                </div>
                            </div>
                        ) : mounted ? (
                            <div className="flex h-[200px] items-center justify-center">
                                <div className="text-center">
                                    <GraduationCap className="text-muted-foreground mx-auto mb-2 h-8 w-8" />
                                    <p className="text-muted-foreground text-sm">No year level data available</p>
                                </div>
                            </div>
                        ) : (
                            <div className="flex h-[200px] items-center justify-center">
                                <Loader2 className="text-muted-foreground h-6 w-6 animate-spin" />
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Secondary Charts Row */}
            <div className="grid gap-6 lg:grid-cols-3">
                {/* Status Distribution */}
                <Card>
                    <CardHeader>
                        <CardTitle>Status Distribution</CardTitle>
                        <CardDescription>Active vs Deleted</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="h-[250px]">
                            {mounted ? (
                                (() => {
                                    const statusData = [
                                        { name: "Active", value: analytics?.active_count ?? 0, fill: CHART_COLORS_OBJ.success },
                                        { name: "Deleted", value: analytics?.trashed_count ?? 0, fill: CHART_COLORS_OBJ.danger },
                                    ];
                                    return (
                                        <ResponsiveContainer width="100%" height="100%">
                                            <PieChart>
                                                <Pie
                                                    data={statusData}
                                                    cx="50%"
                                                    cy="50%"
                                                    innerRadius={50}
                                                    outerRadius={80}
                                                    paddingAngle={5}
                                                    dataKey="value"
                                                >
                                                    {statusData.map((entry, index) => (
                                                        <Cell key={`cell-${index}`} fill={entry.fill} />
                                                    ))}
                                                </Pie>
                                                <RechartsTooltip
                                                    contentStyle={{
                                                        borderRadius: "12px",
                                                        border: "none",
                                                        boxShadow: "0 4px 12px rgba(0,0,0,0.1)",
                                                        backgroundColor: "hsl(var(--popover))",
                                                        color: "hsl(var(--popover-foreground))",
                                                    }}
                                                />
                                                <Legend verticalAlign="bottom" height={36} iconType="circle" />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    );
                                })()
                            ) : (
                                <div className="text-muted-foreground flex h-full items-center justify-center">Loading chart...</div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Top Courses */}
                <Card>
                    <CardHeader>
                        <CardTitle>Top Courses</CardTitle>
                        <CardDescription>Most enrolled programs</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="h-[250px]">
                            {mounted ? (
                                (() => {
                                    const courseCounts = enrollmentsData.reduce(
                                        (acc, e) => {
                                            const course = e.course || "Unknown";
                                            acc[course] = (acc[course] || 0) + 1;
                                            return acc;
                                        },
                                        {} as Record<string, number>,
                                    );

                                    const courseData = Object.entries(courseCounts)
                                        .map(([name, value]) => ({ name, value }))
                                        .sort((a, b) => b.value - a.value)
                                        .slice(0, 5);

                                    return courseData.length > 0 ? (
                                        <ResponsiveContainer width="100%" height="100%">
                                            <PieChart>
                                                <Pie
                                                    data={courseData}
                                                    cx="50%"
                                                    cy="50%"
                                                    outerRadius={80}
                                                    dataKey="value"
                                                    label={({ name, percent }) => `${name} (${(percent * 100).toFixed(0)}%)`}
                                                    labelLine={{ stroke: "hsl(var(--muted-foreground))", strokeWidth: 1 }}
                                                >
                                                    {courseData.map((entry, index) => (
                                                        <Cell key={`cell-${index}`} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                                                    ))}
                                                </Pie>
                                                <RechartsTooltip
                                                    contentStyle={{
                                                        borderRadius: "12px",
                                                        border: "none",
                                                        boxShadow: "0 4px 12px rgba(0,0,0,0.1)",
                                                        backgroundColor: "hsl(var(--popover))",
                                                        color: "hsl(var(--popover-foreground))",
                                                    }}
                                                />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    ) : (
                                        <div className="text-muted-foreground flex h-full items-center justify-center">No course data</div>
                                    );
                                })()
                            ) : (
                                <div className="text-muted-foreground flex h-full items-center justify-center">Loading chart...</div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Semester Comparison */}
                <Card>
                    <CardHeader>
                        <CardTitle>Semester Comparison</CardTitle>
                        <CardDescription>Current vs Previous</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Current Semester */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between text-sm">
                                <span className="font-medium">Current Semester</span>
                                <span className="font-bold">{analytics?.current_semester_count ?? 0}</span>
                            </div>
                            <div className="bg-muted h-3 w-full overflow-hidden rounded-full">
                                <div
                                    className="bg-primary h-full transition-all"
                                    style={{
                                        width: `${
                                            Math.max(analytics?.current_semester_count ?? 0, analytics?.previous_semester_count ?? 0) > 0
                                                ? ((analytics?.current_semester_count ?? 0) /
                                                      Math.max(analytics?.current_semester_count ?? 0, analytics?.previous_semester_count ?? 0)) *
                                                  100
                                                : 0
                                        }%`,
                                    }}
                                />
                            </div>
                        </div>

                        {/* Previous Semester */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between text-sm">
                                <span className="font-medium">Previous Semester</span>
                                <span className="font-bold">{analytics?.previous_semester_count ?? 0}</span>
                            </div>
                            <div className="bg-muted h-3 w-full overflow-hidden rounded-full">
                                <div
                                    className="bg-muted-foreground h-full transition-all"
                                    style={{
                                        width: `${
                                            Math.max(analytics?.current_semester_count ?? 0, analytics?.previous_semester_count ?? 0) > 0
                                                ? ((analytics?.previous_semester_count ?? 0) /
                                                      Math.max(analytics?.current_semester_count ?? 0, analytics?.previous_semester_count ?? 0)) *
                                                  100
                                                : 0
                                        }%`,
                                    }}
                                />
                            </div>
                        </div>

                        {/* Growth Indicator */}
                        <div className="pt-2">
                            {(() => {
                                const currentCount = analytics?.current_semester_count ?? 0;
                                const previousCount = analytics?.previous_semester_count ?? 0;
                                const diff = currentCount - previousCount;
                                const percentChange = previousCount > 0 ? Math.round((diff / previousCount) * 100) : currentCount > 0 ? 100 : 0;
                                const isPositive = diff > 0;
                                const isNegative = diff < 0;

                                return (
                                    <div className="bg-muted/50 flex items-center justify-center gap-2 rounded-lg border p-3">
                                        {isPositive ? (
                                            <ArrowUpRight className="h-5 w-5 text-emerald-600" />
                                        ) : isNegative ? (
                                            <ArrowDownRight className="h-5 w-5 text-red-600" />
                                        ) : (
                                            <Minus className="text-muted-foreground h-5 w-5" />
                                        )}
                                        <span
                                            className={cn(
                                                "text-lg font-bold",
                                                isPositive ? "text-emerald-600" : isNegative ? "text-red-600" : "text-muted-foreground",
                                            )}
                                        >
                                            {diff >= 0 ? "+" : ""}
                                            {diff} ({percentChange >= 0 ? "+" : ""}
                                            {percentChange}%)
                                        </span>
                                    </div>
                                );
                            })()}
                        </div>

                        {/* Total This Year */}
                        <div className="border-t pt-4">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">Total This Year</span>
                                <span className="text-xl font-bold">{analytics?.current_school_year_count ?? 0}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Quick Insights */}
            <Card>
                <CardHeader>
                    <CardTitle>Quick Insights</CardTitle>
                    <CardDescription>Key metrics and observations</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4 md:grid-cols-4">
                        {/* Total Revenue */}
                        <div className="space-y-1">
                            <p className="text-muted-foreground text-sm">Total Revenue</p>
                            <p className="text-2xl font-bold">{formatMoney(stats.tuition)}</p>
                            <p className="text-muted-foreground text-xs">From visible records</p>
                        </div>

                        {/* Outstanding Balance */}
                        <div className="space-y-1">
                            <p className="text-muted-foreground text-sm">Outstanding Balance</p>
                            <p className="text-2xl font-bold">
                                {formatMoney(enrollmentsData.reduce((sum, e) => sum + (e.tuition?.balance || 0), 0))}
                            </p>
                            <p className="text-muted-foreground text-xs">Unpaid tuition</p>
                        </div>

                        {/* Paid in Full */}
                        <div className="space-y-1">
                            <p className="text-muted-foreground text-sm">Paid in Full</p>
                            <p className="text-2xl font-bold text-emerald-600">{enrollmentsData.filter((e) => e.tuition?.balance === 0).length}</p>
                            <p className="text-muted-foreground text-xs">
                                {enrollmentsTotal > 0
                                    ? `${Math.round((enrollmentsData.filter((e) => e.tuition?.balance === 0).length / enrollmentsTotal) * 100)}%`
                                    : "0%"}{" "}
                                of total
                            </p>
                        </div>

                        {/* Pending Applicants */}
                        <div className="space-y-1">
                            <p className="text-muted-foreground text-sm">Pending Applicants</p>
                            <p className="text-2xl font-bold text-blue-600">{stats.applicants}</p>
                            <p className="text-muted-foreground text-xs">Awaiting processing</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
