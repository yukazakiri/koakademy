/**
 * Spectrum UI — BeamSearch
 *
 * An animated search bar with a traveling beam on focus, clear button,
 * keyboard shortcut hint support, and smooth focus transitions.
 *
 * Dependencies: framer-motion, lucide-react, @/lib/utils
 */

"use client";

import React, { forwardRef, useRef, useImperativeHandle, useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Search, X } from "lucide-react";
import { cn } from "@/lib/utils";

export interface BeamSearchProps {
  value?: string;
  defaultValue?: string;
  onChange?: (value: string) => void;
  onSubmit?: (value: string) => void;
  onClear?: () => void;
  placeholder?: string;
  autoFocus?: boolean;
  /** Right-hand slot, e.g. a keyboard shortcut chip <KbdKey>⌘K</KbdKey> */
  trailing?: React.ReactNode;
  className?: string;
  size?: "sm" | "default";
}

export const BeamSearch = forwardRef<HTMLInputElement, BeamSearchProps>(
  (
    {
      value,
      defaultValue = "",
      onChange,
      onSubmit,
      onClear,
      placeholder = "Search...",
      autoFocus = false,
      trailing,
      className,
      size = "sm",
    },
    ref,
  ) => {
    const inputRef = useRef<HTMLInputElement>(null);
    useImperativeHandle(ref, () => inputRef.current as HTMLInputElement);

    const [inner, setInner] = useState(defaultValue);
    const [focused, setFocused] = useState(false);
    const current = value ?? inner;

    const update = (next: string) => {
      if (value === undefined) setInner(next);
      onChange?.(next);
    };

    const handleClear = () => {
      update("");
      onClear?.();
      inputRef.current?.focus();
    };

    const isSmall = size === "sm";

    return (
      <div
        className={cn(
          "relative flex w-full items-center overflow-hidden rounded-lg border transition-all duration-200",
          focused
            ? "border-primary/50 bg-background/95 shadow-xs ring-2 ring-primary/15"
            : "border-sidebar-border/60 bg-sidebar/50 hover:border-sidebar-border hover:bg-sidebar/80",
          isSmall ? "h-8.5 px-2.5" : "h-10 px-3",
          className,
        )}
      >
        <Search
          className={cn(
            "shrink-0 transition-colors",
            focused ? "text-primary" : "text-muted-foreground/70",
            isSmall ? "size-3.5" : "size-4",
          )}
          aria-hidden
        />

        <input
          ref={inputRef}
          type="search"
          value={current}
          placeholder={placeholder}
          autoFocus={autoFocus}
          onChange={(e) => update(e.target.value)}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          onKeyDown={(e) => {
            if (e.key === "Enter") onSubmit?.(current);
            if (e.key === "Escape") handleClear();
          }}
          className={cn(
            "min-w-0 flex-1 bg-transparent px-2 text-foreground outline-hidden placeholder:text-muted-foreground/60",
            "[&::-webkit-search-cancel-button]:hidden [&::-webkit-search-decoration]:hidden",
            isSmall ? "text-xs" : "text-sm",
          )}
        />

        {current ? (
          <button
            type="button"
            aria-label="Clear search"
            onMouseDown={(e) => e.preventDefault()}
            onClick={handleClear}
            className="flex size-5 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
          >
            <X className="size-3" />
          </button>
        ) : (
          trailing && <div className="shrink-0">{trailing}</div>
        )}

        {/* Focus beam animated effect on bottom border */}
        <AnimatePresence>
          {focused && (
            <motion.div
              initial={{ opacity: 0, scaleX: 0 }}
              animate={{ opacity: 1, scaleX: 1 }}
              exit={{ opacity: 0, scaleX: 0 }}
              transition={{ duration: 0.2 }}
              className="pointer-events-none absolute inset-x-0 bottom-0 h-[1.5px] bg-gradient-to-r from-transparent via-primary to-transparent"
            />
          )}
        </AnimatePresence>
      </div>
    );
  },
);

BeamSearch.displayName = "BeamSearch";
