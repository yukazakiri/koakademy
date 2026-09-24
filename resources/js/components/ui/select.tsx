import SmoothSelect, {
    type SmoothSelectEntry,
    type SmoothSelectOption,
} from "@/components/smoothui/select";
import { cn } from "@/lib/utils";
import * as React from "react";

type SelectRootProps = {
    children?: React.ReactNode;
    defaultOpen?: boolean;
    defaultValue?: number | string | null;
    disabled?: boolean;
    items?: unknown;
    name?: string;
    onOpenChange?: (open: boolean) => void;
    onValueChange?: (value: string) => void;
    open?: boolean;
    required?: boolean;
    value?: number | string | null;
};

type SelectTriggerProps = Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, "defaultValue" | "value"> & {
    size?: "sm" | "default";
};

type SelectValueProps = {
    children?: React.ReactNode | ((value: string) => React.ReactNode);
    className?: string;
    placeholder?: React.ReactNode;
};

type SelectContentProps = React.HTMLAttributes<HTMLDivElement> & {
    align?: "center" | "end" | "start";
    alignItemWithTrigger?: boolean;
    alignOffset?: number;
    side?: "bottom" | "top";
    sideOffset?: number;
};

type SelectItemProps = React.HTMLAttributes<HTMLDivElement> & {
    disabled?: boolean;
    textValue?: string;
    value: string | number;
};

type SelectGroupProps = React.HTMLAttributes<HTMLDivElement>;
type SelectLabelProps = React.HTMLAttributes<HTMLDivElement>;
type SelectSeparatorProps = React.HTMLAttributes<HTMLDivElement>;

function SelectTrigger(_props?: SelectTriggerProps) {
    void _props;
    return null;
}

function SelectValue(_props?: SelectValueProps) {
    void _props;
    return null;
}

function SelectContent(_props?: SelectContentProps) {
    void _props;
    return null;
}

function SelectItem(_props?: SelectItemProps) {
    void _props;
    return null;
}

function SelectGroup(_props?: SelectGroupProps) {
    void _props;
    return null;
}

function SelectLabel(_props?: SelectLabelProps) {
    void _props;
    return null;
}

function SelectSeparator(_props?: SelectSeparatorProps) {
    void _props;
    return null;
}

function SelectScrollUpButton(_props?: React.HTMLAttributes<HTMLDivElement>) {
    void _props;
    return null;
}

function SelectScrollDownButton(_props?: React.HTMLAttributes<HTMLDivElement>) {
    void _props;
    return null;
}

function isElementType<P>(node: React.ReactNode, type: React.ElementType): node is React.ReactElement<P> {
    return React.isValidElement(node) && node.type === type;
}

function findElement<P>(children: React.ReactNode, type: React.ElementType): React.ReactElement<P> | null {
    let match: React.ReactElement<P> | null = null;

    React.Children.forEach(children, (child) => {
        if (match) return;
        if (isElementType<P>(child, type)) {
            match = child;
            return;
        }
        if (React.isValidElement<{ children?: React.ReactNode }>(child) && child.type === React.Fragment) {
            match = findElement<P>(child.props.children, type);
        }
    });

    return match;
}

function nodeToText(node: React.ReactNode): string {
    if (typeof node === "string" || typeof node === "number") return String(node);
    if (Array.isArray(node)) return node.map(nodeToText).join(" ").replace(/\s+/g, " ").trim();
    if (React.isValidElement<{ children?: React.ReactNode }>(node)) return nodeToText(node.props.children);
    return "";
}

function entryId(prefix: string, child: React.ReactElement, index: number): string {
    return `${prefix}-${child.key === null ? index : String(child.key)}`;
}

function parseEntries(children: React.ReactNode, prefix = "select"): SmoothSelectEntry[] {
    const entries: SmoothSelectEntry[] = [];

    React.Children.forEach(children, (child, index) => {
        if (!React.isValidElement(child)) return;

        if (child.type === React.Fragment) {
            entries.push(...parseEntries((child.props as { children?: React.ReactNode }).children, `${prefix}-fragment-${index}`));
            return;
        }

        if (isElementType<SelectItemProps>(child, SelectItem)) {
            const label = child.props.children;
            const option: SmoothSelectOption = {
                className: child.props.className,
                disabled: child.props.disabled,
                label,
                textValue: child.props.textValue ?? nodeToText(label),
                value: String(child.props.value),
            };
            entries.push({ id: entryId(`${prefix}-option`, child, index), option, type: "option" });
            return;
        }

        if (isElementType<SelectLabelProps>(child, SelectLabel)) {
            entries.push({
                className: child.props.className,
                id: entryId(`${prefix}-label`, child, index),
                label: child.props.children,
                type: "label",
            });
            return;
        }

        if (isElementType<SelectSeparatorProps>(child, SelectSeparator)) {
            entries.push({ id: entryId(`${prefix}-separator`, child, index), type: "separator" });
            return;
        }

        if (isElementType<SelectGroupProps>(child, SelectGroup)) {
            entries.push(...parseEntries(child.props.children, entryId(`${prefix}-group`, child, index)));
        }
    });

    return entries;
}

function renderTriggerNode(
    node: React.ReactNode,
    option: SmoothSelectOption | undefined,
    value: string,
): React.ReactNode {
    if (isElementType<SelectValueProps>(node, SelectValue)) {
        if (typeof node.props.children === "function") return node.props.children(value);
        if (node.props.children !== undefined) return node.props.children;

        return option?.label ?? node.props.placeholder ?? "Select an option";
    }

    if (React.isValidElement<{ children?: React.ReactNode }>(node) && node.type === React.Fragment) {
        return React.cloneElement(node, undefined, renderTriggerChildren(node.props.children, option, value));
    }

    return node;
}

function renderTriggerChildren(
    children: React.ReactNode,
    option: SmoothSelectOption | undefined,
    value: string,
): React.ReactNode {
    return React.Children.map(children, (child) => renderTriggerNode(child, option, value));
}

function Select({
    children,
    value,
    defaultValue,
    onValueChange,
    disabled,
    required,
    name,
    open,
    defaultOpen,
    onOpenChange,
}: SelectRootProps) {
    const trigger = findElement<SelectTriggerProps>(children, SelectTrigger);
    const content = findElement<SelectContentProps>(children, SelectContent);
    const valueElement = trigger ? findElement<SelectValueProps>(trigger.props.children, SelectValue) : null;
    const entries = React.useMemo(() => parseEntries(content?.props.children), [content?.props.children]);
    const placeholder = valueElement?.props.placeholder ?? "Select an option";

    return (
        <SmoothSelect
            value={value === null || value === undefined ? value ?? undefined : String(value)}
            defaultValue={defaultValue === null || defaultValue === undefined ? defaultValue ?? undefined : String(defaultValue)}
            onValueChange={onValueChange}
            disabled={disabled || trigger?.props.disabled}
            required={required}
            name={name}
            entries={entries}
            open={open}
            defaultOpen={defaultOpen}
            onOpenChange={onOpenChange}
            placeholder={placeholder}
            size={trigger?.props.size}
            side={content?.props.side}
            sideOffset={content?.props.sideOffset}
            align={content?.props.align}
            alignOffset={content?.props.alignOffset}
            className={cn("text-foreground", trigger?.props.className)}
            contentClassName={content?.props.className}
            id={trigger?.props.id}
            title={trigger?.props.title}
            aria-label={trigger?.props["aria-label"]}
            aria-labelledby={trigger?.props["aria-labelledby"]}
            aria-invalid={trigger?.props["aria-invalid"]}
            selectedContent={(option, selectedValue) =>
                trigger ? renderTriggerChildren(trigger.props.children, option, selectedValue) : option?.label ?? placeholder
            }
        />
    );
}

export {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectScrollDownButton,
    SelectScrollUpButton,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
};
