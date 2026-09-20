'use client';

import { useMemo, useState } from 'react';
import {
  Check,
  Cpu,
  Search,
  Server,
  Sparkles,
  Zap,
  Bot,
  LayoutGrid,
  X,
  BrainCircuit,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ModelOption } from './types';

export type ModelSelectorVariant = 'List' | 'Segmented';

export interface ModelSelectorProps {
  models: ModelOption[];
  value?: string;
  onChange?: (id: string) => void;
  variant?: ModelSelectorVariant;
  className?: string;
  searchable?: boolean;
}

type ModelCategory = 'recommended' | 'auto' | 'fast' | 'reasoning' | 'all';

/**
 * Humanize model IDs into friendly titles, clean provider names, and category tags.
 */
function humanizeModel(model: ModelOption): {
  displayName: string;
  family: 'claude' | 'openai' | 'gemini' | 'deepseek' | 'auto' | 'generic';
  displayProvider: string;
  category: 'recommended' | 'auto' | 'fast' | 'reasoning' | 'general';
  tag?: string;
} {
  const rawId = model.id || '';
  const rawName = model.name || rawId;
  const cleanId = rawId.includes(':') ? rawId.split(':')[1] : rawId;
  const lower = cleanId.toLowerCase();

  // Determine Family
  let family: 'claude' | 'openai' | 'gemini' | 'deepseek' | 'auto' | 'generic' = 'generic';
  if (lower.includes('claude') || lower.includes('fable') || lower.includes('sonnet') || lower.includes('opus')) {
    family = 'claude';
  } else if (lower.includes('gpt') || lower.includes('codex') || lower.includes('openai') || lower.includes('o1') || lower.includes('o3')) {
    family = 'openai';
  } else if (lower.includes('gemini')) {
    family = 'gemini';
  } else if (lower.includes('deepseek')) {
    family = 'deepseek';
  } else if (lower.startsWith('auto/')) {
    family = 'auto';
  }

  // Determine Category
  let category: 'recommended' | 'auto' | 'fast' | 'reasoning' | 'general' = 'general';
  if (
    lower.includes('best-free') ||
    lower.includes('best-chat') ||
    lower.includes('sonnet-4-6') ||
    lower.includes('gemini-3.8-flash-high') ||
    lower.includes('gpt-5.6-luna-high') ||
    lower.includes('deepseek-v4-pro') ||
    (model.badge && (model.badge.includes('Default') || model.badge.includes('Recommended')))
  ) {
    category = 'recommended';
  } else if (lower.startsWith('auto/')) {
    category = 'auto';
  } else if (
    lower.includes('fast') ||
    lower.includes('flash') ||
    lower.includes('mini') ||
    lower.includes('haiku') ||
    lower.includes('turbo')
  ) {
    category = 'fast';
  } else if (
    lower.includes('reasoning') ||
    lower.includes('thinking') ||
    lower.includes('opus') ||
    lower.includes('r1')
  ) {
    category = 'reasoning';
  }

  // Humanize Display Name
  let displayName = rawName;
  if (displayName === cleanId || displayName.includes('/') || displayName.includes(':')) {
    // Clean up prefixes like dva/, oc/, cx/, agy/, no-think/
    const stripped = cleanId.replace(/^(?:no-think\/|dva\/|oc\/|cx\/|cxa\/|agy\/|zed-hosted\/)+/, '');
    if (stripped.startsWith('auto/')) {
      displayName = 'Auto: ' + stripped.replace('auto/', '').split('-').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
    } else {
      displayName = stripped
        .split(/[-_/]/)
        .map((w) => (w.length <= 3 ? w.toUpperCase() : w.charAt(0).toUpperCase() + w.slice(1)))
        .join(' ');
    }
  }

  // Display Provider
  let displayProvider = model.provider_name || model.provider || 'AI';
  if (displayProvider.toLowerCase() === 'omni') {
    displayProvider = family === 'auto' ? 'OmniRoute' : family.charAt(0).toUpperCase() + family.slice(1);
  }

  // Capability Tag
  let tag = model.badge;
  if (!tag) {
    if (category === 'recommended') tag = 'Recommended';
    else if (category === 'fast') tag = 'Fast';
    else if (category === 'reasoning') tag = 'Thinking';
    else if (family === 'auto') tag = 'Auto';
  }

  return { displayName, family, displayProvider, category, tag };
}

export function ModelSelector({
  models,
  value: valueProp,
  onChange,
  variant = 'List',
  className,
  searchable = true,
}: ModelSelectorProps) {
  const [valueState, setValueState] = useState(models.find((m) => !m.disabled)?.id);
  const [search, setSearch] = useState('');
  const [activeTab, setActiveTab] = useState<ModelCategory>('recommended');

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
      if (m.category === 'recommended') counts.recommended++;
      if (m.family === 'auto' || m.category === 'auto') counts.auto++;
      if (m.category === 'fast') counts.fast++;
      if (m.category === 'reasoning') counts.reasoning++;
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
        const id = (m.id || '').toLowerCase();
        const provider = (m.displayProvider || '').toLowerCase();
        const desc = (m.description || '').toLowerCase();
        return name.includes(q) || id.includes(q) || provider.includes(q) || desc.includes(q);
      });
    }

    // Otherwise filter by category tab
    if (activeTab === 'recommended') {
      const recs = enrichedModels.filter(
        (m) =>
          m.category === 'recommended' ||
          (m.badge && (m.badge.includes('Default') || m.badge.includes('Recommended')))
      );
      return recs.length > 0 ? recs.slice(0, 30) : enrichedModels.slice(0, 20);
    }

    if (activeTab === 'auto') {
      return enrichedModels.filter((m) => m.family === 'auto' || m.category === 'auto');
    }

    if (activeTab === 'fast') {
      return enrichedModels.filter((m) => m.category === 'fast');
    }

    if (activeTab === 'reasoning') {
      return enrichedModels.filter((m) => m.category === 'reasoning');
    }

    // 'all' tab
    return enrichedModels;
  }, [enrichedModels, search, activeTab]);

  // Group filtered models by provider if viewing all/search, or keep clean list
  const activeSelectedModel = useMemo(() => {
    return enrichedModels.find((m) => m.id === value);
  }, [enrichedModels, value]);

  if (variant === 'Segmented') {
    const enabled = models.filter((model) => !model.disabled);
    const activeIndex = Math.max(0, enabled.findIndex((model) => model.id === value));
    return (
      <div
        role="radiogroup"
        aria-label="Model"
        className={cn(
          'relative isolate grid w-fit grid-flow-col auto-cols-fr rounded-lg bg-black/[0.04] p-0.5 dark:bg-white/[0.05]',
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
                'whitespace-nowrap rounded-[7px] px-3 py-1.5 font-mono text-[11.5px] transition-[color,transform] duration-150 active:scale-[0.96]',
                'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-neutral-400',
                active
                  ? 'font-medium text-neutral-900 dark:text-neutral-50'
                  : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200',
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
    { key: 'recommended', label: 'Recommended', icon: Sparkles },
    { key: 'auto', label: 'Auto', icon: Bot },
    { key: 'fast', label: 'Fast', icon: Zap },
    { key: 'reasoning', label: 'Reasoning', icon: BrainCircuit },
    { key: 'all', label: 'All', icon: LayoutGrid },
  ];

  return (
    <div
      role="radiogroup"
      aria-label="Model"
      className={cn('w-full space-y-2.5 select-none', className)}
    >
      {/* Category Pills Header */}
      {!search && (
        <div className="flex items-center gap-1 overflow-x-auto pb-1 no-scrollbar border-b border-border/40">
          {tabs.map((tab) => {
            const Icon = tab.icon;
            const isTabActive = activeTab === tab.key;
            const count = categoryCounts[tab.key];
            if (count === 0 && tab.key !== 'all' && tab.key !== 'recommended') return null;

            return (
              <button
                key={tab.key}
                type="button"
                onClick={() => setActiveTab(tab.key)}
                className={cn(
                  'flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium transition-all shrink-0 cursor-pointer',
                  isTabActive
                    ? 'bg-primary text-primary-foreground shadow-2xs font-semibold'
                    : 'text-muted-foreground hover:text-foreground hover:bg-muted/60'
                )}
              >
                <Icon className={cn('size-3.5', isTabActive ? 'text-primary-foreground' : 'text-muted-foreground')} />
                <span>{tab.label}</span>
                {tab.key === 'all' && (
                  <span className={cn('text-[10px] font-mono opacity-70', isTabActive ? 'text-primary-foreground' : 'text-muted-foreground')}>
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
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground pointer-events-none" />
          <input
            type="text"
            placeholder={search ? 'Searching all models...' : `Search ${filteredModels.length} models or providers...`}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full h-8.5 pl-8 pr-7 text-xs rounded-xl border border-border/80 bg-background/80 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-foreground placeholder:text-muted-foreground/60 transition-all font-sans"
          />
          {search && (
            <button
              type="button"
              onClick={() => setSearch('')}
              className="absolute right-2 top-1/2 -translate-y-1/2 size-5 rounded-md flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted"
            >
              <X className="size-3.5" />
            </button>
          )}
        </div>
      )}

      {/* Currently Active Model Banner if not in filtered view */}
      {activeSelectedModel && !filteredModels.some((m) => m.id === value) && (
        <div className="flex items-center justify-between px-2.5 py-1.5 rounded-lg bg-primary/10 border border-primary/20 text-xs">
          <div className="flex items-center gap-1.5 truncate">
            <span className="text-[10px] font-semibold uppercase tracking-wider text-primary">Active:</span>
            <span className="font-semibold text-foreground truncate">{activeSelectedModel.displayName}</span>
          </div>
          <button
            type="button"
            onClick={() => {
              setSearch('');
              setActiveTab('all');
            }}
            className="text-[11px] text-primary hover:underline shrink-0 ml-2 font-medium"
          >
            Locate
          </button>
        </div>
      )}

      {/* Model Options List */}
      <div className="max-h-[300px] overflow-y-auto space-y-1.5 pr-1 focus:outline-none">
        {filteredModels.length === 0 ? (
          <div className="py-8 text-center text-xs text-muted-foreground space-y-1.5">
            <Cpu className="size-7 mx-auto opacity-40 text-muted-foreground" />
            <p className="font-semibold text-foreground text-sm">No models found</p>
            <p className="text-[11px] max-w-[240px] mx-auto text-muted-foreground">
              Try a different search term or switch categories.
            </p>
            {search && (
              <button
                type="button"
                onClick={() => setSearch('')}
                className="mt-2 text-xs text-primary font-medium hover:underline"
              >
                Clear search filter
              </button>
            )}
          </div>
        ) : (
          filteredModels.map((model) => {
            const active = model.id === value;
            const isClaude = model.family === 'claude';
            const isOpenAi = model.family === 'openai';
            const isGemini = model.family === 'gemini';
            const isAuto = model.family === 'auto';

            return (
              <button
                key={model.id}
                type="button"
                role="radio"
                aria-checked={active}
                disabled={model.disabled}
                onClick={() => select(model.id)}
                className={cn(
                  'group flex w-full items-center gap-2.5 rounded-xl border px-3 py-2 text-left cursor-pointer transition-all duration-150',
                  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40',
                  model.disabled
                    ? 'cursor-not-allowed border-border/40 opacity-40'
                    : active
                      ? 'border-primary bg-primary/10 dark:bg-primary/15 shadow-xs ring-1 ring-primary/30'
                      : 'border-border/60 hover:border-border hover:bg-muted/50 active:scale-[0.995]'
                )}
              >
                {/* Family Icon Avatar */}
                <div
                  className={cn(
                    'size-7 rounded-lg flex items-center justify-center shrink-0 border transition-colors',
                    isClaude
                      ? 'bg-amber-500/10 border-amber-500/20 text-amber-600 dark:text-amber-400'
                      : isOpenAi
                        ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                        : isGemini
                          ? 'bg-sky-500/10 border-sky-500/20 text-sky-600 dark:text-sky-400'
                          : isAuto
                            ? 'bg-indigo-500/10 border-indigo-500/20 text-indigo-600 dark:text-indigo-400'
                            : 'bg-muted border-border/60 text-muted-foreground'
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
                        'truncate text-xs',
                        active ? 'font-semibold text-foreground' : 'font-medium text-foreground/90'
                      )}
                    >
                      {model.displayName}
                    </span>

                    {model.tag && (
                      <span
                        className={cn(
                          'shrink-0 rounded-md px-1.5 py-0.2 font-mono text-[9px] font-semibold tracking-wide uppercase',
                          active
                            ? 'bg-primary/20 text-primary border border-primary/30'
                            : 'bg-muted text-muted-foreground/90 border border-border/60'
                        )}
                      >
                        {model.tag}
                      </span>
                    )}
                  </div>

                  <div className="flex items-center gap-1.5 text-[10.5px] text-muted-foreground/80 mt-0.5">
                    <span className="font-medium text-foreground/70">{model.displayProvider}</span>
                    <span className="opacity-40">•</span>
                    <span className="font-mono text-[10px] truncate max-w-[180px] opacity-70">{model.id}</span>
                  </div>
                </div>

                {/* Radio Checkmark Circle */}
                <span
                  aria-hidden
                  className={cn(
                    'grid size-4 shrink-0 place-items-center rounded-full border transition-colors duration-150',
                    active
                      ? 'border-primary bg-primary text-primary-foreground'
                      : 'border-muted-foreground/40 bg-transparent group-hover:border-foreground/50'
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
