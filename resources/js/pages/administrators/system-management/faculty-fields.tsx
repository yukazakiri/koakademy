import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { router, useForm } from "@inertiajs/react";
import {
    AlertTriangle,
    ChevronRight,
    EyeOff,
    FileSpreadsheet,
    Fingerprint,
    LockKeyhole,
    Pencil,
    Plus,
    Save,
    ShieldCheck,
    SlidersHorizontal,
} from "lucide-react";
import { FormEvent, useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

import SystemManagementLayout from "./layout";
import type { FacultyFieldDefinition, FacultyFieldType, SystemManagementPageProps } from "./types";

interface FacultyFieldFormData {
    label: string;
    key: string;
    field_type: FacultyFieldType;
    is_required: boolean;
    is_sensitive: boolean;
    help_text: string;
    options: string[];
    source_header_aliases: string[];
    display_order: number;
}

const FIELD_TYPES: Array<{ value: FacultyFieldType; label: string; description: string }> = [
    { value: "text", label: "Text", description: "Names, codes, and other free-form values." },
    { value: "date", label: "Date", description: "Employment, expiry, or verification dates." },
    { value: "number", label: "Number", description: "Numeric identifiers without free-form text." },
    { value: "select", label: "Select", description: "A controlled value from the supplied option list." },
];

const EMPTY_FORM: FacultyFieldFormData = {
    label: "",
    key: "",
    field_type: "text",
    is_required: false,
    is_sensitive: true,
    help_text: "",
    options: [],
    source_header_aliases: [],
    display_order: 0,
};

function toStableKey(value: string): string {
    return value
        .trim()
        .toLocaleLowerCase()
        .replace(/[^a-z0-9]+/g, "_")
        .replace(/^_+|_+$/g, "")
        .slice(0, 64);
}

function toList(value: string): string[] {
    return Array.from(
        new Set(
            value
                .split(/[\n,]+/)
                .map((item) => item.trim())
                .filter(Boolean),
        ),
    );
}

function definitionToForm(definition: FacultyFieldDefinition): FacultyFieldFormData {
    return {
        label: definition.label,
        key: definition.key,
        field_type: definition.field_type,
        is_required: definition.is_required,
        is_sensitive: definition.is_sensitive,
        help_text: definition.help_text ?? "",
        options: definition.options,
        source_header_aliases: definition.source_header_aliases,
        display_order: definition.display_order,
    };
}

function FieldTypeBadge({ type }: { type: FacultyFieldType }) {
    return <Badge variant="secondary">{FIELD_TYPES.find((fieldType) => fieldType.value === type)?.label ?? type}</Badge>;
}

interface FieldEditorDialogProps {
    definition: FacultyFieldDefinition | null;
    nextOrder: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    canUpdate: boolean;
}

function FieldEditorDialog({ definition, nextOrder, open, onOpenChange, canUpdate }: FieldEditorDialogProps) {
    const [keyEdited, setKeyEdited] = useState(false);
    const form = useForm<FacultyFieldFormData>(definition ? definitionToForm(definition) : { ...EMPTY_FORM, display_order: nextOrder });
    const isEditing = definition !== null;

    const resetFor = (nextDefinition: FacultyFieldDefinition | null): void => {
        form.clearErrors();
        form.setData(nextDefinition ? definitionToForm(nextDefinition) : { ...EMPTY_FORM, display_order: nextOrder });
        setKeyEdited(false);
    };

    useEffect(() => {
        if (open) {
            resetFor(definition);
        }
    }, [definition?.id, nextOrder, open]);

    const handleOpenChange = (nextOpen: boolean): void => {
        if (!nextOpen) {
            form.reset();
            form.clearErrors();
        }

        onOpenChange(nextOpen);
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(isEditing ? "Faculty field updated." : "Faculty field added.");
                handleOpenChange(false);
            },
            onError: () => toast.error("Review the field details and try again."),
        };

        if (definition) {
            form.put(route("administrators.system-management.faculty-fields.update", definition.id), options);
            return;
        }

        form.post(route("administrators.system-management.faculty-fields.store"), options);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{isEditing ? "Edit faculty field" : "Add faculty field"}</DialogTitle>
                    <DialogDescription>
                        This definition controls a protected faculty value and the matching column in future import templates.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="faculty-field-label">Field label</Label>
                            <Input
                                id="faculty-field-label"
                                value={form.data.label}
                                disabled={!canUpdate || form.processing}
                                placeholder="National insurance number"
                                onChange={(event) => {
                                    const label = event.target.value;
                                    form.setData("label", label);

                                    if (!isEditing && !keyEdited) {
                                        form.setData("key", toStableKey(label));
                                    }
                                }}
                            />
                            {form.errors.label ? <p className="text-destructive text-sm">{form.errors.label}</p> : null}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="faculty-field-key">Stable key</Label>
                            <Input
                                id="faculty-field-key"
                                value={form.data.key}
                                disabled={isEditing || !canUpdate || form.processing}
                                placeholder="national_insurance_number"
                                onChange={(event) => {
                                    setKeyEdited(true);
                                    form.setData("key", toStableKey(event.target.value));
                                }}
                            />
                            <p className="text-muted-foreground text-xs">
                                {isEditing
                                    ? "A field key cannot change after values have been collected."
                                    : "Used internally and cannot be changed later."}
                            </p>
                            {form.errors.key ? <p className="text-destructive text-sm">{form.errors.key}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Value type</Label>
                            <Select
                                value={form.data.field_type}
                                disabled={!canUpdate || form.processing}
                                onValueChange={(value) => form.setData("field_type", value as FacultyFieldType)}
                            >
                                <SelectTrigger aria-label="Value type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {FIELD_TYPES.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-muted-foreground text-xs">
                                {FIELD_TYPES.find((type) => type.value === form.data.field_type)?.description}
                            </p>
                            {form.errors.field_type ? <p className="text-destructive text-sm">{form.errors.field_type}</p> : null}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="faculty-field-order">Display order</Label>
                            <Input
                                id="faculty-field-order"
                                type="number"
                                min="0"
                                value={form.data.display_order}
                                disabled={!canUpdate || form.processing}
                                onChange={(event) => form.setData("display_order", Number(event.target.value || 0))}
                            />
                            <p className="text-muted-foreground text-xs">Lower values appear first in the template and faculty record.</p>
                            {form.errors.display_order ? <p className="text-destructive text-sm">{form.errors.display_order}</p> : null}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="faculty-field-help">Help text</Label>
                        <Textarea
                            id="faculty-field-help"
                            value={form.data.help_text}
                            disabled={!canUpdate || form.processing}
                            placeholder="Explain why this field is collected or where staff can find it."
                            onChange={(event) => form.setData("help_text", event.target.value)}
                        />
                        {form.errors.help_text ? <p className="text-destructive text-sm">{form.errors.help_text}</p> : null}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="faculty-field-aliases">Import header aliases</Label>
                        <Textarea
                            id="faculty-field-aliases"
                            value={form.data.source_header_aliases.join(", ")}
                            disabled={!canUpdate || form.processing}
                            placeholder="National Insurance No., NINO, NI Number"
                            onChange={(event) => form.setData("source_header_aliases", toList(event.target.value))}
                        />
                        <p className="text-muted-foreground text-xs">
                            Separate aliases with commas or new lines. Importers use these to match legacy spreadsheet and Access headers.
                        </p>
                        {form.errors.source_header_aliases ? <p className="text-destructive text-sm">{form.errors.source_header_aliases}</p> : null}
                    </div>

                    {form.data.field_type === "select" ? (
                        <div className="space-y-2">
                            <Label htmlFor="faculty-field-options">Allowed options</Label>
                            <Textarea
                                id="faculty-field-options"
                                value={form.data.options.join("\n")}
                                disabled={!canUpdate || form.processing}
                                placeholder={"Active\nExpired\nPending"}
                                onChange={(event) => form.setData("options", toList(event.target.value))}
                            />
                            <p className="text-muted-foreground text-xs">One option per line. Imported values must match one of these options.</p>
                            {form.errors.options ? <p className="text-destructive text-sm">{form.errors.options}</p> : null}
                        </div>
                    ) : null}

                    <div className="bg-muted/20 grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
                        <label className="flex cursor-pointer items-start gap-3">
                            <Switch
                                checked={form.data.is_required}
                                disabled={!canUpdate || form.processing}
                                onCheckedChange={(checked) => form.setData("is_required", checked)}
                                aria-label="Require this field"
                            />
                            <span>
                                <span className="text-sm font-medium">Required</span>
                                <span className="text-muted-foreground mt-0.5 block text-xs leading-5">
                                    Each new faculty record and import row must include it.
                                </span>
                            </span>
                        </label>
                        <label className="flex cursor-pointer items-start gap-3">
                            <Switch
                                checked={form.data.is_sensitive}
                                disabled={!canUpdate || form.processing}
                                onCheckedChange={(checked) => form.setData("is_sensitive", checked)}
                                aria-label="Treat this field as sensitive"
                            />
                            <span>
                                <span className="text-sm font-medium">Sensitive</span>
                                <span className="text-muted-foreground mt-0.5 block text-xs leading-5">
                                    Values are protected and masked outside authorized record views.
                                </span>
                            </span>
                        </label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => handleOpenChange(false)} disabled={form.processing}>
                            Cancel
                        </Button>
                        <Button type="submit" className="gap-2" disabled={!canUpdate || form.processing}>
                            <Save className="size-4" />
                            {form.processing ? "Saving…" : isEditing ? "Save changes" : "Add field"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function SystemManagementFacultyFieldsPage({
    user,
    access,
    field_definitions,
}: SystemManagementPageProps & { field_definitions: FacultyFieldDefinition[] }) {
    const [editorOpen, setEditorOpen] = useState(false);
    const [editingDefinition, setEditingDefinition] = useState<FacultyFieldDefinition | null>(null);
    const [definitionToDeactivate, setDefinitionToDeactivate] = useState<FacultyFieldDefinition | null>(null);
    const canUpdate = access.sections.faculty_fields?.can_update ?? false;
    const activeDefinitions = useMemo(() => field_definitions.filter((definition) => definition.is_active), [field_definitions]);
    const inactiveDefinitions = useMemo(() => field_definitions.filter((definition) => !definition.is_active), [field_definitions]);
    const nextOrder = activeDefinitions.reduce((highest, definition) => Math.max(highest, definition.display_order), -1) + 1;

    const openCreate = (): void => {
        setEditingDefinition(null);
        setEditorOpen(true);
    };

    const openEdit = (definition: FacultyFieldDefinition): void => {
        setEditingDefinition(definition);
        setEditorOpen(true);
    };

    const deactivate = (): void => {
        if (!definitionToDeactivate) return;

        router.delete(route("administrators.system-management.faculty-fields.destroy", definitionToDeactivate.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${definitionToDeactivate.label} is no longer included in new templates.`);
                setDefinitionToDeactivate(null);
            },
            onError: () => toast.error("The faculty field could not be deactivated."),
        });
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="faculty_fields"
            heading="Faculty Fields"
            description="Configure country-appropriate staff fields once, then reuse them safely in faculty records and bulk-import templates."
        >
            <Card className="border-primary/15 from-primary/8 via-card to-card overflow-hidden bg-gradient-to-br">
                <CardContent className="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div className="flex gap-4">
                        <span className="bg-primary text-primary-foreground flex size-10 shrink-0 items-center justify-center rounded-xl shadow-sm">
                            <Fingerprint className="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <p className="text-foreground font-semibold">Built for your institution, not one country.</p>
                            <p className="text-muted-foreground mt-1 max-w-2xl text-sm leading-6">
                                Add only the fields your school needs—from government identifiers to regional employment requirements. Sensitive
                                values stay out of directory tables and import history.
                            </p>
                        </div>
                    </div>
                    <Button onClick={openCreate} disabled={!canUpdate} className="shrink-0 gap-2">
                        <Plus className="size-4" />
                        Add faculty field
                    </Button>
                </CardContent>
            </Card>

            <section aria-labelledby="active-faculty-fields" className="space-y-3">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 id="active-faculty-fields" className="text-lg font-semibold tracking-[-0.015em]">
                            Active fields
                        </h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            These appear as protected columns in the next downloadable faculty template.
                        </p>
                    </div>
                    <Badge variant="outline" className="w-fit tabular-nums">
                        {activeDefinitions.length} active
                    </Badge>
                </div>

                {activeDefinitions.length > 0 ? (
                    <div className="bg-card overflow-hidden rounded-xl border">
                        <div className="bg-muted/30 text-muted-foreground hidden grid-cols-[minmax(13rem,1.15fr)_minmax(11rem,0.85fr)_minmax(12rem,0.9fr)_auto] gap-4 border-b px-5 py-3 text-xs font-semibold tracking-[0.06em] uppercase lg:grid">
                            <span>Field</span>
                            <span>Import matching</span>
                            <span>Protection & validation</span>
                            <span className="text-right">Actions</span>
                        </div>
                        <div className="divide-y">
                            {activeDefinitions.map((definition) => (
                                <article
                                    key={definition.id}
                                    className="grid gap-4 px-5 py-5 lg:grid-cols-[minmax(13rem,1.15fr)_minmax(11rem,0.85fr)_minmax(12rem,0.9fr)_auto] lg:items-center"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-medium">{definition.label}</h3>
                                            <FieldTypeBadge type={definition.field_type} />
                                            {definition.is_required ? <Badge variant="outline">Required</Badge> : null}
                                        </div>
                                        <p className="text-muted-foreground mt-1 font-mono text-xs">{definition.key}</p>
                                        {definition.help_text ? (
                                            <p className="text-muted-foreground mt-2 text-sm leading-5">{definition.help_text}</p>
                                        ) : null}
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs font-semibold tracking-[0.06em] uppercase lg:hidden">
                                            Import matching
                                        </p>
                                        {definition.source_header_aliases.length > 0 ? (
                                            <p className="text-sm leading-6">{definition.source_header_aliases.join(", ")}</p>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">Matches its template heading only.</p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {definition.is_sensitive ? (
                                            <Badge variant="secondary" className="gap-1">
                                                <LockKeyhole className="size-3" /> Sensitive
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">Standard access</Badge>
                                        )}
                                        {definition.field_type === "select" ? (
                                            <Badge variant="outline">{definition.options.length} options</Badge>
                                        ) : null}
                                        <Badge variant="outline">Order {definition.display_order}</Badge>
                                    </div>
                                    <div className="flex items-center gap-2 lg:justify-end">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="gap-1.5"
                                            onClick={() => openEdit(definition)}
                                            disabled={!canUpdate}
                                        >
                                            <Pencil className="size-3.5" /> Edit
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="text-muted-foreground hover:text-destructive"
                                            onClick={() => setDefinitionToDeactivate(definition)}
                                            disabled={!canUpdate}
                                        >
                                            Deactivate
                                        </Button>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </div>
                ) : (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center px-6 py-12 text-center">
                            <span className="bg-muted flex size-11 items-center justify-center rounded-xl">
                                <SlidersHorizontal className="text-muted-foreground size-5" />
                            </span>
                            <h3 className="mt-4 font-semibold">No active custom faculty fields</h3>
                            <p className="text-muted-foreground mt-1 max-w-md text-sm leading-6">
                                Add the identifiers or employment details that apply to your school. You can change their import aliases as local
                                formats evolve.
                            </p>
                            <Button className="mt-5 gap-2" onClick={openCreate} disabled={!canUpdate}>
                                <Plus className="size-4" />
                                Add first field
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </section>

            <section className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(17rem,0.55fr)]">
                {inactiveDefinitions.length > 0 ? (
                    <Card>
                        <CardHeader className="pb-4">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <EyeOff className="text-muted-foreground size-4" />
                                Inactive fields
                            </CardTitle>
                            <CardDescription>These are retained for existing records but excluded from new templates and imports.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            {inactiveDefinitions.map((definition) => (
                                <Badge key={definition.id} variant="outline">
                                    {definition.label}
                                </Badge>
                            ))}
                        </CardContent>
                    </Card>
                ) : null}
                <Card className={cn(inactiveDefinitions.length === 0 && "xl:col-start-2")}>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileSpreadsheet className="text-primary size-4" />
                            Template behavior
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="text-muted-foreground space-y-3 text-sm leading-6">
                        <p className="flex gap-2">
                            <ChevronRight className="text-primary mt-1 size-3.5 shrink-0" />
                            Fixed profile columns stay consistent; active fields are appended automatically.
                        </p>
                        <p className="flex gap-2">
                            <ShieldCheck className="text-primary mt-1 size-3.5 shrink-0" />
                            Sensitive values are encrypted and masked in previews and audit logs.
                        </p>
                    </CardContent>
                </Card>
            </section>

            <FieldEditorDialog
                key={editingDefinition?.id ?? "new"}
                definition={editingDefinition}
                nextOrder={nextOrder}
                open={editorOpen}
                onOpenChange={setEditorOpen}
                canUpdate={canUpdate}
            />

            <AlertDialog open={definitionToDeactivate !== null} onOpenChange={(open) => !open && setDefinitionToDeactivate(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <AlertTriangle className="size-5 text-amber-500" />
                            Deactivate this faculty field?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {definitionToDeactivate
                                ? `${definitionToDeactivate.label} will be removed from future templates and import validation. Existing protected values are retained.`
                                : ""}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={deactivate} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Deactivate field
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </SystemManagementLayout>
    );
}
