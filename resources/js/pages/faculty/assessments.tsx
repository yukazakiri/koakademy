import FacultyLayout from "@/components/faculty/faculty-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, useForm, router } from "@inertiajs/react";
import { route } from "ziggy-js";
import { useState } from "react";

interface ClassInfo { id: number; code: string; section: string; students: number }
interface Assessment {
    id: number; title: string; type: string; class_id: number; class_code: string;
    due_date: string | null; total_points: number | null; submitted: number; graded: number; has_rubric: boolean;
}
interface Criterion { title: string; points: number }
interface Rubric { id: number; title: string; description: string | null; criteria: { title: string; points: number }[]; total_points: number }
interface Props { user: User; classes: ClassInfo[]; assessments: Assessment[]; rubrics: Rubric[] }

export default function AssessmentsPage({ user, assessments, rubrics }: Props) {
    const [criteria, setCriteria] = useState<Criterion[]>([{ title: "", points: 10 }]);

    const rubricForm = useForm<{ title: string; description: string; criteria: Criterion[]; is_shared: boolean }>({
        title: "", description: "", criteria: [], is_shared: false,
    });

    const addCriteria = () => setCriteria([...criteria, { title: "", points: 10 }]);
    const updateCriterion = (i: number, patch: Partial<Criterion>) =>
        setCriteria(criteria.map((c, index) => (index === i ? { ...c, ...patch } : c)));
    const removeCriterion = (i: number) => setCriteria(criteria.filter((_, index) => index !== i));

    const deleteRubric = (id: number) => {
        router.delete(route("faculty.assessments.rubrics.destroy", id), { preserveScroll: true });
    };

    const totalPoints = criteria.reduce((sum, c) => sum + (Number(c.points) || 0), 0);

    const saveRubric = () => {
        rubricForm.setData("criteria", criteria.filter((c) => c.title.trim() !== ""));
        rubricForm.post(route("faculty.assessments.rubrics.store"), {
            preserveScroll: true,
            onSuccess: () => {
                rubricForm.reset();
                setCriteria([{ title: "", points: 10 }]);
            },
        });
    };

    const gradingQueue = assessments.filter((a) => a.submitted > a.graded);

    return (
        <FacultyLayout user={user}>
            <Head title="Assessments" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <header className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">Assessments</h1>
                    <p className="text-muted-foreground text-sm">Quizzes, assignments, rubrics, and your grading queue in one place.</p>
                </header>

                {gradingQueue.length > 0 && (
                    <Card className="border-amber-200 bg-amber-50/50">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">Grading queue — {gradingQueue.length} assessment{gradingQueue.length > 1 ? "s" : ""} with ungraded submissions</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            {gradingQueue.map((a) => (
                                <Badge key={a.id} variant="outline" className="bg-white font-normal">
                                    {a.class_code} · {a.title} ({a.submitted - a.graded} to grade)
                                </Badge>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader><CardTitle className="text-base">Assessments</CardTitle></CardHeader>
                    <CardContent className="space-y-2">
                        {assessments.length === 0 && <p className="text-muted-foreground text-sm">No assessments yet. Create assignments from any class page.</p>}
                        {assessments.map((a) => (
                            <div key={a.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3">
                                <div>
                                    <p className="text-sm font-medium">{a.title}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {a.class_code} · {a.type} · due {a.due_date ?? "—"} · {a.total_points ?? 100} pts{a.has_rubric ? " · rubric" : ""}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3 text-xs">
                                    <span className="text-muted-foreground">{a.submitted} submitted</span>
                                    <span className="text-muted-foreground">{a.graded} graded</span>
                                    {a.submitted > a.graded && <Badge className="bg-amber-500/10 text-amber-600">needs grading</Badge>}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader><CardTitle className="text-base">Rubric library</CardTitle></CardHeader>
                        <CardContent className="space-y-3">
                            {rubrics.map((r) => (
                                <div key={r.id} className="flex items-start justify-between gap-2 rounded-lg border p-3">
                                    <div>
                                        <p className="text-sm font-medium">{r.title} <span className="text-muted-foreground font-normal">· {r.total_points} pts</span></p>
                                        {r.description && <p className="text-muted-foreground text-xs">{r.description}</p>}
                                        <p className="text-muted-foreground mt-1 text-xs">{r.criteria.map((c) => `${c.title} (${c.points})`).join(" · ")}</p>
                                    </div>
                                    <Button size="sm" variant="ghost" className="text-destructive" onClick={() => deleteRubric(r.id)}>
                                        Delete
                                    </Button>
                                </div>
                            ))}
                            {rubrics.length === 0 && <p className="text-muted-foreground text-sm">No rubric templates yet.</p>}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle className="text-base">New rubric template</CardTitle></CardHeader>
                        <CardContent className="space-y-3">
                            <div className="space-y-1">
                                <Label>Title</Label>
                                <Input value={rubricForm.data.title} onChange={(e) => rubricForm.setData("title", e.target.value)} placeholder="Essay rubric" />
                                {rubricForm.errors.title && <p className="text-destructive text-xs">{rubricForm.errors.title}</p>}
                            </div>
                            <div className="space-y-1">
                                <Label>Description</Label>
                                <Input value={rubricForm.data.description} onChange={(e) => rubricForm.setData("description", e.target.value)} placeholder="Optional notes" />
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label>Criteria ({totalPoints} pts)</Label>
                                    <Button size="sm" variant="outline" onClick={addCriteria}>Add criterion</Button>
                                </div>
                                {criteria.map((c, i) => (
                                    <div key={i} className="flex items-center gap-2">
                                        <Input value={c.title} onChange={(e) => updateCriterion(i, { title: e.target.value })} placeholder="Criterion" />
                                        <Input type="number" min={0} className="w-24" value={c.points} onChange={(e) => updateCriterion(i, { points: Number(e.target.value) })} />
                                        <Button size="sm" variant="ghost" onClick={() => removeCriterion(i)} disabled={criteria.length === 1}>✕</Button>
                                    </div>
                                ))}
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={rubricForm.data.is_shared} onCheckedChange={(v) => rubricForm.setData("is_shared", Boolean(v))} />
                                Share with other faculty
                            </label>
                            <Button onClick={saveRubric} disabled={rubricForm.processing}>Save rubric</Button>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </FacultyLayout>
    );
}
