/**
 * Spectrum UI — NavListCard
 *
 * A compact navigation card that lists links with icons, with a spring slide on hover.
 *
 * Dependencies: framer-motion, lucide-react, @/lib/utils
 */

"use client";

import * as React from "react";
import { motion } from "framer-motion";
import { cn } from "@/lib/utils";

export interface NavListItem {
  icon?: React.ReactNode;
  label: string;
  href?: string;
  badge?: React.ReactNode;
  active?: boolean;
}

export interface NavListCardProps {
  title?: string;
  items?: NavListItem[];
  className?: string;
}

export function NavListCard({
  title,
  items = [],
  className,
}: NavListCardProps) {
  return (
    <div
      className={cn(
        "w-full rounded-xl bg-sidebar/50 p-2 border border-sidebar-border/60",
        className,
      )}
    >
      {title && (
        <p className="px-3 pb-1 text-[11px] font-medium tracking-wider uppercase text-muted-foreground">
          {title}
        </p>
      )}
      <ul className="space-y-0.5">
        {items.map((item) => {
          const inner = (
            <motion.span
              whileHover={{ x: 2 }}
              whileTap={{ scale: 0.98 }}
              transition={{ type: "spring", bounce: 0.35, duration: 0.25 }}
              className={cn(
                "flex h-8 items-center gap-2 rounded-lg px-2.5 text-xs font-medium transition-colors",
                item.active
                  ? "bg-primary/10 text-primary font-semibold"
                  : "text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-foreground",
              )}
            >
              {item.icon ? (
                <span className="flex shrink-0 text-current [&_svg]:size-4">
                  {item.icon}
                </span>
              ) : null}
              <span className="truncate flex-1">
                {item.label}
              </span>
              {item.badge}
            </motion.span>
          );

          return (
            <li key={item.label}>
              {item.href ? (
                <a href={item.href} className="block">
                  {inner}
                </a>
              ) : (
                <button type="button" className="block w-full text-left">
                  {inner}
                </button>
              )}
            </li>
          );
        })}
      </ul>
    </div>
  );
}
