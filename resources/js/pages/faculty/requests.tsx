import FacultyLayout from "@/components/faculty/faculty-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, useForm, router } from "@inertiajs/react";
import { route } from "ziggy-js";

interface Step { name: string; status: string }
interface Request {
    id: number; type: string; title: string; details: string | null; status: string;
    steps: Step[]; current_step: number; decision_note: string | null;
    decided_at: string | null; created_at: string;
}
interface RequestType { value: string; label: string; steps: string[] }
interface Props { user: User; requests: Request[]; request_types: RequestType[] }

const statusBadge: Record<string, string> = {
    pending: "bg-amber-500/10 text-amber-600 border-amber-200",
    approved: "bg-emerald-500/10 text-emerald-600 border-emerald-200",
    rejected: "bg-red-500/10 text-red-600 border-red-200",
    withdrawn: "bg-muted text-muted-foreground",
};

export default function RequestsPage({ user, requests, request_types }: Props) {
    const form = useForm<{ type: string; title: string; details: string }>({
        type: request_types[0]?.value ?? "other", title: "", details: "",
    });

    const selectedType = request_types.find((t) => t.value === form.data.type);

    const submit = () => form.post(route("faculty.requests.store"), {
        preserveScroll: true,
        onSuccess: () => form.reset("title", "details"),
    });

    const withdraw = (id: number) => router.post(route("faculty.requests.withdraw", id), {}, { preserveScroll: true });

    return (
        <FacultyLayout user={user}>
            <Head title="Requests & Approvals" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <header className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">Requests & Approvals</h1>
                    <p className="text-muted-foreground text-sm">Leave, make-up exams, and other requests routed through the approval chain.</p>
                </header>

                <Card>
                    <CardHeader><CardTitle className="text-base">New request</CardTitle></CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2">
                        <div className="space-y-1">
                            <Label>Type</Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData("type", v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {request_types.map((t) => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            {selectedType && (
                                <p className="text-muted-foreground text-xs">Route: {selectedType.steps.join(" → ")}</p>
                            )}
                        </div>
                        <div className="space-y-1">
                            <Label>Title</Label>
                            <Input value={form.data.title} onChange={(e) => form.setData("title", e.target.value)} placeholder="e.g., Leave on Nov 3" />
                            {form.errors.title && <p className="text-destructive text-xs">{form.errors.title}</p>}
                        </div>
                        <div className="space-y-1 sm:col-span-2">
                            <Label>Details</Label>
                            <Textarea rows={3} value={form.data.details} onChange={(e) => form.setData("details", e.target.value)} placeholder="Explanation, dates, attachments info…" />
                        </div>
                        <div>
                            <Button onClick={submit} disabled={form.processing}>Submit request</Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="space-y-3">
                    {requests.length === 0 && (
                        <Card><CardContent className="text-muted-foreground p-8 text-center text-sm">No requests yet.</CardContent></Card>
                    )}
                    {requests.map((r) => (
                        <Card key={r.id}>
                            <CardContent className="space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p className="font-semibold">{r.title}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {r.type.replace(/_/g, " ")} · submitted {r.created_at}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline" className={statusBadge[r.status]}>{r.status}</Badge>
                                        {r.status === "pending" && (
                                            <Button size="sm" variant="ghost" className="text-destructive" onClick={() => withdraw(r.id)}>Withdraw</Button>
                                        )}
                                    </div>
                                </div>

                                {r.details && <p className="text-muted-foreground text-sm">{r.details}</p>}

                                <div className="flex flex-wrap items-center gap-2">
                                    {(r.steps ?? []).map((step, i) => {
                                        const done = r.status === "approved" || i < r.current_step;
                                        const active = r.status === "pending" && i === r.current_step;
                                        return (
                                            <div key={i} className="flex items-center gap-2">
                                                <div className={`flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs ${done ? "border-emerald-200 bg-emerald-500/10 text-emerald-600" : active ? "border-amber-200 bg-amber-500/10 text-amber-600" : "text-muted-foreground"}`}>
                                                    {done ? "✓" : `${i + 1}`} {step.name}
                                                </div>
                                                {i < (r.steps?.length ?? 0) - 1 && <span className="text-muted-foreground">→</span>}
                                            </div>
                                        );
                                    })}
                                </div>

                                {r.decision_note && (
                                    <p className="text-muted-foreground text-xs">Decision: {r.decision_note}{r.decided_at ? ` · ${r.decided_at}` : ""}</p>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </main>
        </FacultyLayout>
    );
}
