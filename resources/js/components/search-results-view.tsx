import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from "@/components/ui/pagination";
import { highlightSearchTerms } from "@/lib/highlight-search-terms";
import type { SearchGroup } from "@/hooks/use-global-search";
import { Head, Link, router } from "@inertiajs/react";
import { IconChevronRight, IconSearch } from "@tabler/icons-react";
import { useEffect, useState, type FormEvent } from "react";
import { route } from "ziggy-js";

export type ResultRow = {
    id: number | string;
    url?: string | null;
    status?: string | null;
    [key: string]: unknown;
};

export type PaginatedResults = {
    data: ResultRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type SearchResultsViewProps = {
    query: string | null;
    rawQuery: string;
    group: SearchGroup;
    groups: SearchGroup[];
    results: PaginatedResults;
    /** Named route of the results page this view belongs to. */
    resultsRoute: string;
};

const GROUP_LABELS: Record<SearchGroup, string> = {
    students: "students",
    classes: "classes",
    users: "users",
    faculty: "faculty",
    enrollments: "enrollments",
    subjects: "subjects",
    courses: "courses",
    schedule: "schedule entries",
};

function text(row: ResultRow, key: string): string {
    const value = row[key];

    return typeof value === "string" || typeof value === "number" ? String(value) : "";
}

/**
 * Groups render their own label pair, because one set of column names does not
 * describe every record type.
 */
function rowTitle(group: SearchGroup, row: ResultRow): string {
    const key = (group === "classes" || group === "schedule") ? "record_title" : group === "enrollments" ? "student_name" : "name";
    const candidates = group === "classes" || group === "schedule" || group === "enrollments" ? [key] : [key, "title"];

    for (const candidate of candidates) {
        const value = text(row, candidate);

        if (value !== "") {
            return value;
        }
    }

    return `#${String(row.id)}`;
}

function rowSubtitle(group: SearchGroup, row: ResultRow): string {
    switch (group) {
        case "schedule":
            return [text(row, "day_of_week"), text(row, "time_range"), text(row, "room")].filter(Boolean).join(" · ");
        case "students":
            return [text(row, "student_id"), text(row, "course"), text(row, "academic_year")].filter(Boolean).join(" · ");
        case "classes":
            return [text(row, "subject_title"), text(row, "section"), text(row, "school_year")].filter(Boolean).join(" · ");
        case "users":
            return [text(row, "role"), text(row, "email")].filter(Boolean).join(" · ");
        case "faculty":
            return [text(row, "department"), text(row, "email")].filter(Boolean).join(" · ");
        case "enrollments":
            return [text(row, "course_code"), text(row, "year_level"), text(row, "semester")].filter(Boolean).join(" · ");
        case "subjects":
            return [text(row, "code"), text(row, "units") && `${text(row, "units")} units`].filter(Boolean).join(" · ");
        case "courses":
            return [text(row, "code"), text(row, "department")].filter(Boolean).join(" · ");
        default:
            return "";
    }
}

/**
 * The full-page search results body, shared by every portal.
 *
 * Each portal supplies its own layout, named results route and group list; rows
 * carry the destination the server chose, so a record links to the same place
 * from the palette and from here.
 */
export default function SearchResultsView({ query, rawQuery, group, groups, results, resultsRoute }: SearchResultsViewProps) {
    const [searchText, setSearchText] = useState(rawQuery);

    useEffect(() => {
        setSearchText(rawQuery);
    }, [rawQuery]);

    function submit(event: FormEvent): void {
        event.preventDefault();

        router.get(route(resultsRoute, { q: searchText, type: group }), {}, { preserveState: false });
    }

    const groupLabel = GROUP_LABELS[group];

    return (
        <>
            <Head title="Search results" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Search results</h1>
                    <p className="text-muted-foreground text-sm">
                        {query === null
                            ? "Type at least 2 characters to search across your portal."
                            : `${results.total} ${groupLabel} matched “${query}”.`}
                    </p>
                </div>

                <form onSubmit={submit} className="flex items-center gap-2">
                    <div className="relative w-full max-w-md">
                        <IconSearch className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={searchText}
                            onChange={(event) => setSearchText(event.target.value)}
                            placeholder="Search…"
                            className="pl-9"
                            autoFocus
                        />
                    </div>
                    <Button type="submit">Search</Button>
                </form>

                <div className="scrollbar-none -mx-1 flex gap-1 overflow-x-auto px-1">
                    {groups.map((candidate) => (
                        <Link
                            key={candidate}
                            href={route(resultsRoute, { q: query ?? "", type: candidate })}
                            className={
                                candidate === group
                                    ? "bg-primary text-primary-foreground rounded-md px-3 py-1.5 text-sm font-medium capitalize"
                                    : "text-muted-foreground hover:bg-accent rounded-md px-3 py-1.5 text-sm font-medium capitalize"
                            }
                            preserveScroll
                        >
                            {GROUP_LABELS[candidate]}
                        </Link>
                    ))}
                </div>

                {results.data.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center text-sm">
                        {query === null ? "Nothing searched yet." : `No ${groupLabel} matched “${query}”.`}
                    </div>
                ) : (
                    <ul className="divide-border divide-y rounded-lg border">
                        {results.data.map((row) => {
                            const title = rowTitle(group, row);
                            const subtitle = rowSubtitle(group, row);

                            return (
                                <li key={`${group}:${String(row.id)}`}>
                                    <Link
                                        href={row.url ?? "#"}
                                        className="hover:bg-accent/50 flex items-center gap-3 px-4 py-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate font-medium">
                                                {query ? highlightSearchTerms(title, query) : title}
                                            </div>
                                            <div className="text-muted-foreground truncate text-xs">{subtitle}</div>
                                        </div>
                                        {typeof row.status === "string" && row.status !== "" ? (
                                            <Badge variant="secondary" className="shrink-0">
                                                {row.status}
                                            </Badge>
                                        ) : null}
                                        <IconChevronRight className="text-muted-foreground size-4 shrink-0" />
                                    </Link>
                                    {typeof row.edit_url === "string" ? (
                                        <Link href={row.edit_url} className="inline-block px-4 pb-3 text-sm underline">Edit curriculum</Link>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ul>
                )}

                {results.last_page > 1 ? (
                    <Pagination>
                        <PaginationContent>
                            <PaginationItem>
                                <PaginationPrevious
                                    href={route(resultsRoute, {
                                        q: query ?? "",
                                        type: group,
                                        page: Math.max(1, results.current_page - 1),
                                    })}
                                    className={results.current_page <= 1 ? "pointer-events-none opacity-50" : ""}
                                />
                            </PaginationItem>
                            <PaginationItem>
                                <span className="text-muted-foreground px-3 text-sm">
                                    Page {results.current_page} of {results.last_page}
                                </span>
                            </PaginationItem>
                            <PaginationItem>
                                <PaginationNext
                                    href={route(resultsRoute, {
                                        q: query ?? "",
                                        type: group,
                                        page: Math.min(results.last_page, results.current_page + 1),
                                    })}
                                    className={results.current_page >= results.last_page ? "pointer-events-none opacity-50" : ""}
                                />
                            </PaginationItem>
                        </PaginationContent>
                    </Pagination>
                ) : null}
            </div>
        </>
    );
}
