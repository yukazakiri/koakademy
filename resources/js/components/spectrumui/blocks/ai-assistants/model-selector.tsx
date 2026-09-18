'use client';

import { useMemo, useState } from 'react';
import { Check, Cpu, Search, Server, Sparkles, X } from 'lucide-react';
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
  const value = valueProp !== undefined ? valueProp : valueState;

  function select(id: string) {
    setValueState(id);
    onChange?.(id);
  }

  // Filter models by search query
  const filteredModels = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return models;

    return models.filter((m) => {
      const name = (m.name || '').toLowerCase();
      const id = (m.id || '').toLowerCase();
      const provider = (m.provider_name || m.provider || '').toLowerCase();
      const desc = (m.description || '').toLowerCase();

      return name.includes(q) || id.includes(q) || provider.includes(q) || desc.includes(q);
    });
  }, [models, search]);

  // Group models by provider
  const groupedModels = useMemo(() => {
    const groups: Record<string, ModelOption[]> = {};

    filteredModels.forEach((m) => {
      const groupName = m.provider_name || m.provider || 'Available Models';
      if (!groups[groupName]) {
        groups[groupName] = [];
      }
      groups[groupName].push(m);
    });

    return groups;
  }, [filteredModels]);

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

  const groupKeys = Object.keys(groupedModels);

  return (
    <div
      role="radiogroup"
      aria-label="Model"
      className={cn('w-full space-y-2', className)}
    >
      {/* Search Bar */}
      {searchable && models.length > 2 && (
        <div className="relative mb-2">
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground pointer-events-none" />
          <input
            type="text"
            placeholder="Search models or providers..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full h-8 pl-8 pr-7 text-xs rounded-lg border border-border/80 bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-foreground placeholder:text-muted-foreground/60 transition-all font-sans"
            autoFocus
          />
          {search && (
            <button
              type="button"
              onClick={() => setSearch('')}
              className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
            >
              <X className="size-3.5" />
            </button>
          )}
        </div>
      )}

      {/* Model Options List - Grouped by Provider */}
      <div className="max-h-72 overflow-y-auto space-y-3 pr-1">
        {groupKeys.length === 0 ? (
          <div className="py-6 text-center text-xs text-muted-foreground space-y-1">
            <Cpu className="size-6 mx-auto opacity-40 text-muted-foreground" />
            <p className="font-medium text-foreground">No models found</p>
            <p className="text-[11px]">No models match your search criteria.</p>
          </div>
        ) : (
          groupKeys.map((groupName) => {
            const groupItems = groupedModels[groupName];
            return (
              <div key={groupName} className="space-y-1">
                {/* Group Header */}
                <div className="flex items-center justify-between px-2 py-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground border-b border-border/40 mb-1">
                  <span className="flex items-center gap-1.5 truncate">
                    {groupName.toLowerCase().includes('custom') || groupName.toLowerCase().includes('vllm') ? (
                      <Server className="size-3 text-primary" />
                    ) : (
                      <Sparkles className="size-3 text-indigo-500" />
                    )}
                    {groupName}
                  </span>
                  <span className="font-mono text-[10px] font-normal opacity-70">
                    {groupItems.length} {groupItems.length === 1 ? 'model' : 'models'}
                  </span>
                </div>

                {/* Model items in this group */}
                <div className="space-y-1">
                  {groupItems.map((model) => {
                    const active = model.id === value;
                    return (
                      <button
                        key={model.id}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        disabled={model.disabled}
                        onClick={() => select(model.id)}
                        className={cn(
                          'flex w-full items-center gap-2.5 rounded-xl border px-3 py-2 text-left cursor-pointer',
                          'transition-all duration-150',
                          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40',
                          model.disabled
                            ? 'cursor-not-allowed border-border/40 opacity-40'
                            : active
                              ? 'border-primary bg-primary/5 dark:bg-primary/10 shadow-xs'
                              : 'border-border/60 hover:border-border hover:bg-muted/40 active:scale-[0.99]',
                        )}
                      >
                        {/* Radio Checkmark Circle */}
                        <span
                          aria-hidden
                          className={cn(
                            'grid size-4 shrink-0 place-items-center rounded-full border transition-colors duration-150',
                            active
                              ? 'border-primary bg-primary text-primary-foreground'
                              : 'border-muted-foreground/40 bg-transparent',
                          )}
                        >
                          {active && <Check className="size-2.5 stroke-[3]" />}
                        </span>

                        {/* Model Details */}
                        <span className="min-w-0 flex-1">
                          <span className={cn(
                            'block truncate text-xs',
                            active ? 'font-semibold text-foreground' : 'font-medium text-foreground/90'
                          )}>
                            {model.name}
                          </span>
                          {model.description && (
                            <span className="block truncate text-[11px] text-muted-foreground/80 mt-0.5">
                              {model.description}
                            </span>
                          )}
                        </span>

                        {/* Badge */}
                        {model.badge && (
                          <span className={cn(
                            'shrink-0 rounded-md px-1.5 py-0.5 font-mono text-[9.5px] font-medium tracking-wide',
                            active
                              ? 'bg-primary/15 text-primary border border-primary/20'
                              : 'bg-muted text-muted-foreground border border-border/60'
                          )}>
                            {model.badge}
                          </span>
                        )}
                      </button>
                    );
                  })}
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}

export default ModelSelector;
