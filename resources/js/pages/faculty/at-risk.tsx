import FacultyLayout from "@/components/faculty/faculty-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, useForm } from "@inertiajs/react";
import { route } from "ziggy-js";
import { useState } from "react";

interface ClassInfo { id: number; code: string; section: string; students: number }
interface Reason { label: string; detail: string }
interface Action { action: string; note: string | null; date: string }
interface Alert {
    student_id: number;
    name: string;
    student_number: string;
    email: string | null;
    reasons: Reason[];
    score: number;
    level: string;
    actions: Action[];
}
interface Props { user: User; classes: ClassInfo[]; alerts: Alert[] }

interface ClassInfo { id: number; code: string; section: string; students: number }

const levelBadge: Record<string, string> = {
    high: "bg-red-500/10 text-red-600 border-red-200",
    medium: "bg-amber-500/10 text-amber-600 border-amber-200",
    low: "bg-sky-500/10 text-sky-600 border-sky-200",
};

const actionTypes = [
    { value: "contacted", label: "Contacted student" },
    { value: "meeting", label: "Scheduled meeting" },
    { value: "notified_adviser", label: "Notified adviser/parent" },
    { value: "referred_counseling", label: "Referred to counseling" },
    { value: "resolved", label: "Marked resolved" },
];

export default function AtRiskPage({ user, alerts }: Props) {
    const [levelFilter, setLevelFilter] = useState("all");
    const [selected, setSelected] = useState<Alert | null>(null);

    const form = useForm<{ student_id: string; class_id: string; action: string; note: string }>({
        student_id: "", class_id: "", action: "contacted", note: "",
    });

    const filtered = alerts.filter((a) => levelFilter === "all" || a.level === levelFilter);

    const counts = {
        high: alerts.filter((a) => a.level === "high").length,
        medium: alerts.filter((a) => a.level === "medium").length,
        low: alerts.filter((a) => a.level === "low").length,
    };

    const submitAction = () => {
        if (!selected) return;
        form.setData("student_id", String(selected.student_id));
        form.setData("class_id", "0");
        form.post(route("faculty.at-risk-alerts.actions.store"), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset("note");
                setSelected(null);
            },
        });
    };

    return (
        <FacultyLayout user={user}>
            <Head title="At-Risk Alerts" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <header className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">At-Risk Alerts</h1>
                    <p className="text-muted-foreground text-sm">
                        Students flagged from grades, attendance, and missing submissions across your classes.
                    </p>
                </header>

                <div className="grid gap-4 sm:grid-cols-3">
                    {(["high", "medium", "low"] as const).map((level) => (
                        <Card key={level}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                                    {level} risk
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-3xl font-bold">{counts[level]}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="flex flex-wrap gap-3">
                    <Select value={levelFilter} onValueChange={setLevelFilter}>
                        <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All levels</SelectItem>
                            <SelectItem value="high">High</SelectItem>
                            <SelectItem value="medium">Medium</SelectItem>
                            <SelectItem value="low">Low</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-3">
                    {filtered.length === 0 && (
                        <Card><CardContent className="text-muted-foreground p-8 text-center text-sm">
                            No alerts match the current filter.
                        </CardContent></Card>
                    )}
                    {filtered.map((alert) => (
                        <Card key={alert.student_id}>
                            <CardContent className="space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p className="font-semibold">{alert.name}</p>
                                        <p className="text-muted-foreground text-xs">{alert.student_number}{alert.email ? ` · ${alert.email}` : ""}</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline" className={levelBadge[alert.level]}>{alert.level}</Badge>
                                        <span className="text-muted-foreground text-xs font-semibold">score {alert.score}</span>
                                        <Button size="sm" variant="outline" onClick={() => { setSelected(alert); form.clearErrors(); }}>
                                            Log action
                                        </Button>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {alert.reasons.map((reason) => (
                                        <Badge key={reason.label} variant="secondary" className="font-normal">
                                            {reason.label}: {reason.detail}
                                        </Badge>
                                    ))}
                                </div>
                                {alert.actions.length > 0 && (
                                    <div className="border-t pt-2 text-xs">
                                        <p className="text-muted-foreground mb-1 font-medium">Outreach log</p>
                                        <ul className="space-y-1">
                                            {alert.actions.map((entry, i) => (
                                                <li key={i} className="text-muted-foreground">
                                                    <span className="text-foreground font-medium">{entry.action.replace(/_/g, " ")}</span> — {entry.note || "—"} · {entry.date}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {selected && (
                    <div className="bg-background/80 fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div className="absolute inset-0 bg-black/40" onClick={() => setSelected(null)} />
                        <Card className="relative w-full max-w-md">
                            <CardHeader>
                                <CardTitle className="text-base">Log outreach — {selected.name}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label>Action</Label>
                                    <Select value={form.data.action} onValueChange={(v) => form.setData("action", v)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            {actionTypes.map((t) => (
                                                <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Note</Label>
                                    <Textarea value={form.data.note} onChange={(e) => form.setData("note", e.target.value)} rows={3} placeholder="What happened / next step" />
                                </div>
                                {form.errors.note && <p className="text-destructive text-xs">{form.errors.note}</p>}
                                <div className="flex justify-end gap-2">
                                    <Button variant="outline" onClick={() => setSelected(null)}>Cancel</Button>
                                    <Button onClick={submitAction} disabled={form.processing}>Save</Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </main>
        </FacultyLayout>
    );
}
