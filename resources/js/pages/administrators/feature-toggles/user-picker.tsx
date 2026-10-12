import { searchUsers } from "@/actions/App/Http/Controllers/AdministratorFeatureToggleController";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { useEffect, useId, useState } from "react";
import type { FeatureUser } from "./types";
import { portalFeatureUrl } from "./types";

export function UserPicker({ onSelect, label = "Find user" }: { onSelect: (user: FeatureUser) => void; label?: string }) {
    const [search, setSearch] = useState("");
    const [users, setUsers] = useState<FeatureUser[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const [retry, setRetry] = useState(0);
    const id = useId();

    useEffect(() => {
        if (!search.trim()) return;
        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setLoading(true);
            setError(false);
            try {
                const response = await fetch(portalFeatureUrl(searchUsers.url({ query: { search } })), {
                    signal: controller.signal,
                    headers: { Accept: "application/json" },
                });
                if (!response.ok) throw new Error("Search failed");
                setUsers((await response.json()).users);
            } catch {
                if (!controller.signal.aborted) setError(true);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 250);
        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [search, retry]);

    return (
        <div className="flex min-w-0 flex-col gap-2">
            <label htmlFor={id} className="text-sm font-medium">
                {label}
            </label>
            <Input
                id={id}
                placeholder="Search name or email"
                value={search}
                onChange={(event) => {
                    setSearch(event.target.value);
                    setUsers([]);
                    setLoading(Boolean(event.target.value.trim()));
                    setError(false);
                }}
            />
            {loading ? (
                <Skeleton className="h-12 w-full" />
            ) : error ? (
                <div role="alert" className="flex items-center gap-2 text-sm">
                    Search failed.
                    <Button type="button" size="sm" variant="outline" onClick={() => setRetry((value) => value + 1)}>
                        Retry
                    </Button>
                </div>
            ) : search.trim() && users.length === 0 ? (
                <p className="text-muted-foreground text-xs">No users found. Try another name.</p>
            ) : null}
            {search.trim() && !loading && !error && (
                <div className="flex max-h-48 flex-col gap-1 overflow-y-auto">
                    {users.map((user) => (
                        <Button
                            type="button"
                            key={user.id}
                            variant="ghost"
                            className="justify-start"
                            onClick={() => {
                                onSelect(user);
                                setSearch("");
                                setUsers([]);
                                setLoading(false);
                            }}
                        >
                            <span className="truncate">
                                {user.name} <span className="text-muted-foreground font-normal">{user.email}</span>
                            </span>
                        </Button>
                    ))}
                </div>
            )}
        </div>
    );
}
