import AdminLayout from "@/components/administrators/admin-layout";
import SearchResultsView, { type PaginatedResults } from "@/components/search-results-view";
import type { SearchGroup } from "@/hooks/use-global-search";
import type { User } from "@/types/user";
import { usePage } from "@inertiajs/react";

type Props = {
    query: string | null;
    rawQuery: string;
    group: SearchGroup;
    groups: SearchGroup[];
    results: PaginatedResults;
};

export default function AdministratorSearchResults({ query, rawQuery, group, groups, results }: Props) {
    const { props } = usePage<{ auth?: { user?: User | null } }>();
    const user = props.auth?.user;

    if (! user) {
        return null;
    }

    return (
        <AdminLayout user={user} title="Search results">
            <SearchResultsView
                query={query}
                rawQuery={rawQuery}
                group={group}
                groups={groups}
                results={results}
                resultsRoute="administrators.search.results"
            />
        </AdminLayout>
    );
}
