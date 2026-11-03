import { IconTile } from "@/components/reui/icon-tile";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { CustomStudentField } from "@/types/student-configuration";
import { Sparkles } from "lucide-react";

export function CustomStudentFields({
    fields,
    values,
    errors,
    onChange,
    editing = false,
}: {
    fields: CustomStudentField[];
    values: Record<string, string>;
    errors: Record<string, string | undefined>;
    onChange: (key: string, value: string) => void;
    editing?: boolean;
}) {
    const active = fields.filter((field) => field.active);
    if (active.length === 0) return null;

    return (
        <Card className="border-border/60 overflow-hidden transition-shadow duration-200">
            <CardHeader className="flex flex-row items-start gap-3 space-y-0 pb-4">
                <IconTile variant="soft" size="sm" className="mt-0.5">
                    <Sparkles className="size-4" />
                </IconTile>
                <div>
                    <CardTitle className="text-base font-semibold">Institution-specific information</CardTitle>
                    <CardDescription className="text-xs sm:text-sm">Attributes configured by your institution for student records.</CardDescription>
                </div>
            </CardHeader>
            <CardContent className="grid gap-4 md:grid-cols-2">
                {active.map((field) => {
                    const error = errors[`custom_fields.${field.key}`];
                    const value = values[field.key] ?? "";
                    const options = value && !field.options.includes(value) ? [...field.options, value] : field.options;
                    return (
                        <div key={field.key} className="flex flex-col gap-2">
                            <Label htmlFor={`custom-${field.key}`}>
                                {field.label} {field.required && (!editing || value !== "") && <span className="text-destructive">*</span>}
                            </Label>
                            {field.type === "choice" ? (
                                <Select value={value || "__none__"} onValueChange={(next) => onChange(field.key, next === "__none__" ? "" : next)}>
                                    <SelectTrigger
                                        id={`custom-${field.key}`}
                                        aria-invalid={Boolean(error)}
                                        aria-describedby={error ? `custom-${field.key}-error` : undefined}
                                    >
                                        <SelectValue placeholder="Choose an option" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {!field.required && <SelectItem value="__none__">No selection</SelectItem>}
                                        {options.map((option) => (
                                            <SelectItem key={option} value={option}>
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Input
                                    id={`custom-${field.key}`}
                                    type={field.type === "number" ? "number" : field.type === "date" ? "date" : "text"}
                                    value={value}
                                    aria-invalid={Boolean(error)}
                                    aria-describedby={error ? `custom-${field.key}-error` : undefined}
                                    onChange={(event) => onChange(field.key, event.target.value)}
                                />
                            )}
                            {error && (
                                <p id={`custom-${field.key}-error`} className="text-destructive text-sm">
                                    {error}
                                </p>
                            )}
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}
