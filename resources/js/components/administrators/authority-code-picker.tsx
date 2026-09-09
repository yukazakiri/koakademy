import { Command, CommandEmpty, CommandItem, CommandList } from "@/components/ui/command";
import { Input } from "@/components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";
import axios from "axios";
import { Loader2, X } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { useDebounce } from "use-debounce";
import { route } from "ziggy-js";

export interface AuthorityCodeOption {
    id: number;
    code: string;
    title: string;
    label: string;
    authority_id: number;
    authority_name: string | null;
    attributes: Record<string, string | null>;
}

interface AuthorityCodePickerProps {
    value: string;
    authorityId?: string;
    onSelect: (option: AuthorityCodeOption | null) => void;
    placeholder?: string;
    disabled?: boolean;
    id?: string;
    error?: string;
}

/**
 * Searchable picker for per-school regulatory authority codes.
 * Searches the tenant-scoped registry; never ships regulator data.
 */
export function AuthorityCodePicker({
    value,
    authorityId,
    onSelect,
    placeholder = "Search official code or title…",
    disabled = false,
    id,
    error,
}: AuthorityCodePickerProps) {
    const [open, setOpen] = useState(false);
    const [inputValue, setInputValue] = useState("");
    const [initialLabel, setInitialLabel] = useState<string | null>(null);
    const [debouncedValue] = useDebounce(inputValue, 300);
    const [options, setOptions] = useState<AuthorityCodeOption[]>([]);
    const [loading, setLoading] = useState(false);
    const abortRef = useRef<AbortController | null>(null);
    const blurTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        return () => {
            if (blurTimeout.current) clearTimeout(blurTimeout.current);
        };
    }, []);

    useEffect(() => {
        if (disabled || debouncedValue.length < 1) {
            setOptions([]);
            setOpen(false);
            return;
        }

        const controller = new AbortController();
        abortRef.current?.abort();
        abortRef.current = controller;

        const fetchOptions = async () => {
            setLoading(true);
            try {
                const response = await axios.get<{ codes: AuthorityCodeOption[] }>(route("administrators.curriculum.authority-codes.search"), {
                    params: { q: debouncedValue, code_authority_id: authorityId || undefined },
                    signal: controller.signal,
                    headers: { Accept: "application/json" },
                });
                const items = response.data.codes ?? [];
                setOptions(items);
                if (items.length > 0) setOpen(true);
            } catch (requestError: unknown) {
                const cancelled =
                    requestError instanceof Error &&
                    (requestError.name === "CanceledError" || (requestError as { code?: string }).code === "ERR_CANCELED");
                if (!cancelled) setOptions([]);
            } finally {
                setLoading(false);
            }
        };

        fetchOptions();

        return () => controller.abort();
    }, [debouncedValue, disabled, authorityId]);

    const handleSelect = useCallback(
        (option: AuthorityCodeOption) => {
            setInitialLabel(option.label);
            setInputValue("");
            onSelect(option);
            setOpen(false);
        },
        [onSelect],
    );

    const handleClear = useCallback(() => {
        setInitialLabel(null);
        setInputValue("");
        onSelect(null);
    }, [onSelect]);

    const shownValue = inputValue !== "" ? inputValue : (initialLabel ?? "");

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <div className="relative">
                <PopoverTrigger asChild>
                    <Input
                        id={id}
                        type="text"
                        role="combobox"
                        aria-expanded={open}
                        aria-autocomplete="list"
                        value={shownValue}
                        onChange={(event) => {
                            setInputValue(event.target.value);
                            if (initialLabel) setInitialLabel(null);
                            if (value) onSelect(null);
                        }}
                        placeholder={placeholder}
                        disabled={disabled}
                        autoComplete="off"
                        aria-invalid={Boolean(error)}
                        onFocus={() => {
                            if (blurTimeout.current) {
                                clearTimeout(blurTimeout.current);
                                blurTimeout.current = null;
                            }
                            if (!disabled && options.length > 0) setOpen(true);
                        }}
                        onBlur={() => {
                            blurTimeout.current = setTimeout(() => setOpen(false), 200);
                        }}
                    />
                </PopoverTrigger>
                {value && !disabled && (
                    <button
                        type="button"
                        onClick={handleClear}
                        aria-label="Clear selected authority code"
                        className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 -translate-y-1/2 rounded p-1"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>
            <PopoverContent
                className="w-[--radix-popover-trigger-width] p-0"
                align="start"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                <Command shouldFilter={false}>
                    <CommandList>
                        {loading && (
                            <div className="text-muted-foreground flex items-center justify-center gap-2 py-6 text-sm">
                                <Loader2 className="size-4 animate-spin" />
                                Searching official codes…
                            </div>
                        )}
                        {!loading && options.length === 0 && <CommandEmpty>No official codes found.</CommandEmpty>}
                        {!loading &&
                            options.map((option) => (
                                <CommandItem
                                    key={option.id}
                                    value={String(option.id)}
                                    onSelect={() => handleSelect(option)}
                                    className="cursor-pointer"
                                >
                                    <div className="min-w-0">
                                        <div className="truncate font-medium">{option.label}</div>
                                        {option.authority_name && (
                                            <div className="text-muted-foreground truncate text-xs">{option.authority_name}</div>
                                        )}
                                    </div>
                                </CommandItem>
                            ))}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

export function authorityCodeInputClass(error?: string) {
    return cn(error && "border-destructive");
}
