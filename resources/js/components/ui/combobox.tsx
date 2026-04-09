import * as React from "react"
import { IconCheck, IconChevronDown, IconSearch } from "@tabler/icons-react"
import { Command as CommandPrimitive } from "cmdk"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
} from "@/components/ui/command"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"

export interface ComboboxOption {
  label: string
  value: string
  description?: string
  searchText?: string
}

interface ComboboxProps {
  options: ComboboxOption[]
  value?: string
  onValueChange?: (value: string) => void
  placeholder?: string
  emptyText?: string
  searchPlaceholder?: string
  disabled?: boolean
  className?: string
  label?: string
  required?: boolean
}

export function Combobox({
  options,
  value,
  onValueChange,
  placeholder = "Select an option...",
  emptyText = "No option found.",
  searchPlaceholder = "Search...",
  disabled = false,
  className,
  label,
  required = false,
}: ComboboxProps) {
  const [open, setOpen] = React.useState(false)

  const selectedOption = options.find((option) => option.value === value)

  const handleSelect = (optionValue: string) => {
    onValueChange?.(optionValue)
    setOpen(false)
  }

  // Custom filter function for cmdk
  const filterFunction = React.useCallback((value: string, search: string) => {
    const option = options.find((opt) => opt.value === value)
    if (!option) return 0

    const searchLower = search.toLowerCase()
    const searchableText = option.searchText || option.label

    const isMatch =
      searchableText.toLowerCase().includes(searchLower) ||
      option.label.toLowerCase().includes(searchLower) ||
      option.value.toLowerCase().includes(searchLower)

    return isMatch ? 1 : 0
  }, [options])

  return (
    <div className={cn("space-y-2", className)}>
      {label && (
        <label className="text-sm font-medium flex items-center gap-2 text-foreground">
          <span>{label}</span>
          {required && <span className="text-destructive">*</span>}
        </label>
      )}
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            variant="outline"
            role="combobox"
            aria-expanded={open}
            className="w-full justify-between h-11 bg-background text-foreground"
            disabled={disabled}
          >
            <span className="truncate">
              {selectedOption ? selectedOption.label : placeholder}
            </span>
            <IconChevronDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-full p-0" align="start">
          <Command filter={filterFunction}>
            <CommandInput placeholder={searchPlaceholder} />
            <CommandEmpty>{emptyText}</CommandEmpty>
            <CommandGroup className="max-h-64 overflow-auto">
              {options.map((option) => (
                <CommandItem
                  key={option.value}
                  value={option.value}
                  onSelect={() => handleSelect(option.value)}
                  className="flex items-center gap-2"
                >
                  <IconCheck
                    className={cn(
                      "h-4 w-4",
                      value === option.value ? "opacity-100" : "opacity-0"
                    )}
                  />
                  <div className="flex-1">
                    <div className="font-medium">{option.label}</div>
                    {option.description && (
                      <div className="text-xs text-muted-foreground">
                        {option.description}
                      </div>
                    )}
                  </div>
                </CommandItem>
              ))}
            </CommandGroup>
          </Command>
        </PopoverContent>
      </Popover>
    </div>
  )
}
