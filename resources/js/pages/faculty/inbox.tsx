import FacultyLayout from "@/components/faculty/faculty-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, useForm, router } from "@inertiajs/react";
import { route } from "ziggy-js";
import { useState } from "react";

interface ClassInfo { id: number; code: string; section: string; students: number }
interface Message { id: number; from: string; body: string; at: string; unread: boolean }
interface Thread {
    id: number; subject: string; status: string; class_id: number | null;
    student: string | null; messages: Message[]; unread: number; last_at: string | null;
}
interface Template { id: number; name: string; subject: string | null; body: string }
interface StudentRow { id: number; name: string; number: string }
interface Props { user: User; classes: ClassInfo[]; threads: Thread[]; templates: Template[]; roster: StudentRow[] }

export default function InboxPage({ user, classes, threads, templates, roster }: Props) {
    const [selectedId, setSelectedId] = useState<number | null>(threads[0]?.id ?? null);
    const [showCompose, setShowCompose] = useState(false);
    const selected = threads.find((t) => t.id === selectedId) ?? null;

    const replyForm = useForm<{ thread_id: string; body: string }>({ thread_id: "", body: "" });
    const composeForm = useForm<{ student_id: string; class_id: string; subject: string; body: string }>({
        student_id: "", class_id: "", subject: "", body: "",
    });
    const templateForm = useForm<{ name: string; subject: string; body: string }>({ name: "", subject: "", body: "" });

    const sendReply = () => {
        if (!selected) return;
        replyForm.setData("thread_id", String(selected.id));
        replyForm.post(route("faculty.inbox.messages.send"), {
            preserveScroll: true,
            onSuccess: () => replyForm.reset("body"),
        });
    };

    const newThread = () => {
        composeForm.post(route("faculty.inbox.messages.send"), {
            preserveScroll: true,
            onSuccess: () => {
                composeForm.reset();
                setShowCompose(false);
            },
        });
    };

    const applyTemplate = (tpl: Template) => {
        if (selected) {
            replyForm.setData("body", tpl.body);
        } else {
            setShowCompose(true);
            composeForm.setData("subject", tpl.subject ?? "");
            composeForm.setData("body", tpl.body);
        }
    };

    const markRead = (threadId: number) => {
        router.post(route("faculty.inbox.threads.read", threadId), {}, { preserveScroll: true });
    };

    const saveTemplate = () => {
        templateForm.post(route("faculty.inbox.templates.store"), {
            preserveScroll: true,
            onSuccess: () => templateForm.reset(),
        });
    };

    const deleteTemplate = (id: number) => {
        router.delete(route("faculty.inbox.templates.destroy", id), { preserveScroll: true });
    };

    return (
        <FacultyLayout user={user}>
            <Head title="Inbox" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Inbox</h1>
                        <p className="text-muted-foreground text-sm">Student conversations and reusable reply templates.</p>
                    </div>
                    <Button onClick={() => setShowCompose(!showCompose)}>{showCompose ? "Close" : "New message"}</Button>
                </header>

                {showCompose && (
                    <Card>
                        <CardHeader><CardTitle className="text-base">New message</CardTitle></CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label>To student</Label>
                                <select
                                    className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                                    value={composeForm.data.student_id}
                                    onChange={(e) => composeForm.setData("student_id", e.target.value)}
                                >
                                    <option value="">Select student…</option>
                                    {roster.map((s) => (
                                        <option key={s.id} value={s.id}>{s.name} ({s.number})</option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-1">
                                <Label>Class (optional)</Label>
                                <select
                                    className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                                    value={composeForm.data.class_id}
                                    onChange={(e) => composeForm.setData("class_id", e.target.value)}
                                >
                                    <option value="">—</option>
                                    {classes.map((c) => (
                                        <option key={c.id} value={c.id}>{c.code} · {c.section}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-1 sm:col-span-2">
                                <Label>Subject</Label>
                                <Input value={composeForm.data.subject} onChange={(e) => composeForm.setData("subject", e.target.value)} />
                                {composeForm.errors.subject && <p className="text-destructive text-xs">{composeForm.errors.subject}</p>}
                            </div>
                            <div className="space-y-1 sm:col-span-2">
                                <Label>Message</Label>
                                <Textarea rows={4} value={composeForm.data.body} onChange={(e) => composeForm.setData("body", e.target.value)} />
                                {composeForm.errors.body && <p className="text-destructive text-xs">{composeForm.errors.body}</p>}
                            </div>
                            <div className="sm:col-span-2">
                                <Button onClick={newThread} disabled={composeForm.processing}>Send</Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
                    <div className="space-y-2">
                        {threads.length === 0 && (
                            <Card><CardContent className="text-muted-foreground p-6 text-center text-sm">No conversations yet.</CardContent></Card>
                        )}
                        {threads.map((t) => (
                            <button
                                key={t.id}
                                onClick={() => { setSelectedId(t.id); if (t.unread > 0) markRead(t.id); }}
                                className={`w-full rounded-lg border p-3 text-left transition-colors ${t.id === selectedId ? "border-primary bg-primary/5" : "hover:bg-muted/40"}`}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <p className="truncate text-sm font-medium">{t.subject}</p>
                                    {t.unread > 0 && <Badge className="bg-red-500/10 text-red-600">{t.unread}</Badge>}
                                </div>
                                <p className="text-muted-foreground truncate text-xs">{t.student ?? "Student"} · {t.last_at}</p>
                            </button>
                        ))}
                    </div>

                    {selected && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">{selected.subject}</CardTitle>
                                <p className="text-muted-foreground text-xs">with {selected.student ?? "student"}</p>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="max-h-80 space-y-3 overflow-y-auto">
                                    {selected.messages.map((m) => (
                                        <div key={m.id} className={`rounded-lg border p-3 ${m.from === "faculty" ? "ml-12 bg-primary/5" : "mr-12"}`}>
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="font-medium">{m.from === "faculty" ? "You" : selected.student}</span>
                                                <span className="text-muted-foreground">{m.at}</span>
                                            </div>
                                            <p className="mt-1 text-sm whitespace-pre-wrap">{m.body}</p>
                                        </div>
                                    ))}
                                </div>
                                <div className="space-y-2 border-t pt-3">
                                    <Textarea rows={3} value={replyForm.data.body} onChange={(e) => replyForm.setData("body", e.target.value)} placeholder="Reply…" />
                                    {replyForm.errors.body && <p className="text-destructive text-xs">{replyForm.errors.body}</p>}
                                    <Button onClick={sendReply} disabled={replyForm.processing || replyForm.data.body.trim() === ""}>Send reply</Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Card>
                    <CardHeader><CardTitle className="text-base">Reply templates</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            {templates.map((tpl) => (
                                <div key={tpl.id} className="flex items-start justify-between gap-2 rounded-lg border p-3">
                                    <div>
                                        <p className="text-sm font-medium">{tpl.name}</p>
                                        <p className="text-muted-foreground line-clamp-2 text-xs">{tpl.body}</p>
                                    </div>
                                    <div className="flex shrink-0 gap-1">
                                        <Button size="sm" variant="outline" onClick={() => applyTemplate(tpl)}>Use</Button>
                                        <Button size="sm" variant="ghost" className="text-destructive" onClick={() => deleteTemplate(tpl.id)}>✕</Button>
                                    </div>
                                </div>
                            ))}
                            {templates.length === 0 && <p className="text-muted-foreground text-sm">No templates yet.</p>}
                        </div>
                        <div className="space-y-2">
                            <Input value={templateForm.data.name} onChange={(e) => templateForm.setData("name", e.target.value)} placeholder="Template name" />
                            <Input value={templateForm.data.subject} onChange={(e) => templateForm.setData("subject", e.target.value)} placeholder="Subject (optional)" />
                            <Textarea rows={3} value={templateForm.data.body} onChange={(e) => templateForm.setData("body", e.target.value)} placeholder="Template body" />
                            <Button onClick={saveTemplate} disabled={templateForm.processing}>Save template</Button>
                        </div>
                    </CardContent>
                </Card>
            </main>
        </FacultyLayout>
    );
}
