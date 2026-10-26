import * as React from "react"
import { Check, ChevronsUpDown, X } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import {
  Command,
  CommandEmpty,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { cn } from "@/lib/utils"

export type MultiSelectOption = {
  label: string
  value: string
  description?: string
  searchText?: string
}

interface MultiSelectProps {
  options: MultiSelectOption[]
  selected: string[]
  onChange: (selected: string[]) => void
  placeholder?: string
  searchPlaceholder?: string
  className?: string
  badgeClassName?: string
  disabled?: boolean
  emptyText?: string
  maxCount?: number
}

export function MultiSelect({
  options,
  selected,
  onChange,
  placeholder = "Select items...",
  searchPlaceholder = "Search items...",
  className,
  badgeClassName,
  disabled = false,
  emptyText = "No item found.",
  maxCount = 3,
}: MultiSelectProps) {
  const [open, setOpen] = React.useState(false)
  const [query, setQuery] = React.useState("")
  const popoverId = React.useId()

  const handleUnselect = (item: string) => {
    onChange(selected.filter((i) => i !== item))
  }

  const handleSelect = (item: string) => {
    if (selected.includes(item)) {
      handleUnselect(item)
    } else {
      onChange([...selected, item])
    }
  }

  const selectedOptions = options.filter((option) =>
    selected.includes(option.value)
  )

  const filteredOptions = React.useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase()

    if (!normalizedQuery) {
      return options
    }

    return options.filter((option) => {
      const searchable = [
        option.label,
        option.description,
        option.searchText,
        option.value,
      ]
        .filter(Boolean)
        .join(" ")
        .toLowerCase()

      return searchable.includes(normalizedQuery)
    })
  }, [options, query])

  const handleTriggerKeyDown = (event: React.KeyboardEvent<HTMLDivElement>): void => {
    if (disabled) {
      return
    }

    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault()
      setOpen((previous) => !previous)
    }
  }

  const handleTriggerClick = (): void => {
    if (disabled) {
      return
    }
    setOpen((previous) => !previous)
  }

  return (
    <Popover open={open} onOpenChange={(nextOpen) => {
      setOpen(nextOpen)
      if (!nextOpen) {
        setQuery("")
      }
    }}>
      <PopoverTrigger asChild>
        <div
          role="combobox"
          aria-label={placeholder}
          aria-expanded={open}
          aria-controls={popoverId}
          aria-haspopup="listbox"
          aria-disabled={disabled || undefined}
          tabIndex={disabled ? -1 : 0}
          onClick={handleTriggerClick}
          onKeyDown={handleTriggerKeyDown}
          className={cn(
            "border-input bg-background ring-offset-background placeholder:text-muted-foreground focus:ring-ring flex min-h-9 w-full items-center justify-between rounded-xl border px-3 py-1.5 text-xs shadow-2xs transition-[color,box-shadow] outline-none focus:ring-2 focus:ring-offset-1 cursor-pointer select-none",
            disabled && "pointer-events-none opacity-50",
            className
          )}
        >
          <div className="flex flex-1 flex-wrap items-center gap-1 overflow-hidden py-0.5">
            {selected.length === 0 && (
              <span className="text-muted-foreground font-normal text-xs">
                {placeholder}
              </span>
            )}
            {selectedOptions.slice(0, maxCount).map((option) => (
              <Badge
                variant="secondary"
                key={option.value}
                className={cn("h-5 px-1.5 text-[11px] font-normal cursor-pointer gap-1", badgeClassName)}
                role="button"
                tabIndex={0}
                aria-label={`Remove ${option.label}`}
                title={`Remove ${option.label}`}
                onClick={(event) => {
                  event.stopPropagation()
                  handleUnselect(option.value)
                }}
                onKeyDown={(event) => {
                  if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault()
                    event.stopPropagation()
                    handleUnselect(option.value)
                  }
                }}
              >
                <span className="truncate max-w-[120px]">{option.label}</span>
                <span
                  aria-hidden="true"
                  className="inline-flex items-center justify-center rounded-full text-muted-foreground hover:text-foreground"
                >
                  <X className="size-2.5" />
                </span>
              </Badge>
            ))}
            {selectedOptions.length > maxCount && (
              <Badge
                variant="outline"
                className="h-5 px-1.5 font-mono text-[10px] text-muted-foreground border-border/80"
              >
                +{selectedOptions.length - maxCount} more
              </Badge>
            )}
          </div>
          <ChevronsUpDown className="ml-1.5 size-3.5 shrink-0 opacity-50" />
        </div>
      </PopoverTrigger>
      <PopoverContent
        id={popoverId}
        className="w-[var(--radix-popover-trigger-width)] p-0"
        align="start"
      >
        <Command shouldFilter={false}>
          <CommandInput
            placeholder={searchPlaceholder}
            value={query}
            onValueChange={setQuery}
          />
          <CommandList
            className="max-h-[min(22rem,55vh)] overflow-y-auto overscroll-contain [scrollbar-color:var(--border)_transparent] [scrollbar-width:thin] [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-corner]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-border [&::-webkit-scrollbar-track]:bg-transparent"
            onWheel={(event) => {
              event.stopPropagation()
            }}
            onWheelCapture={(event) => {
              event.stopPropagation()
            }}
          >
            {filteredOptions.length === 0 ? (
              <CommandEmpty>{emptyText}</CommandEmpty>
            ) : (
              filteredOptions.map((option) => (
                <CommandItem
                  key={option.value}
                  value={option.value}
                  onSelect={() => handleSelect(option.value)}
                  className="items-start py-2.5"
                >
                  <Check
                    className={cn(
                      "mt-0.5 mr-2 h-4 w-4",
                      selected.includes(option.value)
                        ? "opacity-100"
                        : "opacity-0"
                    )}
                  />
                  <div className="flex-1">
                    <div>{option.label}</div>
                    {option.description ? (
                      <div className="text-xs text-muted-foreground">
                        {option.description}
                      </div>
                    ) : null}
                  </div>
                </CommandItem>
              ))
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}
