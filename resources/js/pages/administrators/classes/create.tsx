import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { create, index, show } from "@/routes/administrators/classes";
import type { User } from "@/types/user";
import { Head, Link, usePage } from "@inertiajs/react";
import { ArrowLeft, ArrowUpRight, BookOpen, Calendar, GraduationCap, Layers, Table2 } from "lucide-react";
import { useState, type ReactNode } from "react";
import { BlueprintMatrixWorkbench } from "./blueprint/blueprint-matrix-workbench";
import { SplitComposerStudio } from "./composer/split-composer-studio";
import type { BlueprintSection, EditClassMeta, SiblingSection } from "./crud/types";
import { canonicalDeliveryMode, type ClassFormData, type ClassFormOptions } from "./lib/class-defaults";

type MinimalDefaults = {
    semester: "1" | "2" | "summer";
    school_year: string;
    delivery_mode?: string;
};

type CreateClassProps = {
    user: User;
    filament: {
        classes: {
            index_url: string;
        };
    };
    options: ClassFormOptions;
    defaults: ClassFormData | MinimalDefaults;
    mode?: "create" | "edit";
    initial_mode?: "single" | "batch";
    duplicate_from?: number | null;
    class_id?: number;
    class_title?: string;
    edit_meta?: EditClassMeta | null;
    class_meta?: EditClassMeta | null;
    sibling_sections?: SiblingSection[];
    blueprint_sections?: BlueprintSection[];
    flash: { type: string; message: string; class_id?: number } | null;
};

function isFullClassFormData(value: ClassFormData | MinimalDefaults): value is ClassFormData {
    return "classification" in value && "schedules" in value && "settings" in value;
}

export default function AdministratorClassCreate({
    user,
    options,
    defaults,
    mode = "create",
    initial_mode = "single",
    class_id,
    class_title,
    edit_meta = null,
    class_meta = null,
    sibling_sections = [],
    blueprint_sections = [],
    flash,
}: CreateClassProps): ReactNode {
    const isEditing = mode === "edit";
    const pageUrl = usePage().url;
    const searchParams = new URL(pageUrl, "http://localhost").searchParams;
    const urlMode = searchParams.get("mode");
    const stepParam = searchParams.get("step");

    const initialFocus: "details" | "schedule" | "settings" =
        stepParam === "schedule" ? "schedule" : stepParam === "settings" ? "settings" : "details";

    const [createMode, setCreateMode] = useState<"single" | "batch">(() =>
        !isEditing && (urlMode === "batch" || initial_mode === "batch") ? "batch" : "single",
    );
    const [visitedModes, setVisitedModes] = useState(() => new Set([createMode]));
    const switchMode = (next: "single" | "batch") => {
        setVisitedModes((previous) => new Set([...previous, next]));
        setCreateMode(next);
    };

    const semester = defaults.semester;
    const schoolYear = defaults.school_year;
    const initialData = isFullClassFormData(defaults) ? defaults : undefined;
    const resolvedEditMeta = edit_meta ?? class_meta ?? null;
    const termLabel = options.semesters.find((term) => term.value === semester)?.label ?? semester;

    const pageTitle = isEditing
        ? `Edit class • ${class_title ?? "Class"}`
        : createMode === "batch"
          ? "Batch create sections"
          : "Create new class";

    return (
        <AdminLayout user={user} title={isEditing ? "Edit class" : createMode === "batch" ? "Batch sections" : "Create class"}>
            <Head title={pageTitle} />

            <div className="mx-auto flex w-full max-w-screen-2xl flex-col gap-5 pb-12">
                {/* Header Banner */}
                <header className="bg-card relative overflow-hidden rounded-2xl border p-5 shadow-xs sm:p-6">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="flex min-w-0 flex-col gap-2.5">
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    nativeButton={false}
                                    render={<Link href={index.url()} />}
                                    className="text-muted-foreground hover:text-foreground -ml-2 h-8 gap-1.5 px-2.5 text-xs font-semibold"
                                >
                                    <ArrowLeft className="size-3.5" aria-hidden="true" />
                                    Classes
                                </Button>
                                <span className="text-muted-foreground/60 text-xs">/</span>
                                <span className="text-muted-foreground text-xs font-medium">
                                    {isEditing
                                        ? "Edit class offering"
                                        : createMode === "batch"
                                          ? "Parallel Sections Blueprint Matrix"
                                          : "New class offering"}
                                </span>
                            </div>

                            <div className="min-w-0 space-y-1">
                                <div className="flex items-center gap-2.5">
                                    <div className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-xl">
                                        {isEditing ? (
                                            <GraduationCap className="size-5" aria-hidden="true" />
                                        ) : createMode === "batch" ? (
                                            <Table2 className="size-5" aria-hidden="true" />
                                        ) : (
                                            <BookOpen className="size-5" aria-hidden="true" />
                                        )}
                                    </div>
                                    <h1 className="text-foreground text-2xl font-bold tracking-tight text-balance sm:text-3xl">
                                        {isEditing
                                            ? class_title || "Edit class"
                                            : createMode === "batch"
                                              ? "Multi-Section Blueprint Matrix"
                                              : "Create new class"}
                                    </h1>
                                </div>
                                <p className="text-muted-foreground max-w-2xl pl-11.5 text-xs text-pretty sm:text-sm">
                                    {isEditing
                                        ? "Update curriculum placement, instructor assignment, live preflight timetable, and classroom policies."
                                        : createMode === "batch"
                                          ? "Configure a shared subject blueprint once and stage multiple parallel sections with live room, faculty, and hour checks."
                                          : "Compose curriculum placement, faculty assignment, and weekly timetable with real-time conflict preflight."}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2 self-start lg:pt-1">
                            {!isEditing ? (
                                <div
                                    className="inline-flex items-center gap-1 rounded-xl border bg-muted/40 p-1"
                                    role="group"
                                    aria-label="Class creation mode"
                                >
                                    <button
                                        type="button"
                                        aria-pressed={createMode === "single"}
                                        onClick={() => switchMode("single")}
                                        className={cn(
                                            "inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all",
                                            createMode === "single"
                                                ? "bg-background text-foreground shadow-2xs"
                                                : "text-muted-foreground hover:text-foreground",
                                        )}
                                    >
                                        <BookOpen className="size-3.5" aria-hidden="true" />
                                        Single Class
                                    </button>
                                    <button
                                        type="button"
                                        aria-pressed={createMode === "batch"}
                                        onClick={() => switchMode("batch")}
                                        className={cn(
                                            "inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all",
                                            createMode === "batch"
                                                ? "bg-background text-foreground shadow-2xs"
                                                : "text-muted-foreground hover:text-foreground",
                                        )}
                                    >
                                        <Layers className="size-3.5" aria-hidden="true" />
                                        Parallel Sections (Batch)
                                    </button>
                                </div>
                            ) : null}

                            <Badge
                                variant="outline"
                                className="border-border/80 bg-muted/40 text-foreground gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold backdrop-blur"
                            >
                                <Calendar className="text-primary size-3.5" aria-hidden="true" />
                                {schoolYear} · {termLabel}
                            </Badge>

                            {isEditing && class_id ? (
                                <>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-9 gap-1.5 rounded-lg px-3 text-xs font-semibold shadow-2xs"
                                        nativeButton={false}
                                        render={<Link href={create.url({ query: { mode: "batch", blueprint_class_id: class_id } })} />}
                                    >
                                        <Layers className="size-3.5" aria-hidden="true" />
                                        Manage All Sections
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-9 gap-1.5 rounded-lg px-3 text-xs font-semibold shadow-2xs"
                                        nativeButton={false}
                                        render={<Link href={show.url(class_id)} />}
                                    >
                                        <ArrowUpRight className="size-3.5" aria-hidden="true" />
                                        Open Dossier
                                    </Button>
                                </>
                            ) : null}
                        </div>
                    </div>
                </header>

                {flash ? (
                    <div className="border-primary/20 bg-primary/5 text-foreground rounded-xl border px-4 py-3 text-sm shadow-2xs" role="status">
                        {flash.message}
                    </div>
                ) : null}

                {/* Surface 1 (Single Class / Edit) vs Surface 3 (Batch Blueprint Matrix) */}
                {(isEditing || visitedModes.has("single")) && (
                    <div hidden={!isEditing && createMode !== "single"}>
                    <SplitComposerStudio
                        semester={semester}
                        school_year={schoolYear}
                        options={options}
                        mode={mode}
                        classId={class_id}
                        initialData={initialData}
                        editMeta={resolvedEditMeta}
                        siblingSections={sibling_sections}
                        initialFocus={initialFocus}
                    />
                    </div>
                )}
                {!isEditing && visitedModes.has("batch") && (
                    <div hidden={createMode !== "batch"}>
                    <BlueprintMatrixWorkbench
                        semester={semester}
                        school_year={schoolYear}
                        options={options}
                        initialBlueprint={initialData ?? { delivery_mode: canonicalDeliveryMode(defaults.delivery_mode) }}
                        siblingSections={sibling_sections}
                        preloadedSections={blueprint_sections}
                    />
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
