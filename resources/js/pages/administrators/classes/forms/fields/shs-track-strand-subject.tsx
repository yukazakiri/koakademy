import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { shsStrands, shsSubjects } from "@/routes/administrators/classes/options";
import { useEffect, useState, type ReactNode } from "react";
import type { EntityOption, ShsSubjectOption, ValueOption } from "../../lib/class-defaults";

type ShsTrackStrandSubjectProps = {
    tracks: EntityOption[];
    gradeLevels: ValueOption[];
    trackId: number | null;
    strandId: number | null;
    subjectCode: string;
    gradeLevel: string;
    onTrackChange: (trackId: number | null) => void;
    onStrandChange: (strandId: number | null) => void;
    onSubjectCodeChange: (subjectCode: string) => void;
    onGradeLevelChange: (gradeLevel: string) => void;
};

type OptionsResponse<T> = { data: T[] };

function isEntityOption(value: unknown): value is EntityOption {
    return typeof value === "object" && value !== null && "id" in value && "label" in value;
}

function isShsSubjectOption(value: unknown): value is ShsSubjectOption {
    return typeof value === "object" && value !== null && "code" in value && "label" in value;
}

async function fetchOptionData<T>(url: string, guard: (value: unknown) => value is T): Promise<T[]> {
    const response = await fetch(url, { headers: { Accept: "application/json" } });
    if (!response.ok) throw new Error("Curriculum options could not be loaded.");
    const payload = (await response.json()) as Partial<OptionsResponse<unknown>>;
    const rows = Array.isArray(payload.data) ? payload.data : [];

    return rows.filter(guard);
}

export function ShsTrackStrandSubject({
    tracks,
    gradeLevels,
    trackId,
    strandId,
    subjectCode,
    gradeLevel,
    onTrackChange,
    onStrandChange,
    onSubjectCodeChange,
    onGradeLevelChange,
}: ShsTrackStrandSubjectProps): ReactNode {
    const [strands, setStrands] = useState<EntityOption[]>([]);
    const [subjects, setSubjects] = useState<ShsSubjectOption[]>([]);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [loadingStrands, setLoadingStrands] = useState(false);
    const [loadingSubjects, setLoadingSubjects] = useState(false);

    useEffect(() => {
        if (!trackId) {
            setStrands([]);
            return;
        }

        let active = true;
        setLoadingStrands(true);
        setLoadError(null);
        fetchOptionData<EntityOption>(shsStrands.url({ query: { track_id: trackId } }), isEntityOption)
            .then((rows) => {
                if (active) setStrands(rows);
            })
            .catch(() => {
                if (active) setLoadError("Strands could not be loaded. Select the track again to retry.");
            })
            .finally(() => {
                if (active) setLoadingStrands(false);
            });
        return () => {
            active = false;
        };
    }, [trackId]);

    useEffect(() => {
        if (!strandId) {
            setSubjects([]);
            return;
        }

        let active = true;
        setLoadingSubjects(true);
        setLoadError(null);
        fetchOptionData<ShsSubjectOption>(shsSubjects.url({ query: { strand_id: strandId } }), isShsSubjectOption)
            .then((rows) => {
                if (active) setSubjects(rows);
            })
            .catch(() => {
                if (active) setLoadError("Subjects could not be loaded. Select the strand again to retry.");
            })
            .finally(() => {
                if (active) setLoadingSubjects(false);
            });
        return () => {
            active = false;
        };
    }, [strandId]);

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <div className="space-y-2">
                <Label htmlFor="shs-track">Track</Label>
                <Select
                    value={trackId ? String(trackId) : ""}
                    onValueChange={(value) => {
                        onTrackChange(Number(value));
                        onStrandChange(null);
                        onSubjectCodeChange("");
                    }}
                >
                    <SelectTrigger id="shs-track" className="w-full">
                        <SelectValue placeholder="Choose a track" />
                    </SelectTrigger>
                    <SelectContent>
                        {tracks.map((track) => (
                            <SelectItem key={track.id} value={String(track.id)}>
                                {track.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-2">
                <Label htmlFor="shs-strand">Strand</Label>
                <Select
                    value={strandId ? String(strandId) : ""}
                    onValueChange={(value) => {
                        onStrandChange(Number(value));
                        onSubjectCodeChange("");
                    }}
                    disabled={!trackId || loadingStrands}
                >
                    <SelectTrigger id="shs-strand" className="w-full">
                        {loadingStrands ? (
                            <span className="text-muted-foreground flex items-center gap-2">Loading strands</span>
                        ) : (
                            <SelectValue placeholder="Choose a strand" />
                        )}
                    </SelectTrigger>
                    <SelectContent>
                        {strands.map((strand) => (
                            <SelectItem key={strand.id} value={String(strand.id)}>
                                {strand.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-2">
                <Label htmlFor="shs-subject">Subject</Label>
                <Select value={subjectCode} onValueChange={onSubjectCodeChange} disabled={!strandId || loadingSubjects}>
                    <SelectTrigger id="shs-subject" className="w-full">
                        {loadingSubjects ? (
                            <span className="text-muted-foreground flex items-center gap-2">Loading subjects</span>
                        ) : (
                            <SelectValue placeholder="Choose a subject" />
                        )}
                    </SelectTrigger>
                    <SelectContent>
                        {subjects.map((subject) => (
                            <SelectItem key={subject.code} value={subject.code}>
                                {subject.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-2">
                <Label htmlFor="shs-grade">Grade level</Label>
                <Select value={gradeLevel} onValueChange={onGradeLevelChange}>
                    <SelectTrigger id="shs-grade" className="w-full">
                        <SelectValue placeholder="Choose a grade level" />
                    </SelectTrigger>
                    <SelectContent>
                        {gradeLevels.map((level) => (
                            <SelectItem key={level.value} value={level.value}>
                                {level.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            {loadError ? (
                <p role="alert" className="text-destructive text-sm lg:col-span-2">
                    {loadError}
                </p>
            ) : null}
        </div>
    );
}
