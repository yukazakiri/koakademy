/**
 * Spectrum UI — NotificationBell
 *
 * A bell icon button micro-interaction. When the unread count increases the
 * bell swings from its hinge like a settling pendulum while the clapper
 * wiggles the opposite way in phase, and a badge springs in and rolls
 * its count like an odometer. A dot mode swaps the number for an indicator
 * that pings once per increase. Honors prefers-reduced-motion and announces
 * unread changes to screen readers.
 *
 * Dependencies: framer-motion, @/lib/utils
 */

"use client";

import React, { useEffect, useRef, useState, forwardRef } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { cn } from "@/lib/utils";

// ─── Types ───────────────────────────────────────────────────────────────────

export interface NotificationBellProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Number of unread notifications. Default 0 */
  count?: number;
  /** Counts above this render as "max+". Default 99 */
  max?: number;
  /** Show a small pinging dot instead of the numeric badge. Default false */
  dot?: boolean;
  /** Play the ring swing once when the component mounts. Default false */
  ringOnMount?: boolean;
  /** Visual size of the button. Default "md" */
  size?: "sm" | "md" | "lg";
  className?: string;
}

// ─── Constants ───────────────────────────────────────────────────────────────

const SWING_DURATION = 0.9;
const BELL_SWING = [0, 15, -12, 8, -5, 3, -1.5, 0];
const CLAPPER_SWING = [0, -17, 14, -10, 6, -3.5, 2, 0];
const SWING_TIMES = [0, 0.1, 0.26, 0.42, 0.58, 0.74, 0.88, 1];
const SWING_EASE = "easeInOut" as const;

const PING_DURATION = 0.9;
const BADGE_SPRING = { type: "spring", stiffness: 500, damping: 22 } as const;
const COUNT_SPRING = { type: "spring", stiffness: 400, damping: 30 } as const;
const TAP_SPRING = { type: "spring", stiffness: 500, damping: 30 } as const;

const BELL_DOME_PATH = "M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9";
const BELL_CLAPPER_PATH = "M10.3 21a1.94 1.94 0 0 0 3.4 0";

const SIZES = {
  sm: { button: "h-8 w-8", icon: 16 },
  md: { button: "h-9 w-9", icon: 18 },
  lg: { button: "h-11 w-11", icon: 20 },
} as const;

const countVariants = {
  enter: (direction: number) => ({ y: direction * 10, opacity: 0 }),
  center: { y: 0, opacity: 1 },
  exit: (direction: number) => ({ y: direction * -10, opacity: 0 }),
};

// ─── Component ───────────────────────────────────────────────────────────────

export const NotificationBell = forwardRef<HTMLButtonElement, NotificationBellProps>(
  (
    {
      count = 0,
      max = 99,
      dot = false,
      ringOnMount = false,
      onClick,
      size = "md",
      className,
      ...props
    },
    ref,
  ) => {
    const shouldReduceMotion = useReducedMotion();
    const prevCountRef = useRef(count);
    const [ringKey, setRingKey] = useState(() => (ringOnMount ? 1 : 0));

    const { button: sizeClasses, icon } = SIZES[size];
    const displayValue = count > max ? `${max}+` : String(count);
    const direction = count >= prevCountRef.current ? 1 : -1;

    useEffect(() => {
      if (count > prevCountRef.current) setRingKey((key) => key + 1);
      prevCountRef.current = count;
    }, [count]);

    const swinging = ringKey > 0 && !shouldReduceMotion;

    const swingTransition = swinging
      ? { duration: SWING_DURATION, times: SWING_TIMES, ease: SWING_EASE }
      : { duration: 0 };

    const badgeTransition = shouldReduceMotion ? { duration: 0 } : BADGE_SPRING;

    return (
      <motion.button
        ref={ref}
        type="button"
        onClick={onClick}
        aria-label={count > 0 ? `Notifications, ${count} unread` : "Notifications"}
        whileTap={shouldReduceMotion ? undefined : { scale: 0.94 }}
        transition={TAP_SPRING}
        className={cn(
          "relative inline-flex touch-manipulation select-none items-center justify-center rounded-lg border transition-colors",
          "border-sidebar-border/60 bg-sidebar text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-foreground",
          "focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring",
          sizeClasses,
          className,
        )}
        {...(props as any)}
      >
        <motion.span
          key={`bell-${ringKey}`}
          className="inline-flex"
          style={{ transformOrigin: "top center" }}
          initial={{ rotate: 0 }}
          animate={swinging ? { rotate: BELL_SWING } : { rotate: 0 }}
          transition={swingTransition}
        >
          <svg
            viewBox="0 0 24 24"
            width={icon}
            height={icon}
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
          >
            <path d={BELL_DOME_PATH} />
            <motion.path
              d={BELL_CLAPPER_PATH}
              style={{ transformBox: "fill-box", transformOrigin: "top center" }}
              initial={{ rotate: 0 }}
              animate={swinging ? { rotate: CLAPPER_SWING } : { rotate: 0 }}
              transition={swingTransition}
            />
          </svg>
        </motion.span>

        {dot ? (
          <AnimatePresence initial={false}>
            {count > 0 && (
              <motion.span
                key="dot"
                aria-hidden="true"
                className="absolute right-1 top-1 flex h-2 w-2"
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                exit={{ scale: 0 }}
                transition={badgeTransition}
              >
                {swinging && (
                  <motion.span
                    key={`ping-${ringKey}`}
                    className="absolute inset-0 rounded-full bg-rose-500"
                    initial={{ scale: 1, opacity: 0.6 }}
                    animate={{ scale: 2, opacity: 0 }}
                    transition={{ duration: PING_DURATION, ease: "easeOut" }}
                  />
                )}
                <span className="relative h-2 w-2 rounded-full bg-rose-500" />
              </motion.span>
            )}
          </AnimatePresence>
        ) : (
          <AnimatePresence initial={false}>
            {count > 0 && (
              <motion.span
                key="badge"
                aria-hidden="true"
                className="absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold leading-none text-white shadow-xs"
                style={{ transformOrigin: "left bottom" }}
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                exit={{ scale: 0 }}
                transition={badgeTransition}
              >
                <span className="relative inline-flex overflow-hidden tabular-nums">
                  <AnimatePresence mode="popLayout" initial={false} custom={direction}>
                    <motion.span
                      key={displayValue}
                      className="inline-block"
                      custom={direction}
                      variants={countVariants}
                      initial="enter"
                      animate="center"
                      exit="exit"
                      transition={shouldReduceMotion ? { duration: 0 } : COUNT_SPRING}
                    >
                      {displayValue}
                    </motion.span>
                  </AnimatePresence>
                </span>
              </motion.span>
            )}
          </AnimatePresence>
        )}

        <span className="sr-only" role="status" aria-live="polite">
          {count > 0 ? `${count} unread notification${count === 1 ? "" : "s"}` : ""}
        </span>
      </motion.button>
    );
  },
);

NotificationBell.displayName = "NotificationBell";
