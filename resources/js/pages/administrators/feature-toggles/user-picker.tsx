import { searchUsers } from "@/actions/App/Http/Controllers/AdministratorFeatureToggleController";
import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Loader2, Search, X } from "lucide-react";
import { useEffect, useId, useState } from "react";
import type { FeatureUser } from "./types";
import { portalFeatureUrl } from "./types";

interface UserPickerProps {
    onSelect: (user: FeatureUser) => void;
    label?: string;
    placeholder?: string;
    selectedUser?: FeatureUser | null;
    onClear?: () => void;
}

export function UserPicker({ onSelect, label = "Find user", placeholder = "Search name or email…", selectedUser = null, onClear }: UserPickerProps) {
    const [search, setSearch] = useState("");
    const [users, setUsers] = useState<FeatureUser[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const [retry, setRetry] = useState(0);
    const [isOpen, setIsOpen] = useState(false);
    const id = useId();

    useEffect(() => {
        if (!search.trim()) {
            setUsers([]);
            setLoading(false);
            return;
        }
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
                const data = await response.json();
                setUsers(data.users || []);
                setIsOpen(true);
            } catch {
                if (!controller.signal.aborted) setError(true);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 220);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [search, retry]);

    return (
        <div className="flex min-w-0 flex-col gap-1.5">
            {label && (
                <label htmlFor={id} className="text-foreground text-xs font-medium">
                    {label}
                </label>
            )}

            {selectedUser ? (
                <div className="border-border bg-muted/40 flex items-center justify-between gap-3 rounded-lg border p-2.5 transition-colors">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <div className="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold">
                            {selectedUser.name.slice(0, 2).toUpperCase()}
                        </div>
                        <div className="flex min-w-0 flex-col">
                            <div className="flex items-center gap-1.5">
                                <span className="text-foreground truncate text-xs font-medium">{selectedUser.name}</span>
                                {selectedUser.role && (
                                    <Badge variant="outline" className="px-1.5 py-0 text-[10px] capitalize">
                                        {selectedUser.role}
                                    </Badge>
                                )}
                            </div>
                            <span className="text-muted-foreground truncate text-[11px]">{selectedUser.email}</span>
                        </div>
                    </div>
                    {onClear && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-xs"
                            className="text-muted-foreground hover:text-foreground shrink-0"
                            onClick={() => {
                                onClear();
                                setSearch("");
                            }}
                            aria-label={`Clear ${selectedUser.name}`}
                        >
                            <X className="size-3.5" />
                        </Button>
                    )}
                </div>
            ) : (
                <div className="relative">
                    <div className="relative">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                        <Input
                            id={id}
                            placeholder={placeholder}
                            value={search}
                            className="pr-8 pl-8.5 text-xs"
                            onChange={(event) => {
                                setSearch(event.target.value);
                                if (!event.target.value) {
                                    setIsOpen(false);
                                }
                            }}
                            onFocus={() => {
                                if (users.length > 0) setIsOpen(true);
                            }}
                        />
                        {loading ? (
                            <div className="absolute top-1/2 right-2.5 -translate-y-1/2">
                                <Loader2 className="text-muted-foreground size-3.5 animate-spin" />
                            </div>
                        ) : search ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-xs"
                                className="text-muted-foreground hover:text-foreground absolute top-1/2 right-1.5 -translate-y-1/2"
                                onClick={() => {
                                    setSearch("");
                                    setUsers([]);
                                    setIsOpen(false);
                                }}
                            >
                                <X className="size-3.5" />
                            </Button>
                        ) : null}
                    </div>

                    {error && (
                        <div
                            role="alert"
                            className="border-destructive/20 bg-destructive/5 text-destructive mt-1.5 flex items-center justify-between gap-2 rounded-md border px-2.5 py-1.5 text-xs"
                        >
                            <span>Search failed.</span>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-6 px-2 text-xs"
                                onClick={() => setRetry((value) => value + 1)}
                            >
                                Retry
                            </Button>
                        </div>
                    )}

                    {isOpen && search.trim() && !loading && !error && (
                        <div className="border-border bg-popover absolute z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border p-1 shadow-lg">
                            {users.length === 0 ? (
                                <div className="text-muted-foreground py-4 text-center text-xs">No users found matching "{search}"</div>
                            ) : (
                                users.map((user) => (
                                    <button
                                        type="button"
                                        key={user.id}
                                        className="hover:bg-muted focus-visible:bg-muted flex w-full items-center justify-between gap-2.5 rounded-md px-2.5 py-2 text-left text-xs transition-colors focus-visible:outline-none"
                                        onClick={() => {
                                            onSelect(user);
                                            setSearch("");
                                            setUsers([]);
                                            setIsOpen(false);
                                        }}
                                    >
                                        <div className="flex min-w-0 items-center gap-2">
                                            <div className="bg-primary/10 text-primary flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold">
                                                {user.name.slice(0, 2).toUpperCase()}
                                            </div>
                                            <div className="flex min-w-0 flex-col">
                                                <span className="text-foreground truncate font-medium">{user.name}</span>
                                                <span className="text-muted-foreground truncate text-[11px]">{user.email}</span>
                                            </div>
                                        </div>
                                        {user.role && (
                                            <Badge variant="outline" className="shrink-0 px-1.5 py-0 text-[10px] capitalize">
                                                {user.role}
                                            </Badge>
                                        )}
                                    </button>
                                ))
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
