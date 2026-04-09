import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { BookOpen, Save } from "lucide-react";

interface StudentEducationFormProps {
    studentForm: {
        data: {
            education: {
                elementary_school: string;
                elementary_year_graduated: string;
                high_school: string;
                high_school_year_graduated: string;
                senior_high_school: string;
                senior_high_year_graduated: string;
            };
        };
        setData: (key: string, value: any) => void;
        processing: boolean;
    };
    onSubmit: (e: React.FormEvent) => void;
}

export function StudentEducationForm({ studentForm, onSubmit }: StudentEducationFormProps) {
    const updateEducation = (key: string, value: string) => {
        studentForm.setData("education", {
            ...studentForm.data.education,
            [key]: value,
        });
    };

    return (
        <Card className="mt-6">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <BookOpen className="h-5 w-5" />
                    Education History
                </CardTitle>
                <CardDescription>Your educational background</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={onSubmit} className="space-y-6">
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <h3 className="mb-2 font-semibold">Elementary</h3>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="elementary_school">School Name</Label>
                            <Input
                                id="elementary_school"
                                value={studentForm.data.education.elementary_school}
                                onChange={(e) => updateEducation("elementary_school", e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="elementary_year_graduated">Year Graduated</Label>
                            <Input
                                id="elementary_year_graduated"
                                value={studentForm.data.education.elementary_year_graduated}
                                onChange={(e) => updateEducation("elementary_year_graduated", e.target.value)}
                            />
                        </div>

                        <div className="mt-4 md:col-span-2">
                            <h3 className="mb-2 font-semibold">High School</h3>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="high_school">School Name</Label>
                            <Input
                                id="high_school"
                                value={studentForm.data.education.high_school}
                                onChange={(e) => updateEducation("high_school", e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="high_school_year_graduated">Year Graduated</Label>
                            <Input
                                id="high_school_year_graduated"
                                value={studentForm.data.education.high_school_year_graduated}
                                onChange={(e) => updateEducation("high_school_year_graduated", e.target.value)}
                            />
                        </div>

                        <div className="mt-4 md:col-span-2">
                            <h3 className="mb-2 font-semibold">Senior High School</h3>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="senior_high_school">School Name</Label>
                            <Input
                                id="senior_high_school"
                                value={studentForm.data.education.senior_high_school}
                                onChange={(e) => updateEducation("senior_high_school", e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="senior_high_year_graduated">Year Graduated</Label>
                            <Input
                                id="senior_high_year_graduated"
                                value={studentForm.data.education.senior_high_year_graduated}
                                onChange={(e) => updateEducation("senior_high_year_graduated", e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={studentForm.processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {studentForm.processing ? "Saving..." : "Save Education Info"}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
