import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { SchoolAutocompleteInput } from "@/components/ui/school-autocomplete-input";
import { BookOpen, Save } from "lucide-react";

type EducationData = {
    elementary_school: string;
    elementary_year_graduated: string;
    high_school: string;
    high_school_year_graduated: string;
    senior_high_school: string;
    senior_high_year_graduated: string;
    college_school: string;
    college_course: string;
    college_year_graduated: string;
    vocational_school: string;
    vocational_course: string;
    vocational_year_graduated: string;
};

interface StudentEducationFormProps {
    studentForm: {
        data: StudentEducationFormData;
        setData: <Key extends keyof StudentEducationFormData>(key: Key, value: StudentEducationFormData[Key]) => void;
        errors: Record<string, string>;
        processing: boolean;
    };
    onSubmit: (event: React.FormEvent) => void;
    schoolOptionsEndpoint: string;
}

type StudentEducationFormData = {
    education: EducationData;
};

const schoolSections = [
    {
        title: "Elementary",
        schoolKey: "elementary_school",
        yearKey: "elementary_year_graduated",
        placeholder: "Search or enter elementary school",
    },
    {
        title: "Junior high school",
        schoolKey: "high_school",
        yearKey: "high_school_year_graduated",
        placeholder: "Search or enter junior high school",
    },
    {
        title: "Senior high school",
        schoolKey: "senior_high_school",
        yearKey: "senior_high_year_graduated",
        placeholder: "Search or enter senior high school",
    },
] as const satisfies ReadonlyArray<{
    title: string;
    schoolKey: keyof EducationData;
    yearKey: keyof EducationData;
    placeholder: string;
}>;

function FieldError({ message }: { message?: string }) {
    return message ? <p className="text-destructive text-sm">{message}</p> : null;
}

export function StudentEducationForm({ studentForm, onSubmit, schoolOptionsEndpoint }: StudentEducationFormProps) {
    const currentYear = new Date().getFullYear();
    const errorFor = (key: string) => studentForm.errors[key];

    const updateEducation = (key: keyof EducationData, value: string) => {
        studentForm.setData("education", {
            ...studentForm.data.education,
            [key]: value,
        });
    };

    return (
        <Card id="student-education" className="border-border/60 bg-card/75 scroll-mt-24 rounded-lg shadow-sm">
            <CardHeader className="pb-4">
                <CardTitle className="flex items-center gap-2">
                    <BookOpen className="h-5 w-5" />
                    Education History
                </CardTitle>
                <CardDescription>Search existing school records or enter a school that is not listed.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={onSubmit} className="space-y-6">
                    {schoolSections.map(({ title, schoolKey, yearKey, placeholder }) => {
                        const schoolError = errorFor(`education.${schoolKey}`);
                        const yearError = errorFor(`education.${yearKey}`);

                        return (
                            <section key={schoolKey} className="border-border/70 border-b pb-6 last:border-b-0 last:pb-0">
                                <h3 className="mb-3 text-sm font-semibold">{title}</h3>
                                <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_10rem]">
                                    <div className="min-w-0 space-y-2">
                                        <Label htmlFor={schoolKey}>School name</Label>
                                        <SchoolAutocompleteInput
                                            id={schoolKey}
                                            value={studentForm.data.education[schoolKey]}
                                            onChange={(value) => updateEducation(schoolKey, value)}
                                            fieldName={schoolKey}
                                            endpoint={schoolOptionsEndpoint}
                                            placeholder={placeholder}
                                            aria-invalid={Boolean(schoolError)}
                                        />
                                        <FieldError message={schoolError} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor={yearKey}>Year graduated</Label>
                                        <Input
                                            id={yearKey}
                                            type="number"
                                            inputMode="numeric"
                                            min={1900}
                                            max={currentYear}
                                            value={studentForm.data.education[yearKey]}
                                            onChange={(event) => updateEducation(yearKey, event.target.value)}
                                            aria-invalid={Boolean(yearError)}
                                            placeholder={String(currentYear)}
                                        />
                                        <FieldError message={yearError} />
                                    </div>
                                </div>
                            </section>
                        );
                    })}

                    <section className="border-border/70 space-y-4 border-t pt-6">
                        <div>
                            <h3 className="text-sm font-semibold">College or transferee history</h3>
                            <p className="text-muted-foreground mt-1 text-sm">Optional — complete this only if you attended another college.</p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            {(
                                [
                                    ["college_school", "Previous college"],
                                    ["college_course", "Previous course"],
                                    ["college_year_graduated", "Year graduated or last attended"],
                                ] as const
                            ).map(([key, label]) => (
                                <div key={key} className="space-y-2">
                                    <Label htmlFor={key}>{label}</Label>
                                    <Input
                                        id={key}
                                        value={studentForm.data.education[key]}
                                        onChange={(event) => updateEducation(key, event.target.value)}
                                        aria-invalid={Boolean(errorFor(`education.${key}`))}
                                    />
                                    <FieldError message={errorFor(`education.${key}`)} />
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="border-border/70 space-y-4 border-t pt-6">
                        <div>
                            <h3 className="text-sm font-semibold">Vocational education</h3>
                            <p className="text-muted-foreground mt-1 text-sm">Optional — include relevant technical or vocational training.</p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            {(
                                [
                                    ["vocational_school", "Vocational school"],
                                    ["vocational_course", "Course taken"],
                                    ["vocational_year_graduated", "Year graduated"],
                                ] as const
                            ).map(([key, label]) => (
                                <div key={key} className="space-y-2">
                                    <Label htmlFor={key}>{label}</Label>
                                    <Input
                                        id={key}
                                        value={studentForm.data.education[key]}
                                        onChange={(event) => updateEducation(key, event.target.value)}
                                        aria-invalid={Boolean(errorFor(`education.${key}`))}
                                    />
                                    <FieldError message={errorFor(`education.${key}`)} />
                                </div>
                            ))}
                        </div>
                    </section>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={studentForm.processing} className="rounded-lg">
                            <Save className="mr-2 h-4 w-4" />
                            {studentForm.processing ? "Saving..." : "Save education history"}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
