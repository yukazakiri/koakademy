import { Card } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Award, BookCheck, GraduationCap } from "lucide-react";

interface StudentGradesTabProps {
    grades: {
        prelim?: number;
        midterm?: number;
        final?: number;
        average?: number;
        final_status?: "released" | "clearance_required" | "not_released";
    };
}

export function StudentGradesTab({ grades }: StudentGradesTabProps) {
    const terms = [
        { label: "Prelim", value: grades.prelim },
        { label: "Midterm", value: grades.midterm },
        { label: "Final", value: grades.final },
    ];
    const available = terms.filter((term) => term.value !== null && term.value !== undefined);
    const average = grades.average;
    const finalStatus =
        grades.final_status === "clearance_required"
            ? "Clearance required before final grades can be released."
            : "Final grades have not yet been released.";

    return (
        <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
            <Card className="border-border/70 rounded-lg p-5 shadow-sm">
                <div className="flex items-center gap-3">
                    <div className="bg-primary/10 text-primary flex size-11 items-center justify-center rounded-md">
                        <GraduationCap className="size-5" />
                    </div>
                    <div>
                        <p className="text-muted-foreground text-xs">Current average</p>
                        <p className="text-2xl font-semibold">{average ?? "Not available"}</p>
                    </div>
                </div>
                {average !== null && average !== undefined && <Progress value={Math.max(0, Math.min(100, average))} className="mt-5 h-2" />}
            </Card>

            <Card className="border-border/70 overflow-hidden rounded-lg shadow-sm">
                <div className="border-border/70 flex items-center gap-2 border-b px-4 py-3">
                    <BookCheck className="text-primary size-4" />
                    <h2 className="text-sm font-semibold">Term grades</h2>
                </div>
                {available.length > 0 ? (
                    <div className="grid divide-y sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        {terms.map((term) => (
                            <div key={term.label} className="flex items-center justify-between px-4 py-4 sm:block sm:text-center">
                                <p className="text-muted-foreground text-sm">{term.label}</p>
                                <p className="mt-1 text-xl font-semibold">{term.value ?? "-"}</p>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="text-muted-foreground flex min-h-40 flex-col items-center justify-center px-6 text-center text-sm">
                        <Award className="mb-3 size-7" />
                        Your instructor has not published grades yet.
                    </div>
                )}
                {grades.final_status && grades.final_status !== "released" && (
                    <p className="text-muted-foreground px-4 pb-4 text-sm">{finalStatus}</p>
                )}
            </Card>
        </div>
    );
}
