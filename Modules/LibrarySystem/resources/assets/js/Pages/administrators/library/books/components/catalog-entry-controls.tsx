import { store as storeAuthor } from "@/actions/Modules/LibrarySystem/Http/Controllers/AdministratorLibraryAuthorController";
import { fieldValues as bookFieldValues } from "@/actions/Modules/LibrarySystem/Http/Controllers/AdministratorLibraryBookController";
import { store as storeCategory } from "@/actions/Modules/LibrarySystem/Http/Controllers/AdministratorLibraryCategoryController";
import { AutocompleteFieldInput } from "@/components/ui/autocomplete-field-input";
import { Button } from "@/components/ui/button";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";
import { useHttp } from "@inertiajs/react";
import { Check, ChevronsUpDown, Loader2, Plus, Tags, UserRound } from "lucide-react";
import { useMemo, useState, type FormEvent } from "react";
import { toast } from "sonner";

export interface CatalogOption {
    value: string | number;
    label: string;
}

type RelationKind = "author" | "category";

interface CatalogRelationFieldProps {
    id: string;
    kind: RelationKind;
    options: CatalogOption[];
    value: string;
    onValueChange: (value: string) => void;
    error?: string;
}

interface QuickCreateResponse {
    author?: {
        id: number;
        name: string;
    };
    category?: {
        id: number;
        name: string;
        color: string | null;
    };
}

export function CatalogRelationField({ id, kind, options, value, onValueChange, error }: CatalogRelationFieldProps) {
    const [availableOptions, setAvailableOptions] = useState<CatalogOption[]>(options);
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState("");
    const [createOpen, setCreateOpen] = useState(false);
    const quickCreate = useHttp<{ name: string }, QuickCreateResponse>({
        name: "",
    });

    const noun = kind === "author" ? "author" : "category";
    const pluralNoun = kind === "author" ? "Authors" : "Categories";
    const Icon = kind === "author" ? UserRound : Tags;
    const selectedOption = availableOptions.find((option) => String(option.value) === value);
    const normalizedSearch = search.trim();

    const filteredOptions = useMemo(() => {
        const query = normalizedSearch.toLocaleLowerCase();

        return availableOptions
            .filter((option) => query === "" || option.label.toLocaleLowerCase().includes(query))
            .sort((left, right) => {
                if (query !== "") {
                    const leftStartsWithQuery = left.label.toLocaleLowerCase().startsWith(query);
                    const rightStartsWithQuery = right.label.toLocaleLowerCase().startsWith(query);

                    if (leftStartsWithQuery !== rightStartsWithQuery) {
                        return leftStartsWithQuery ? -1 : 1;
                    }
                }

                return left.label.localeCompare(right.label);
            })
            .slice(0, 40);
    }, [availableOptions, normalizedSearch]);

    const canCreateFromSearch =
        normalizedSearch !== "" && !availableOptions.some((option) => option.label.toLocaleLowerCase() === normalizedSearch.toLocaleLowerCase());

    const requestQuickCreate = (name = "") => {
        quickCreate.setData("name", name);
        quickCreate.clearErrors();
        setOpen(false);
        setCreateOpen(true);
    };

    const handleCreate = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        event.stopPropagation();

        const name = quickCreate.data.name.trim();

        if (name === "") {
            quickCreate.setError("name", `Enter a ${noun} name.`);
            return;
        }

        quickCreate.setData("name", name);
        quickCreate.clearErrors();

        try {
            const endpoint = kind === "author" ? storeAuthor.url() : storeCategory.url();
            await quickCreate.post(endpoint, {
                onSuccess: (response) => {
                    const record = kind === "author" ? response.author : response.category;

                    if (!record) {
                        quickCreate.setError("name", `The ${noun} response was incomplete.`);
                        return;
                    }

                    const createdOption: CatalogOption = {
                        value: record.id,
                        label: record.name,
                    };

                    setAvailableOptions((current) =>
                        [...current.filter((option) => String(option.value) !== String(record.id)), createdOption].sort((left, right) =>
                            left.label.localeCompare(right.label),
                        ),
                    );
                    onValueChange(String(record.id));
                    setOpen(false);
                    setSearch("");
                    setCreateOpen(false);
                    quickCreate.resetAndClearErrors();
                    toast.success(`${record.name} added and selected.`);
                },
                onNetworkError: () => {
                    quickCreate.setError("name", `Could not create the ${noun}. Please try again.`);
                },
            });
        } catch {
            quickCreate.setError("name", `Could not create the ${noun}. Please try again.`);
        }
    };

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{kind === "author" ? "Author" : "Category"}</Label>
            <div className="flex items-stretch gap-2">
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            id={id}
                            type="button"
                            variant="outline"
                            role="combobox"
                            aria-expanded={open}
                            aria-invalid={Boolean(error)}
                            className="h-auto min-h-11 min-w-0 flex-1 justify-between rounded-xl px-3.5 py-2 text-left transition-transform active:scale-[0.96]"
                        >
                            {selectedOption ? (
                                <span className="flex min-w-0 items-center gap-2.5">
                                    <span className="bg-primary/10 text-primary flex size-7 shrink-0 items-center justify-center rounded-lg">
                                        <Icon className="size-3.5" />
                                    </span>
                                    <span className="truncate font-medium">{selectedOption.label}</span>
                                </span>
                            ) : (
                                <span className="text-muted-foreground flex min-w-0 items-center gap-2.5 font-normal">
                                    <Icon className="size-4 shrink-0" />
                                    <span className="truncate">Search {pluralNoun.toLocaleLowerCase()}…</span>
                                </span>
                            )}
                            <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent
                        className="w-[var(--radix-popover-trigger-width)] min-w-[min(20rem,calc(100vw-2rem))] rounded-xl p-0"
                        align="start"
                    >
                        <Command shouldFilter={false}>
                            <CommandInput placeholder={`Search ${pluralNoun.toLocaleLowerCase()}…`} value={search} onValueChange={setSearch} />
                            <CommandList>
                                {filteredOptions.length === 0 && !canCreateFromSearch && (
                                    <CommandEmpty>No matching {pluralNoun.toLocaleLowerCase()} found.</CommandEmpty>
                                )}
                                {canCreateFromSearch && (
                                    <CommandGroup heading="Quick action">
                                        <CommandItem
                                            value={`create-${normalizedSearch}`}
                                            onSelect={() => requestQuickCreate(normalizedSearch)}
                                            className="min-h-11 cursor-pointer rounded-lg"
                                        >
                                            <span className="bg-primary/10 text-primary flex size-7 items-center justify-center rounded-lg">
                                                <Plus className="size-4" />
                                            </span>
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">Create “{normalizedSearch}”</span>
                                                <span className="text-muted-foreground block text-xs">Add and select without leaving this book.</span>
                                            </span>
                                        </CommandItem>
                                    </CommandGroup>
                                )}
                                {filteredOptions.length > 0 && (
                                    <CommandGroup heading={pluralNoun}>
                                        {filteredOptions.map((option) => (
                                            <CommandItem
                                                key={option.value}
                                                value={String(option.value)}
                                                onSelect={() => {
                                                    onValueChange(String(option.value));
                                                    setSearch("");
                                                    setOpen(false);
                                                }}
                                                className="min-h-10 cursor-pointer rounded-lg"
                                            >
                                                <Icon className="text-muted-foreground size-4" />
                                                <span className="min-w-0 flex-1 truncate">{option.label}</span>
                                                <Check className={cn("size-4", value === String(option.value) ? "opacity-100" : "opacity-0")} />
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                )}
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>

                <Dialog
                    open={createOpen}
                    onOpenChange={(nextOpen) => {
                        setCreateOpen(nextOpen);
                        if (!nextOpen) {
                            quickCreate.resetAndClearErrors();
                        }
                    }}
                >
                    <DialogTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-11 rounded-xl transition-transform active:scale-[0.96]"
                            aria-label={`Create a new ${noun}`}
                            title={`Create a new ${noun}`}
                        >
                            <Plus className="size-4" />
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="rounded-2xl sm:max-w-md">
                        <form onSubmit={handleCreate} className="grid gap-5">
                            <DialogHeader>
                                <DialogTitle>Quick-add {noun}</DialogTitle>
                                <DialogDescription className="text-pretty">
                                    Create the required catalog record here. It will be selected automatically and your book details will stay intact.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-2">
                                <Label htmlFor={`${id}-quick-name`}>{kind === "author" ? "Author name" : "Category name"}</Label>
                                <Input
                                    id={`${id}-quick-name`}
                                    value={quickCreate.data.name}
                                    onChange={(event) => {
                                        quickCreate.setData("name", event.target.value);
                                        quickCreate.clearErrors("name");
                                    }}
                                    placeholder={kind === "author" ? "e.g. Octavia E. Butler" : "e.g. Science Fiction"}
                                    className="h-11 rounded-xl"
                                    aria-invalid={Boolean(quickCreate.errors.name)}
                                    autoFocus
                                />
                                {quickCreate.errors.name && <p className="text-destructive text-sm">{quickCreate.errors.name}</p>}
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={quickCreate.processing} className="transition-transform active:scale-[0.96]">
                                    {quickCreate.processing ? <Loader2 className="size-4 animate-spin" /> : <Plus className="size-4" />}
                                    {quickCreate.processing ? "Creating…" : `Create ${noun}`}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
            <p className="text-muted-foreground text-xs text-pretty">Type to filter, or use + to add a missing {noun} instantly.</p>
            {error && <p className="text-destructive text-xs">{error}</p>}
        </div>
    );
}

interface CatalogIdentifierFieldProps {
    id: string;
    kind: "isbn" | "call_number";
    value: string;
    onValueChange: (value: string) => void;
    error?: string;
}

export function CatalogIdentifierField({ id, kind, value, onValueChange, error }: CatalogIdentifierFieldProps) {
    const label = kind === "isbn" ? "ISBN" : "Call Number";

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <AutocompleteFieldInput
                id={id}
                value={value}
                onChange={onValueChange}
                fieldName={kind}
                endpoint={bookFieldValues.url()}
                placeholder={kind === "isbn" ? "Type or reuse an ISBN" : "Type or reuse a call number"}
                className="[&_[data-slot=autocomplete-input]]:h-11 [&_[data-slot=autocomplete-input]]:rounded-xl"
                aria-invalid={Boolean(error)}
            />
            <p className="text-muted-foreground text-xs text-pretty">Choose an existing value or keep typing a new one.</p>
            {error && <p className="text-destructive text-xs">{error}</p>}
        </div>
    );
}
