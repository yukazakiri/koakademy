import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { BarChart3, MapPinned, Save, ShieldCheck, UsersRound } from "lucide-react";

type IncomeMode = {
    value: string;
    label: string;
    brackets: Array<{ value: string; label: string }>;
};

type ReportingData = {
    ethnicity: string;
    city_of_origin: string;
    province_of_origin: string;
    region_of_origin: string;
    is_indigenous_person: boolean;
    indigenous_group: string;
    is_pwd: boolean;
    pwd_type: string;
    is_solo_parent: boolean;
    is_senior_citizen: boolean;
    is_magna_carta: boolean;
    is_underprivileged: boolean;
    is_first_generation: boolean;
    income_bracket_mode: string;
    use_same_parent_income: boolean;
    family_income_bracket: string;
    father_income_bracket: string;
    mother_income_bracket: string;
};

interface StudentReportingFormProps {
    studentForm: {
        data: ReportingData;
        setData: <Key extends keyof ReportingData>(key: Key, value: ReportingData[Key]) => void;
        errors: Record<string, string>;
        processing: boolean;
    };
    incomeModes: IncomeMode[];
    onSubmit: (event: React.FormEvent) => void;
}

function FieldError({ message }: { message?: string }) {
    return message ? <p className="text-destructive text-sm">{message}</p> : null;
}

export function StudentReportingForm({ studentForm, incomeModes, onSubmit }: StudentReportingFormProps) {
    const reporting = studentForm.data;
    const activeMode = incomeModes.find((mode) => mode.value === reporting.income_bracket_mode) ?? incomeModes[0];

    const updateBoolean = (
        key: keyof Pick<
            ReportingData,
            | "is_indigenous_person"
            | "is_pwd"
            | "is_solo_parent"
            | "is_senior_citizen"
            | "is_magna_carta"
            | "is_underprivileged"
            | "is_first_generation"
        >,
        checked: boolean,
    ) => {
        studentForm.setData(key, checked);
        if (key === "is_indigenous_person" && !checked) studentForm.setData("indigenous_group", "");
        if (key === "is_pwd" && !checked) studentForm.setData("pwd_type", "");
    };

    const updateIncomeMode = (value: string) => {
        studentForm.setData("income_bracket_mode", value);
        studentForm.setData("family_income_bracket", "");
        studentForm.setData("father_income_bracket", "");
        studentForm.setData("mother_income_bracket", "");
    };

    return (
        <Card id="student-reporting" className="border-border/60 bg-card/75 scroll-mt-24 rounded-lg shadow-sm">
            <CardHeader className="pb-4">
                <CardTitle className="flex items-center gap-2">
                    <BarChart3 className="h-5 w-5" />
                    Reporting information
                </CardTitle>
                <CardDescription>These details support institutional reporting, student services, and equitable access to programs.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={onSubmit} className="space-y-7">
                    <section className="space-y-4">
                        <div className="flex items-center gap-2">
                            <MapPinned className="text-primary h-4 w-4" />
                            <h3 className="text-sm font-semibold">Background and origin</h3>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            {(
                                [
                                    ["ethnicity", "Ethnicity", "e.g. Cebuano"],
                                    ["city_of_origin", "City of origin", "City or municipality"],
                                    ["province_of_origin", "Province of origin", "Province"],
                                    ["region_of_origin", "Region of origin", "Region"],
                                ] as const
                            ).map(([key, label, placeholder]) => (
                                <div key={key} className="space-y-2">
                                    <Label htmlFor={key}>{label}</Label>
                                    <Input
                                        id={key}
                                        value={reporting[key]}
                                        onChange={(event) => studentForm.setData(key, event.target.value)}
                                        placeholder={placeholder}
                                        aria-invalid={Boolean(studentForm.errors[key])}
                                    />
                                    <FieldError message={studentForm.errors[key]} />
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="space-y-4 border-t pt-6">
                        <div className="flex items-center gap-2">
                            <UsersRound className="text-primary h-4 w-4" />
                            <h3 className="text-sm font-semibold">Equity and support groups</h3>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Select every category that applies. Leave each unchecked when it does not apply to you.
                        </p>
                        <div className="grid gap-3 md:grid-cols-2">
                            {(
                                [
                                    ["is_indigenous_person", "Indigenous Person (IP)"],
                                    ["is_pwd", "Person with Disability (PWD)"],
                                    ["is_solo_parent", "Solo parent"],
                                    ["is_senior_citizen", "Senior citizen"],
                                    ["is_magna_carta", "Magna Carta beneficiary"],
                                    ["is_underprivileged", "Underprivileged household"],
                                    ["is_first_generation", "First-generation student"],
                                ] as const
                            ).map(([key, label]) => (
                                <label
                                    key={key}
                                    htmlFor={key}
                                    className="border-border/70 hover:bg-muted/35 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors"
                                >
                                    <Checkbox id={key} checked={reporting[key]} onCheckedChange={(checked) => updateBoolean(key, checked === true)} />
                                    <span className="text-sm font-medium">{label}</span>
                                </label>
                            ))}
                        </div>
                        {reporting.is_indigenous_person && (
                            <div className="max-w-md space-y-2">
                                <Label htmlFor="indigenous_group">Indigenous group</Label>
                                <Input
                                    id="indigenous_group"
                                    value={reporting.indigenous_group}
                                    onChange={(event) => studentForm.setData("indigenous_group", event.target.value)}
                                    aria-invalid={Boolean(studentForm.errors.indigenous_group)}
                                />
                                <FieldError message={studentForm.errors.indigenous_group} />
                            </div>
                        )}
                        {reporting.is_pwd && (
                            <div className="max-w-md space-y-2">
                                <Label htmlFor="pwd_type">Type of disability</Label>
                                <Input
                                    id="pwd_type"
                                    value={reporting.pwd_type}
                                    onChange={(event) => studentForm.setData("pwd_type", event.target.value)}
                                    aria-invalid={Boolean(studentForm.errors.pwd_type)}
                                />
                                <FieldError message={studentForm.errors.pwd_type} />
                            </div>
                        )}
                    </section>

                    <section className="space-y-4 border-t pt-6">
                        <h3 className="text-sm font-semibold">Household income</h3>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="income_bracket_mode">Income basis</Label>
                                <Select value={reporting.income_bracket_mode} onValueChange={updateIncomeMode}>
                                    <SelectTrigger id="income_bracket_mode">
                                        <SelectValue placeholder="Select income basis" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {incomeModes.map((mode) => (
                                            <SelectItem key={mode.value} value={mode.value}>
                                                {mode.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <label
                                htmlFor="use_same_parent_income"
                                className="border-border/70 flex cursor-pointer items-center gap-3 self-end rounded-lg border p-3"
                            >
                                <Checkbox
                                    id="use_same_parent_income"
                                    checked={reporting.use_same_parent_income}
                                    onCheckedChange={(checked) => {
                                        const useSame = checked === true;
                                        studentForm.setData("use_same_parent_income", useSame);
                                        if (useSame) {
                                            studentForm.setData("father_income_bracket", "");
                                            studentForm.setData("mother_income_bracket", "");
                                        } else {
                                            studentForm.setData("family_income_bracket", "");
                                        }
                                    }}
                                />
                                <span className="text-sm font-medium">Use one household income bracket</span>
                            </label>
                        </div>
                        {reporting.use_same_parent_income ? (
                            <div className="max-w-md space-y-2">
                                <Label htmlFor="family_income_bracket">Household income bracket</Label>
                                <Select
                                    value={reporting.family_income_bracket}
                                    onValueChange={(value) => studentForm.setData("family_income_bracket", value)}
                                >
                                    <SelectTrigger id="family_income_bracket" aria-invalid={Boolean(studentForm.errors.family_income_bracket)}>
                                        <SelectValue placeholder="Select bracket" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {activeMode?.brackets.map((bracket) => (
                                            <SelectItem key={bracket.value} value={bracket.value}>
                                                {bracket.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldError message={studentForm.errors.family_income_bracket} />
                            </div>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2">
                                {(["father_income_bracket", "mother_income_bracket"] as const).map((key) => (
                                    <div key={key} className="space-y-2">
                                        <Label htmlFor={key}>
                                            {key.startsWith("father") ? "Father's income bracket" : "Mother's income bracket"}
                                        </Label>
                                        <Select value={reporting[key]} onValueChange={(value) => studentForm.setData(key, value)}>
                                            <SelectTrigger id={key} aria-invalid={Boolean(studentForm.errors[key])}>
                                                <SelectValue placeholder="Select bracket" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {activeMode?.brackets.map((bracket) => (
                                                    <SelectItem key={bracket.value} value={bracket.value}>
                                                        {bracket.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <FieldError message={studentForm.errors[key]} />
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    <div className="border-primary/20 bg-primary/5 flex gap-3 rounded-lg border p-4 text-sm">
                        <ShieldCheck className="text-primary mt-0.5 h-5 w-5 shrink-0" />
                        <p>I confirm that I reviewed the reporting information above and that it is accurate to the best of my knowledge.</p>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={studentForm.processing} className="rounded-lg">
                            <Save className="mr-2 h-4 w-4" />
                            {studentForm.processing ? "Saving..." : "Save reporting information"}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
