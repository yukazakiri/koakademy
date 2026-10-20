import AdminLayout from "@/components/administrators/admin-layout";
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
import type { User } from "@/types/user";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { IconChevronRight, IconSearch } from "@tabler/icons-react";
import { useEffect, useState, type FormEvent } from "react";
import { route } from "ziggy-js";

type SearchGroup = "students" | "classes" | "users" | "faculty" | "enrollments";

type ResultRow = {
    id: number | string;
    [key: string]: unknown;
};

type PaginatedResults = {
    data: ResultRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Props = {
    query: string | null;
    rawQuery: string;
    group: SearchGroup;
    groups: SearchGroup[];
    results: PaginatedResults;
};

const GROUP_LABELS: Record<SearchGroup, string> = {
    students: "Students",
    classes: "Classes",
    users: "Users",
    faculty: "Faculty",
    enrollments: "Enrollments",
};

/** Where a result row points, per group. */
const GROUP_HREFS: Record<SearchGroup, (id: number | string) => string> = {
    students: (id) => `/administrators/students/${id}`,
    classes: (id) => `/administrators/classes/${id}`,
    users: (id) => `/administrators/users/${id}/edit`,
    faculty: (id) => `/administrators/faculties/${id}`,
    enrollments: (id) => `/administrators/enrollments/${id}`,
};

function rowTitle(group: SearchGroup, row: ResultRow): string {
    const candidates = ["name", "record_title", "student_name", "email"];

    for (const key of candidates) {
        const value = row[key];

        if (typeof value === "string" && value !== "") {
            return value;
        }
    }

    return `#${String(row.id)}`;
}

function rowSubtitle(group: SearchGroup, row: ResultRow): string {
    const text = (key: string): string => (typeof row[key] === "string" ? (row[key] as string) : "");

    switch (group) {
        case "students":
            return [text("student_id"), text("course"), text("academic_year")].filter(Boolean).join(" · ");
        case "classes":
            return [text("subject_title"), text("section"), text("school_year")].filter(Boolean).join(" · ");
        case "users":
            return [text("role"), text("email")].filter(Boolean).join(" · ");
        case "faculty":
            return [text("department"), text("email")].filter(Boolean).join(" · ");
        case "enrollments":
            return [text("course_code"), text("year_level"), text("semester")].filter(Boolean).join(" · ");
        default:
            return "";
    }
}

export default function GlobalSearchResults({ query, rawQuery, group, groups, results }: Props) {
    const { props } = usePage<{ auth?: { user?: User | null } }>();
    const user = props.auth?.user;
    const [searchText, setSearchText] = useState(rawQuery);

    useEffect(() => {
        setSearchText(rawQuery);
    }, [rawQuery]);

    function submit(event: FormEvent): void {
        event.preventDefault();

        router.get(route("administrators.search.results", { q: searchText, type: group }), {}, { preserveState: false });
    }

    if (! user) {
        return null;
    }

    return (
        <AdminLayout user={user} title="Search results">
            <Head title="Search results" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Search results</h1>
                    <p className="text-muted-foreground text-sm">
                        {query === null
                            ? "Type at least 2 characters to search students, classes, users, faculty and enrollments."
                            : `${results.total} ${GROUP_LABELS[group].toLowerCase()} matched “${query}”.`}
                    </p>
                </div>

                <form onSubmit={submit} className="flex items-center gap-2">
                    <div className="relative w-full max-w-md">
                        <IconSearch className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={searchText}
                            onChange={(event) => setSearchText(event.target.value)}
                            placeholder="Search students, classes, users, faculty, enrollments…"
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
                            href={route("administrators.search.results", { q: query ?? "", type: candidate })}
                            className={
                                candidate === group
                                    ? "bg-primary text-primary-foreground rounded-md px-3 py-1.5 text-sm font-medium"
                                    : "text-muted-foreground hover:bg-accent rounded-md px-3 py-1.5 text-sm font-medium"
                            }
                            preserveScroll
                        >
                            {GROUP_LABELS[candidate]}
                        </Link>
                    ))}
                </div>

                {results.data.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center text-sm">
                        {query === null ? "Nothing searched yet." : `No ${GROUP_LABELS[group].toLowerCase()} matched “${query}”.`}
                    </div>
                ) : (
                    <ul className="divide-border divide-y rounded-lg border">
                        {results.data.map((row) => (
                            <li key={`${group}:${String(row.id)}`}>
                                <Link
                                    href={GROUP_HREFS[group](row.id)}
                                    className="hover:bg-accent/50 flex items-center gap-3 px-4 py-3"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium">
                                            {query ? highlightSearchTerms(rowTitle(group, row), query) : rowTitle(group, row)}
                                        </div>
                                        <div className="text-muted-foreground truncate text-xs">
                                            {rowSubtitle(group, row)}
                                        </div>
                                    </div>
                                    {typeof row.status === "string" && row.status !== "" ? (
                                        <Badge variant="secondary" className="shrink-0">
                                            {row.status}
                                        </Badge>
                                    ) : null}
                                    <IconChevronRight className="text-muted-foreground size-4 shrink-0" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {results.last_page > 1 ? (
                    <Pagination>
                        <PaginationContent>
                            <PaginationItem>
                                <PaginationPrevious
                                    href={route("administrators.search.results", {
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
                                    href={route("administrators.search.results", {
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
        </AdminLayout>
    );
}
