/**
 * Spectrum UI — KbdKey & KbdCombo
 *
 * A semantic <kbd> element rendered as a 3D keycap that physically depresses
 * when the real key is pressed — via a global keydown listener or a pointer
 * tap. Press-down is instant (keys feel mechanical) and release springs back
 * with a tiny overshoot. KbdCombo strings caps together ("meta+k"); when
 * every key is held at once the whole combo pulses and onTrigger fires.
 * Honors prefers-reduced-motion.
 *
 * Dependencies: framer-motion, @/lib/utils
 */

"use client";

import React, { useCallback, useEffect, useRef, useState } from "react";
import { motion, useAnimationControls, useReducedMotion } from "framer-motion";
import { cn } from "@/lib/utils";

// ─── Types ───────────────────────────────────────────────────────────────────

export interface KbdKeyProps {
  /** Cap legend, e.g. "K", "⌘" or "esc" */
  children: React.ReactNode;
  /**
   * Key to match against KeyboardEvent.key, case-insensitively. Friendly
   * names are supported: "meta"/"cmd", "ctrl", "shift", "alt"/"option",
   * "enter", "escape"/"esc", "space", "up"/"down"/"left"/"right". Derived
   * from children when it is a plain string; symbol legends like "⌘" need
   * an explicit keyName
   */
  keyName?: string;
  /** Depress the cap while the real key is held (window listener). Default false */
  listen?: boolean;
  /** Fires once per press — on a matching keydown or a pointer tap. Also wraps the cap in a button */
  onPress?: () => void;
  /** Visual size of the cap. Default "md" */
  size?: "xs" | "sm" | "md";
  className?: string;
}

export interface KbdComboProps {
  /** "+"-separated keys, e.g. "meta+k" or "shift+?" */
  keys: string;
  /** Depress each cap while its real key is held. Default false */
  listen?: boolean;
  /** Fires once each time every key in the combo is held down simultaneously */
  onTrigger?: () => void;
  /** Visual size of the caps. Default "md" */
  size?: "xs" | "sm" | "md";
  className?: string;
}

// ─── Constants ───────────────────────────────────────────────────────────────

const PRESS_DEPTH = 1.5;
const PRESS_TRANSITION = { duration: 0.08, ease: "easeOut" } as const;
const RELEASE_SPRING = { type: "spring", stiffness: 500, damping: 18 } as const;
const PULSE_SCALE = [1, 1.06, 1];
const PULSE_TRANSITION = { duration: 0.25, ease: "easeOut" } as const;

const KEY_ALIASES: Record<string, string> = {
  meta: "Meta",
  cmd: "Meta",
  command: "Meta",
  ctrl: "Control",
  control: "Control",
  shift: "Shift",
  alt: "Alt",
  option: "Alt",
  enter: "Enter",
  return: "Enter",
  escape: "Escape",
  esc: "Escape",
  space: " ",
  up: "ArrowUp",
  down: "ArrowDown",
  left: "ArrowLeft",
  right: "ArrowRight",
};

const KEY_GLYPHS: Record<string, string> = {
  Meta: "⌘",
  Shift: "⇧",
  Alt: "⌥",
  Control: "⌃",
  Enter: "↵",
  " ": "␣",
  Escape: "esc",
  ArrowUp: "↑",
  ArrowDown: "↓",
  ArrowLeft: "←",
  ArrowRight: "→",
};

const MODIFIERS = new Set(["meta", "control", "shift", "alt"]);

const SIZES = {
  xs: "h-4.5 min-w-[18px] px-1 text-[9px]",
  sm: "h-5 min-w-[20px] px-1 text-[10px]",
  md: "h-6 min-w-[24px] px-1.5 text-xs",
} as const;

const EDGE_SHADOW = "shadow-[0_1.5px_0_0_#d4d4d4] dark:shadow-[0_1.5px_0_0_#404040]";
const EDGE_SHADOW_PRESSED = "shadow-[0_0_0_0_#d4d4d4] dark:shadow-[0_0_0_0_#404040]";

function toEventKey(name: string) {
  return KEY_ALIASES[name.toLowerCase()] ?? name;
}

function toGlyph(name: string) {
  const glyph = KEY_GLYPHS[toEventKey(name)];
  return glyph ?? (name.length === 1 ? name.toUpperCase() : name);
}

// ─── KbdKey ──────────────────────────────────────────────────────────────────

export function KbdKey({
  children,
  keyName,
  listen = false,
  onPress,
  size = "sm",
  className,
}: KbdKeyProps) {
  const shouldReduceMotion = useReducedMotion();
  const [pressed, setPressed] = useState(false);
  const pressedRef = useRef(false);
  const onPressRef = useRef(onPress);
  onPressRef.current = onPress;
  const pressedCodeRef = useRef<string | null>(null);

  const resolvedKeyName =
    keyName ?? (typeof children === "string" ? children.toLowerCase() : undefined);

  const press = useCallback(() => {
    if (pressedRef.current) return;
    pressedRef.current = true;
    setPressed(true);
    onPressRef.current?.();
  }, []);

  const release = useCallback(() => {
    if (!pressedRef.current) return;
    pressedRef.current = false;
    pressedCodeRef.current = null;
    setPressed(false);
  }, []);

  useEffect(() => {
    if (!listen || !resolvedKeyName) return;

    const targetKey = toEventKey(resolvedKeyName).toLowerCase();
    const targetIsModifier = MODIFIERS.has(targetKey);

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.repeat) return;
      if (event.key.toLowerCase() !== targetKey) return;
      pressedCodeRef.current = event.code;
      press();
    };

    const handleKeyUp = (event: KeyboardEvent) => {
      const releasedMatchingCode =
        pressedCodeRef.current !== null && event.code === pressedCodeRef.current;
      const metaSwallowedKeyUp =
        event.key.toLowerCase() === "meta" && !targetIsModifier;
      if (
        event.key.toLowerCase() === targetKey ||
        releasedMatchingCode ||
        metaSwallowedKeyUp
      ) {
        release();
      }
    };

    const handleBlur = () => release();

    window.addEventListener("keydown", handleKeyDown);
    window.addEventListener("keyup", handleKeyUp);
    window.addEventListener("blur", handleBlur);
    return () => {
      window.removeEventListener("keydown", handleKeyDown);
      window.removeEventListener("keyup", handleKeyUp);
      window.removeEventListener("blur", handleBlur);
    };
  }, [listen, resolvedKeyName, press, release]);

  const cap = (
    <motion.kbd
      initial={false}
      animate={{ y: pressed ? PRESS_DEPTH : 0 }}
      transition={
        shouldReduceMotion
          ? { duration: 0 }
          : pressed
            ? PRESS_TRANSITION
            : RELEASE_SPRING
      }
      onPointerDown={(event) => {
        if (event.button !== 0) return;
        press();
      }}
      onPointerUp={release}
      onPointerLeave={release}
      onPointerCancel={release}
      className={cn(
        "inline-flex select-none items-center justify-center rounded-md border font-mono font-medium leading-none",
        "border-neutral-200 bg-neutral-50 text-neutral-600 dark:border-neutral-700/80 dark:bg-neutral-800 dark:text-neutral-300",
        pressed ? cn("bg-neutral-100 dark:bg-neutral-700", EDGE_SHADOW_PRESSED) : EDGE_SHADOW,
        !shouldReduceMotion && "transition-[background-color,box-shadow] duration-100 ease-out",
        SIZES[size],
        className,
      )}
    >
      {children}
    </motion.kbd>
  );

  if (!onPress) return cap;

  const label = typeof children === "string" ? children : (resolvedKeyName ?? "key");

  return (
    <button
      type="button"
      aria-label={`Press ${label}`}
      onKeyDown={(event) => {
        if (event.repeat) return;
        if (event.key === "Enter" || event.key === " ") press();
      }}
      onKeyUp={(event) => {
        if (event.key === "Enter" || event.key === " ") release();
      }}
      className="inline-flex rounded-md focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
    >
      {cap}
    </button>
  );
}

// ─── KbdCombo ────────────────────────────────────────────────────────────────

export function KbdCombo({
  keys,
  listen = false,
  onTrigger,
  size = "sm",
  className,
}: KbdComboProps) {
  const shouldReduceMotion = useReducedMotion();
  const controls = useAnimationControls();
  const onTriggerRef = useRef(onTrigger);
  onTriggerRef.current = onTrigger;

  const keyNames = keys
    .split("+")
    .map((name) => name.trim())
    .filter(Boolean);

  useEffect(() => {
    if (!listen) return;

    const targets = keys
      .split("+")
      .map((name) => name.trim())
      .filter(Boolean)
      .map((name) => toEventKey(name).toLowerCase());
    if (targets.length === 0) return;

    const down = new Set<string>();
    const codeToKey = new Map<string, string>();
    let fired = false;

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.repeat) return;
      const key = event.key.toLowerCase();
      if (!targets.includes(key)) return;
      down.add(key);
      codeToKey.set(event.code, key);
      if (fired || !targets.every((target) => down.has(target))) return;
      fired = true;
      onTriggerRef.current?.();
      if (!shouldReduceMotion) {
        controls.start({ scale: PULSE_SCALE, transition: PULSE_TRANSITION });
      }
    };

    const handleKeyUp = (event: KeyboardEvent) => {
      const key = event.key.toLowerCase();
      if (key === "meta") {
        down.forEach((held) => {
          if (!MODIFIERS.has(held)) down.delete(held);
        });
        codeToKey.clear();
      }
      const mapped = codeToKey.get(event.code);
      if (mapped) {
        down.delete(mapped);
        codeToKey.delete(event.code);
      }
      down.delete(key);
      if (!targets.every((target) => down.has(target))) fired = false;
    };

    const handleBlur = () => {
      down.clear();
      codeToKey.clear();
      fired = false;
    };

    window.addEventListener("keydown", handleKeyDown);
    window.addEventListener("keyup", handleKeyUp);
    window.addEventListener("blur", handleBlur);
    return () => {
      window.removeEventListener("keydown", handleKeyDown);
      window.removeEventListener("keyup", handleKeyUp);
      window.removeEventListener("blur", handleBlur);
    };
  }, [keys, listen, shouldReduceMotion, controls]);

  return (
    <motion.span
      animate={controls}
      className={cn("inline-flex items-center gap-1", className)}
    >
      {keyNames.map((name, index) => (
        <React.Fragment key={`${name}-${index}`}>
          {index > 0 && (
            <span
              aria-hidden="true"
              className="select-none text-[10px] text-muted-foreground/60"
            >
              +
            </span>
          )}
          <KbdKey keyName={name} listen={listen} size={size}>
            {toGlyph(name)}
          </KbdKey>
        </React.Fragment>
      ))}
    </motion.span>
  );
}
