"use client";

import { cn } from "@/lib/utils";
import { Bot, BrainCircuit, Check, Cpu, LayoutGrid, Search, Sparkles, X, Zap } from "lucide-react";
import { useMemo, useState } from "react";
import type { ModelOption } from "./types";

export type ModelSelectorVariant = "List" | "Segmented";

export interface ModelSelectorProps {
    models: ModelOption[];
    value?: string;
    onChange?: (id: string) => void;
    searchInputRef?: React.RefObject<HTMLInputElement | null>;
    variant?: ModelSelectorVariant;
    className?: string;
    searchable?: boolean;
}

type ModelCategory = "recommended" | "auto" | "fast" | "reasoning" | "all";

/**
 * Humanize model IDs into friendly titles, clean provider names, and category tags.
 */
function humanizeModel(model: ModelOption): {
    displayName: string;
    family: "claude" | "openai" | "gemini" | "deepseek" | "auto" | "generic";
    displayProvider: string;
    category: "recommended" | "auto" | "fast" | "reasoning" | "general";
    tag?: string;
} {
    const rawId = model.id || "";
    const rawName = model.name || rawId;
    const cleanId = rawId.includes(":") ? rawId.split(":")[1] : rawId;
    const lower = cleanId.toLowerCase();

    // Determine Family
    let family: "claude" | "openai" | "gemini" | "deepseek" | "auto" | "generic" = "generic";
    if (lower.includes("claude") || lower.includes("fable") || lower.includes("sonnet") || lower.includes("opus")) {
        family = "claude";
    } else if (lower.includes("gpt") || lower.includes("codex") || lower.includes("openai") || lower.includes("o1") || lower.includes("o3")) {
        family = "openai";
    } else if (lower.includes("gemini")) {
        family = "gemini";
    } else if (lower.includes("deepseek")) {
        family = "deepseek";
    } else if (lower.startsWith("auto/")) {
        family = "auto";
    }

    // Determine Category
    let category: "recommended" | "auto" | "fast" | "reasoning" | "general" = "general";
    if (
        lower.includes("best-free") ||
        lower.includes("best-chat") ||
        lower.includes("sonnet-4-6") ||
        lower.includes("gemini-3.8-flash-high") ||
        lower.includes("gpt-5.6-luna-high") ||
        lower.includes("deepseek-v4-pro") ||
        (model.badge && (model.badge.includes("Default") || model.badge.includes("Recommended")))
    ) {
        category = "recommended";
    } else if (lower.startsWith("auto/")) {
        category = "auto";
    } else if (lower.includes("fast") || lower.includes("flash") || lower.includes("mini") || lower.includes("haiku") || lower.includes("turbo")) {
        category = "fast";
    } else if (lower.includes("reasoning") || lower.includes("thinking") || lower.includes("opus") || lower.includes("r1")) {
        category = "reasoning";
    }

    // Humanize Display Name
    let displayName = rawName;
    if (displayName === cleanId || displayName.includes("/") || displayName.includes(":")) {
        // Clean up prefixes like dva/, oc/, cx/, agy/, no-think/
        const stripped = cleanId.replace(/^(?:no-think\/|dva\/|oc\/|cx\/|cxa\/|agy\/|zed-hosted\/)+/, "");
        if (stripped.startsWith("auto/")) {
            displayName =
                "Auto: " +
                stripped
                    .replace("auto/", "")
                    .split("-")
                    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
                    .join(" ");
        } else {
            displayName = stripped
                .split(/[-_/]/)
                .map((w) => (w.length <= 3 ? w.toUpperCase() : w.charAt(0).toUpperCase() + w.slice(1)))
                .join(" ");
        }
    }

    // Display Provider
    let displayProvider = model.provider_name || model.provider || "AI";
    if (displayProvider.toLowerCase() === "omni") {
        displayProvider = family === "auto" ? "OmniRoute" : family.charAt(0).toUpperCase() + family.slice(1);
    }

    // Capability Tag
    let tag = model.badge;
    if (!tag) {
        if (category === "recommended") tag = "Recommended";
        else if (category === "fast") tag = "Fast";
        else if (category === "reasoning") tag = "Thinking";
        else if (family === "auto") tag = "Auto";
    }

    return { displayName, family, displayProvider, category, tag };
}

export function ModelSelector({
    models,
    value: valueProp,
    onChange,
    searchInputRef,
    variant = "List",
    className,
    searchable = true,
}: ModelSelectorProps) {
    const [valueState, setValueState] = useState(models.find((m) => !m.disabled)?.id);
    const [search, setSearch] = useState("");
    const [activeTab, setActiveTab] = useState<ModelCategory>("recommended");

    const value = valueProp !== undefined ? valueProp : valueState;

    function select(id: string) {
        setValueState(id);
        onChange?.(id);
    }

    // Enrich models with metadata once
    const enrichedModels = useMemo(() => {
        return models.map((m) => {
            const meta = humanizeModel(m);
            return {
                ...m,
                ...meta,
            };
        });
    }, [models]);

    // Counts by category
    const categoryCounts = useMemo(() => {
        const counts: Record<ModelCategory, number> = {
            recommended: 0,
            auto: 0,
            fast: 0,
            reasoning: 0,
            all: enrichedModels.length,
        };

        enrichedModels.forEach((m) => {
            if (m.category === "recommended") counts.recommended++;
            if (m.family === "auto" || m.category === "auto") counts.auto++;
            if (m.category === "fast") counts.fast++;
            if (m.category === "reasoning") counts.reasoning++;
        });

        // Ensure recommended always has at least a few models
        if (counts.recommended === 0 && enrichedModels.length > 0) {
            counts.recommended = Math.min(10, enrichedModels.length);
        }

        return counts;
    }, [enrichedModels]);

    // Filter models based on search and active tab
    const filteredModels = useMemo(() => {
        const q = search.trim().toLowerCase();

        // If searching, search globally across all models
        if (q) {
            return enrichedModels.filter((m) => {
                const name = m.displayName.toLowerCase();
                const id = (m.id || "").toLowerCase();
                const provider = (m.displayProvider || "").toLowerCase();
                const desc = (m.description || "").toLowerCase();
                return name.includes(q) || id.includes(q) || provider.includes(q) || desc.includes(q);
            });
        }

        // Otherwise filter by category tab
        if (activeTab === "recommended") {
            const recs = enrichedModels.filter(
                (m) => m.category === "recommended" || (m.badge && (m.badge.includes("Default") || m.badge.includes("Recommended"))),
            );
            return recs.length > 0 ? recs.slice(0, 30) : enrichedModels.slice(0, 20);
        }

        if (activeTab === "auto") {
            return enrichedModels.filter((m) => m.family === "auto" || m.category === "auto");
        }

        if (activeTab === "fast") {
            return enrichedModels.filter((m) => m.category === "fast");
        }

        if (activeTab === "reasoning") {
            return enrichedModels.filter((m) => m.category === "reasoning");
        }

        // 'all' tab
        return enrichedModels;
    }, [enrichedModels, search, activeTab]);

    // Group filtered models by provider if viewing all/search, or keep clean list
    const activeSelectedModel = useMemo(() => {
        return enrichedModels.find((m) => m.id === value);
    }, [enrichedModels, value]);

    if (variant === "Segmented") {
        const enabled = models.filter((model) => !model.disabled);
        const activeIndex = Math.max(
            0,
            enabled.findIndex((model) => model.id === value),
        );
        return (
            <div
                role="radiogroup"
                aria-label="Model"
                className={cn(
                    "relative isolate grid w-fit auto-cols-fr grid-flow-col rounded-lg bg-black/[0.04] p-0.5 dark:bg-white/[0.05]",
                    className,
                )}
            >
                <span
                    aria-hidden
                    className="absolute inset-y-0.5 left-0.5 -z-10 rounded-[7px] bg-white shadow-xs transition-transform duration-200 ease-[cubic-bezier(0.23,1,0.32,1)] dark:bg-neutral-800"
                    style={{
                        width: `calc((100% - 4px) / ${enabled.length})`,
                        transform: `translateX(${activeIndex * 100}%)`,
                    }}
                />
                {enabled.map((model) => {
                    const active = model.id === value;
                    return (
                        <button
                            key={model.id}
                            type="button"
                            role="radio"
                            aria-checked={active}
                            onClick={() => select(model.id)}
                            className={cn(
                                "rounded-[7px] px-3 py-1.5 font-mono text-[11.5px] whitespace-nowrap transition-[color,transform] duration-150 active:scale-[0.96]",
                                "focus-visible:ring-2 focus-visible:ring-neutral-400 focus-visible:outline-hidden",
                                active
                                    ? "font-medium text-neutral-900 dark:text-neutral-50"
                                    : "text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200",
                            )}
                        >
                            {model.name}
                        </button>
                    );
                })}
            </div>
        );
    }

    const tabs: { key: ModelCategory; label: string; icon: React.ComponentType<{ className?: string }> }[] = [
        { key: "recommended", label: "Recommended", icon: Sparkles },
        { key: "auto", label: "Auto", icon: Bot },
        { key: "fast", label: "Fast", icon: Zap },
        { key: "reasoning", label: "Reasoning", icon: BrainCircuit },
        { key: "all", label: "All", icon: LayoutGrid },
    ];

    return (
        <div role="radiogroup" aria-label="Model" className={cn("w-full space-y-2.5 select-none", className)}>
            {/* Category Pills Header */}
            {!search && (
                <div className="no-scrollbar border-border/40 flex items-center gap-1 overflow-x-auto border-b pb-1">
                    {tabs.map((tab) => {
                        const Icon = tab.icon;
                        const isTabActive = activeTab === tab.key;
                        const count = categoryCounts[tab.key];
                        if (count === 0 && tab.key !== "all" && tab.key !== "recommended") return null;

                        return (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveTab(tab.key)}
                                className={cn(
                                    "flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium transition-all",
                                    isTabActive
                                        ? "bg-primary text-primary-foreground font-semibold shadow-2xs"
                                        : "text-muted-foreground hover:text-foreground hover:bg-muted/60",
                                )}
                            >
                                <Icon className={cn("size-3.5", isTabActive ? "text-primary-foreground" : "text-muted-foreground")} />
                                <span>{tab.label}</span>
                                {tab.key === "all" && (
                                    <span
                                        className={cn(
                                            "font-mono text-[10px] opacity-70",
                                            isTabActive ? "text-primary-foreground" : "text-muted-foreground",
                                        )}
                                    >
                                        {count}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            )}

            {/* Search Bar */}
            {searchable && models.length > 3 && (
                <div className="relative">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                    <input
                        type="text"
                        ref={searchInputRef}
                        placeholder={search ? "Searching all models..." : `Search ${filteredModels.length} models or providers...`}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="border-border/80 bg-background/80 focus:ring-primary/20 focus:border-primary text-foreground placeholder:text-muted-foreground/60 h-8.5 w-full rounded-xl border pr-7 pl-8 font-sans text-xs transition-all focus:ring-2 focus:outline-none"
                    />
                    {search && (
                        <button
                            type="button"
                            onClick={() => setSearch("")}
                            className="text-muted-foreground hover:text-foreground hover:bg-muted absolute top-1/2 right-2 flex size-5 -translate-y-1/2 items-center justify-center rounded-md"
                        >
                            <X className="size-3.5" />
                        </button>
                    )}
                </div>
            )}

            {/* Currently Active Model Banner if not in filtered view */}
            {activeSelectedModel && !filteredModels.some((m) => m.id === value) && (
                <div className="bg-primary/10 border-primary/20 flex items-center justify-between rounded-lg border px-2.5 py-1.5 text-xs">
                    <div className="flex items-center gap-1.5 truncate">
                        <span className="text-primary text-[10px] font-semibold tracking-wider uppercase">Active:</span>
                        <span className="text-foreground truncate font-semibold">{activeSelectedModel.displayName}</span>
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            setSearch("");
                            setActiveTab("all");
                        }}
                        className="text-primary ml-2 shrink-0 text-[11px] font-medium hover:underline"
                    >
                        Locate
                    </button>
                </div>
            )}

            {/* Model Options List */}
            <div className="max-h-[300px] space-y-1.5 overflow-y-auto pr-1 focus:outline-none">
                {filteredModels.length === 0 ? (
                    <div className="text-muted-foreground space-y-1.5 py-8 text-center text-xs">
                        <Cpu className="text-muted-foreground mx-auto size-7 opacity-40" />
                        <p className="text-foreground text-sm font-semibold">No models found</p>
                        <p className="text-muted-foreground mx-auto max-w-[240px] text-[11px]">Try a different search term or switch categories.</p>
                        {search && (
                            <button type="button" onClick={() => setSearch("")} className="text-primary mt-2 text-xs font-medium hover:underline">
                                Clear search filter
                            </button>
                        )}
                    </div>
                ) : (
                    filteredModels.map((model) => {
                        const active = model.id === value;
                        const isClaude = model.family === "claude";
                        const isOpenAi = model.family === "openai";
                        const isGemini = model.family === "gemini";
                        const isAuto = model.family === "auto";

                        return (
                            <button
                                key={model.id}
                                type="button"
                                role="radio"
                                aria-checked={active}
                                disabled={model.disabled}
                                onClick={() => select(model.id)}
                                className={cn(
                                    "group flex w-full cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2 text-left transition-all duration-150",
                                    "focus-visible:ring-primary/40 focus-visible:ring-2 focus-visible:outline-none",
                                    model.disabled
                                        ? "border-border/40 cursor-not-allowed opacity-40"
                                        : active
                                          ? "border-primary bg-primary/10 dark:bg-primary/15 ring-primary/30 shadow-xs ring-1"
                                          : "border-border/60 hover:border-border hover:bg-muted/50 active:scale-[0.995]",
                                )}
                            >
                                {/* Family Icon Avatar */}
                                <div
                                    className={cn(
                                        "flex size-7 shrink-0 items-center justify-center rounded-lg border transition-colors",
                                        isClaude
                                            ? "border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                                            : isOpenAi
                                              ? "border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                              : isGemini
                                                ? "border-sky-500/20 bg-sky-500/10 text-sky-600 dark:text-sky-400"
                                                : isAuto
                                                  ? "border-indigo-500/20 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                                                  : "bg-muted border-border/60 text-muted-foreground",
                                    )}
                                >
                                    {isAuto ? (
                                        <Bot className="size-3.5" />
                                    ) : isClaude || isGemini ? (
                                        <Sparkles className="size-3.5" />
                                    ) : (
                                        <Cpu className="size-3.5" />
                                    )}
                                </div>

                                {/* Model Details */}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-1.5">
                                        <span
                                            className={cn(
                                                "truncate text-xs",
                                                active ? "text-foreground font-semibold" : "text-foreground/90 font-medium",
                                            )}
                                        >
                                            {model.displayName}
                                        </span>

                                        {model.tag && (
                                            <span
                                                className={cn(
                                                    "py-0.2 shrink-0 rounded-md px-1.5 font-mono text-[9px] font-semibold tracking-wide uppercase",
                                                    active
                                                        ? "bg-primary/20 text-primary border-primary/30 border"
                                                        : "bg-muted text-muted-foreground/90 border-border/60 border",
                                                )}
                                            >
                                                {model.tag}
                                            </span>
                                        )}
                                    </div>

                                    <div className="text-muted-foreground/80 mt-0.5 flex items-center gap-1.5 text-[10.5px]">
                                        <span className="text-foreground/70 font-medium">{model.displayProvider}</span>
                                        <span className="opacity-40">•</span>
                                        <span className="max-w-[180px] truncate font-mono text-[10px] opacity-70">{model.id}</span>
                                    </div>
                                </div>

                                {/* Radio Checkmark Circle */}
                                <span
                                    aria-hidden
                                    className={cn(
                                        "grid size-4 shrink-0 place-items-center rounded-full border transition-colors duration-150",
                                        active
                                            ? "border-primary bg-primary text-primary-foreground"
                                            : "border-muted-foreground/40 group-hover:border-foreground/50 bg-transparent",
                                    )}
                                >
                                    {active && <Check className="size-2.5 stroke-[3]" />}
                                </span>
                            </button>
                        );
                    })
                )}
            </div>
        </div>
    );
}

export default ModelSelector;
