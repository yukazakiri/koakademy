import React from "react"
import { X } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Command, CommandGroup, CommandItem, CommandList } from "@/components/ui/command"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { cn } from "@/lib/utils"

interface TagInputProps {
  placeholder?: string
  tags: string[]
  setTags: (tags: string[]) => void
  suggestions?: { value: string; label: string }[]
  className?: string
}

export function TagInput({
  placeholder = "Add tags...",
  tags,
  setTags,
  suggestions = [],
  className,
}: TagInputProps) {
  const [inputValue, setInputValue] = React.useState("")
  const [open, setOpen] = React.useState(false)

  const normalizeTag = (value: string) => value.trim()

  const handleUnselect = (tag: string) => {
    setTags(tags.filter((t) => t !== tag))
  }

  const handleSelect = (tag: string) => {
    const normalizedTag = normalizeTag(tag)
    if (!normalizedTag) {
      return
    }
    if (!tags.includes(normalizedTag)) {
      setTags([...tags, normalizedTag])
    }
    setInputValue("")
    setOpen(false)
  }

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    const normalizedInput = normalizeTag(inputValue)

    if ((e.key === "Enter" || e.key === "," || e.key === "Tab") && normalizedInput) {
      e.preventDefault()
      e.stopPropagation()
      handleSelect(normalizedInput)
      return
    }
    if (e.key === "Backspace" && !inputValue && tags.length > 0) {
      handleUnselect(tags[tags.length - 1])
    }
  }

  const normalizedInput = normalizeTag(inputValue)
  const availableSuggestions = suggestions.filter(
    (suggestion) => !tags.includes(suggestion.value)
  )
  const filteredSuggestions = normalizedInput
    ? availableSuggestions.filter((suggestion) =>
        suggestion.label.toLowerCase().includes(normalizedInput.toLowerCase()) ||
        suggestion.value.toLowerCase().includes(normalizedInput.toLowerCase())
      )
    : availableSuggestions
  const hasExactSuggestion = normalizedInput
    ? availableSuggestions.some((suggestion) =>
        suggestion.value.toLowerCase() === normalizedInput.toLowerCase() ||
        suggestion.label.toLowerCase() === normalizedInput.toLowerCase()
      )
    : false

  return (
    <div className={cn("flex flex-wrap gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2", className)}>
      {tags.map((tag) => (
        <Badge key={tag} variant="secondary">
          {tag}
          <button
            type="button"
            className="ml-1 rounded-full outline-none ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2"
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                handleUnselect(tag)
              }
            }}
            onMouseDown={(e) => {
              e.preventDefault()
              e.stopPropagation()
            }}
            onClick={() => handleUnselect(tag)}
          >
            <X className="h-3 w-3 text-muted-foreground hover:text-foreground" />
          </button>
        </Badge>
      ))}
      
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <input
            className="flex-1 bg-transparent outline-none placeholder:text-muted-foreground min-w-[120px]"
            placeholder={tags.length === 0 ? placeholder : ""}
            value={inputValue}
            onChange={(e) => {
              setInputValue(e.target.value)
              setOpen(true)
            }}
            onKeyDown={handleKeyDown}
            onFocus={() => setOpen(true)}
          />
        </PopoverTrigger>
        <PopoverContent className="w-[200px] p-0" align="start">
          <Command>
            <CommandList>
              <CommandGroup heading="Suggestions">
                {filteredSuggestions.map((suggestion) => (
                   <CommandItem
                     key={suggestion.value}
                     onSelect={() => handleSelect(suggestion.value)}
                   >
                     {suggestion.label}
                   </CommandItem>
                ))}
                {normalizedInput && !hasExactSuggestion && (
                    <CommandItem onSelect={() => handleSelect(normalizedInput)}>
                        Create "{normalizedInput}"
                    </CommandItem>
                )}
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
    </div>
  )
}
