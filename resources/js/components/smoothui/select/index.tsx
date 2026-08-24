import { DURATION_INSTANT, SPRING_DEFAULT, SPRING_SNAPPY } from "@/components/smoothui/lib/animation";
import { cn } from "@/lib/utils";
import { Check, ChevronDown } from "lucide-react";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import * as React from "react";
import { createPortal } from "react-dom";

const DROPDOWN_OFFSET = 4;
const VIEWPORT_PADDING = 8;
const MAX_DROPDOWN_HEIGHT = 240;
const STAGGER_DELAY = 0.02;

export interface SmoothSelectOption {
    className?: string;
    disabled?: boolean;
    label: React.ReactNode;
    textValue: string;
    value: string;
}

export type SmoothSelectEntry =
    | { className?: string; id: string; type: "label"; label: React.ReactNode }
    | { id: string; type: "option"; option: SmoothSelectOption }
    | { id: string; type: "separator" };

export interface SmoothSelectProps {
    "aria-label"?: string;
    "aria-labelledby"?: string;
    "aria-invalid"?: React.AriaAttributes["aria-invalid"];
    align?: "center" | "end" | "start";
    alignOffset?: number;
    className?: string;
    contentClassName?: string;
    defaultOpen?: boolean;
    defaultValue?: string;
    disabled?: boolean;
    entries: SmoothSelectEntry[];
    id?: string;
    name?: string;
    onOpenChange?: (open: boolean) => void;
    onValueChange?: (value: string) => void;
    open?: boolean;
    placeholder?: React.ReactNode;
    required?: boolean;
    selectedContent?: (option: SmoothSelectOption | undefined, value: string) => React.ReactNode;
    side?: "bottom" | "top";
    sideOffset?: number;
    size?: "sm" | "default";
    title?: string;
    value?: string;
}

interface DropdownPosition {
    left: number;
    maxHeight: number;
    placement: "bottom" | "top";
    right: number | null;
    top: number;
    width: number;
}

function getNextEnabledIndex(options: SmoothSelectOption[], currentIndex: number, direction: 1 | -1): number {
    if (options.length === 0) return -1;

    for (let step = 1; step <= options.length; step += 1) {
        const candidate = (currentIndex + direction * step + options.length) % options.length;
        if (!options[candidate]?.disabled) return candidate;
    }

    return -1;
}

export default function SmoothSelect({
    value: controlledValue,
    defaultValue,
    onValueChange,
    placeholder = "Select an option",
    disabled = false,
    required = false,
    name,
    entries,
    className,
    contentClassName,
    size = "default",
    open: controlledOpen,
    defaultOpen = false,
    onOpenChange,
    side,
    sideOffset = DROPDOWN_OFFSET,
    align = "start",
    alignOffset = 0,
    selectedContent,
    id,
    title,
    "aria-label": ariaLabel,
    "aria-labelledby": ariaLabelledBy,
    "aria-invalid": ariaInvalid,
}: SmoothSelectProps) {
    const shouldReduceMotion = useReducedMotion();
    const [internalOpen, setInternalOpen] = React.useState(defaultOpen);
    const [internalValue, setInternalValue] = React.useState(defaultValue ?? "");
    const [focusedIndex, setFocusedIndex] = React.useState(-1);
    const [position, setPosition] = React.useState<DropdownPosition>({
        left: 0,
        maxHeight: MAX_DROPDOWN_HEIGHT,
        placement: "bottom",
        right: null,
        top: 0,
        width: 0,
    });
    const triggerRef = React.useRef<HTMLButtonElement>(null);
    const portalRef = React.useRef<HTMLDivElement>(null);
    const typeaheadRef = React.useRef("");
    const typeaheadTimerRef = React.useRef<number | null>(null);
    const generatedId = React.useId();

    const isOpen = controlledOpen ?? internalOpen;
    const selectedValue = controlledValue === undefined ? internalValue : controlledValue;
    const options = React.useMemo(() => entries.flatMap((entry) => (entry.type === "option" ? [entry.option] : [])), [entries]);
    const selectedOption = options.find((option) => option.value === selectedValue);
    const listboxId = `${id ?? generatedId}-listbox`;

    const setOpen = React.useCallback(
        (nextOpen: boolean) => {
            if (controlledOpen === undefined) setInternalOpen(nextOpen);
            onOpenChange?.(nextOpen);
            if (!nextOpen) setFocusedIndex(-1);
        },
        [controlledOpen, onOpenChange],
    );

    const updatePosition = React.useCallback(() => {
        const trigger = triggerRef.current;
        if (!trigger) return;

        const rect = trigger.getBoundingClientRect();
        const availableBelow = window.innerHeight - rect.bottom - sideOffset - VIEWPORT_PADDING;
        const availableAbove = rect.top - sideOffset - VIEWPORT_PADDING;
        const placement = side ?? (availableBelow < Math.min(MAX_DROPDOWN_HEIGHT, 160) && availableAbove > availableBelow ? "top" : "bottom");
        const availableHeight = placement === "top" ? availableAbove : availableBelow;
        const maxHeight = Math.max(80, Math.min(MAX_DROPDOWN_HEIGHT, availableHeight));

        const alignedLeft = rect.left + alignOffset;
        const alignedRight = Math.max(VIEWPORT_PADDING, window.innerWidth - rect.right + alignOffset);

        setPosition({
            left: Math.max(VIEWPORT_PADDING, Math.min(alignedLeft, window.innerWidth - rect.width - VIEWPORT_PADDING)),
            maxHeight,
            placement,
            right: align === "end" ? alignedRight : null,
            top: placement === "top" ? window.innerHeight - rect.top + sideOffset : rect.bottom + sideOffset,
            width: Math.min(rect.width, window.innerWidth - VIEWPORT_PADDING * 2),
        });
    }, [align, alignOffset, side, sideOffset]);

    const handleSelect = React.useCallback(
        (option: SmoothSelectOption) => {
            if (option.disabled) return;
            if (controlledValue === undefined) setInternalValue(option.value);
            onValueChange?.(option.value);
            setOpen(false);
            window.requestAnimationFrame(() => triggerRef.current?.focus());
        },
        [controlledValue, onValueChange, setOpen],
    );

    const handleToggle = React.useCallback(() => {
        if (disabled) return;
        if (!isOpen) updatePosition();
        setOpen(!isOpen);
    }, [disabled, isOpen, setOpen, updatePosition]);

    React.useEffect(() => {
        if (!isOpen) return;

        updatePosition();
        window.addEventListener("resize", updatePosition);
        window.addEventListener("scroll", updatePosition, true);

        return () => {
            window.removeEventListener("resize", updatePosition);
            window.removeEventListener("scroll", updatePosition, true);
        };
    }, [isOpen, updatePosition]);

    React.useEffect(() => {
        if (!isOpen) return;

        const handlePointerDown = (event: PointerEvent) => {
            const target = event.target as Node;
            if (!triggerRef.current?.contains(target) && !portalRef.current?.contains(target)) setOpen(false);
        };

        document.addEventListener("pointerdown", handlePointerDown, true);
        return () => document.removeEventListener("pointerdown", handlePointerDown, true);
    }, [isOpen, setOpen]);

    React.useEffect(() => {
        return () => {
            if (typeaheadTimerRef.current !== null) window.clearTimeout(typeaheadTimerRef.current);
        };
    }, []);

    const focusOption = React.useCallback(
        (direction: 1 | -1) => {
            setFocusedIndex((current) => getNextEnabledIndex(options, current, direction));
        },
        [options],
    );

    const handleKeyDown = (event: React.KeyboardEvent<HTMLButtonElement>) => {
        if (disabled) return;

        if (!isOpen) {
            if (["Enter", " ", "ArrowDown", "ArrowUp"].includes(event.key)) {
                event.preventDefault();
                updatePosition();
                setOpen(true);
                const selectedIndex = options.findIndex((option) => option.value === selectedValue && !option.disabled);
                setFocusedIndex(selectedIndex >= 0 ? selectedIndex : getNextEnabledIndex(options, -1, 1));
            }
            return;
        }

        if (event.key === "Escape" || event.key === "Tab") {
            if (event.key === "Escape") event.preventDefault();
            setOpen(false);
            return;
        }

        if (event.key === "ArrowDown" || event.key === "ArrowUp") {
            event.preventDefault();
            focusOption(event.key === "ArrowDown" ? 1 : -1);
            return;
        }

        if (event.key === "Home" || event.key === "End") {
            event.preventDefault();
            setFocusedIndex(getNextEnabledIndex(options, event.key === "Home" ? -1 : 0, event.key === "Home" ? 1 : -1));
            return;
        }

        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            const option = options[focusedIndex];
            if (option) handleSelect(option);
            return;
        }

        if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
            typeaheadRef.current += event.key.toLocaleLowerCase();
            if (typeaheadTimerRef.current !== null) window.clearTimeout(typeaheadTimerRef.current);
            typeaheadTimerRef.current = window.setTimeout(() => {
                typeaheadRef.current = "";
            }, 500);
            const match = options.findIndex((option) => !option.disabled && option.textValue.toLocaleLowerCase().startsWith(typeaheadRef.current));
            if (match >= 0) setFocusedIndex(match);
        }
    };

    let optionIndex = 0;
    const dropdown = (
        <AnimatePresence>
            {isOpen ? (
                <div ref={portalRef} data-smoothui-select-portal className="pointer-events-auto fixed inset-0 z-[80] h-0 w-0">
                    <motion.div
                        data-slot="select-content"
                        data-smoothui-select-content
                        data-side={position.placement}
                        role="listbox"
                        id={listboxId}
                        aria-labelledby={ariaLabelledBy}
                        initial={shouldReduceMotion ? { opacity: 1 } : { opacity: 0, scale: 0.96, y: position.placement === "top" ? 4 : -4 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={shouldReduceMotion ? { opacity: 0 } : { opacity: 0, scale: 0.96, y: position.placement === "top" ? 4 : -4 }}
                        transition={shouldReduceMotion ? DURATION_INSTANT : SPRING_DEFAULT}
                        className={cn(
                            "bg-popover text-popover-foreground pointer-events-auto fixed z-[80] overflow-hidden rounded-md border shadow-md",
                            contentClassName,
                        )}
                        style={{
                            ["--anchor-width" as string]: `${position.width}px`,
                            left: position.right === null ? position.left : undefined,
                            minWidth: position.width,
                            bottom: position.placement === "top" ? position.top : undefined,
                            maxWidth: `calc(100vw - ${VIEWPORT_PADDING * 2}px)`,
                            right: position.right ?? undefined,
                            top: position.placement === "bottom" ? position.top : undefined,
                            transformOrigin: position.placement === "top" ? "bottom" : "top",
                        }}
                        onPointerDown={(event) => event.stopPropagation()}
                    >
                        <div className="overflow-y-auto p-1" style={{ maxHeight: position.maxHeight }}>
                            {entries.map((entry) => {
                                if (entry.type === "separator") {
                                    return <div key={entry.id} role="separator" className="bg-border pointer-events-none -mx-1 my-1 h-px" />;
                                }

                                if (entry.type === "label") {
                                    return (
                                        <div key={entry.id} className={cn("text-muted-foreground px-2 py-1.5 text-xs font-medium", entry.className)}>
                                            {entry.label}
                                        </div>
                                    );
                                }

                                const currentIndex = optionIndex;
                                optionIndex += 1;
                                const option = entry.option;
                                const isSelected = option.value === selectedValue;
                                const isFocused = currentIndex === focusedIndex;

                                return (
                                    <motion.button
                                        key={entry.id}
                                        id={`${listboxId}-option-${currentIndex}`}
                                        type="button"
                                        role="option"
                                        aria-selected={isSelected}
                                        disabled={option.disabled}
                                        initial={shouldReduceMotion ? { opacity: 1 } : { opacity: 0, x: -8 }}
                                        animate={{ opacity: 1, x: 0 }}
                                        exit={shouldReduceMotion ? { opacity: 0 } : { opacity: 0, x: -8 }}
                                        transition={shouldReduceMotion ? DURATION_INSTANT : { ...SPRING_SNAPPY, delay: currentIndex * STAGGER_DELAY }}
                                        className={cn(
                                            "relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-left text-sm outline-hidden transition-colors",
                                            "hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground",
                                            isFocused && "bg-accent text-accent-foreground",
                                            isSelected && "font-medium",
                                            option.disabled && "pointer-events-none opacity-50",
                                            option.className,
                                        )}
                                        onClick={() => handleSelect(option)}
                                        onMouseEnter={() => !option.disabled && setFocusedIndex(currentIndex)}
                                    >
                                        <span className="flex min-w-0 flex-1 items-center gap-2">{option.label}</span>
                                        <span className="absolute right-2 flex size-4 items-center justify-center">
                                            <AnimatePresence>
                                                {isSelected ? (
                                                    <motion.span
                                                        initial={shouldReduceMotion ? undefined : { opacity: 0, scale: 0 }}
                                                        animate={{ opacity: 1, scale: 1 }}
                                                        exit={{ opacity: 0, scale: 0 }}
                                                        transition={shouldReduceMotion ? DURATION_INSTANT : SPRING_SNAPPY}
                                                    >
                                                        <Check className="size-4" />
                                                    </motion.span>
                                                ) : null}
                                            </AnimatePresence>
                                        </span>
                                    </motion.button>
                                );
                            })}
                        </div>
                    </motion.div>
                </div>
            ) : null}
        </AnimatePresence>
    );

    const renderedValue = selectedContent ? selectedContent(selectedOption, selectedValue ?? "") : (selectedOption?.label ?? placeholder);

    return (
        <>
            {name ? <input aria-hidden="true" name={name} required={required} tabIndex={-1} type="hidden" value={selectedValue ?? ""} /> : null}
            <button
                ref={triggerRef}
                id={id}
                title={title}
                type="button"
                role="combobox"
                aria-controls={listboxId}
                aria-expanded={isOpen}
                aria-haspopup="listbox"
                aria-label={ariaLabel}
                aria-labelledby={ariaLabelledBy}
                aria-invalid={ariaInvalid}
                aria-required={required || undefined}
                aria-activedescendant={isOpen && focusedIndex >= 0 ? `${listboxId}-option-${focusedIndex}` : undefined}
                disabled={disabled}
                data-placeholder={!selectedOption || undefined}
                data-size={size}
                data-slot="select-trigger"
                className={cn(
                    "border-input bg-background flex w-full items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none select-none",
                    "focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]",
                    "aria-invalid:border-destructive aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50",
                    size === "default" ? "h-9" : "h-8",
                    className,
                )}
                onClick={handleToggle}
                onKeyDown={handleKeyDown}
            >
                <span
                    className={cn("flex min-w-0 flex-1 items-center gap-2 truncate text-left", !selectedOption && "text-muted-foreground")}
                    title={selectedOption?.textValue}
                >
                    {renderedValue}
                </span>
                <motion.span
                    className="text-muted-foreground shrink-0"
                    animate={{ rotate: isOpen ? 180 : 0 }}
                    transition={shouldReduceMotion ? DURATION_INSTANT : SPRING_SNAPPY}
                >
                    <ChevronDown className="size-4" />
                </motion.span>
            </button>
            {typeof document === "undefined" ? null : createPortal(dropdown, document.body)}
        </>
    );
}
