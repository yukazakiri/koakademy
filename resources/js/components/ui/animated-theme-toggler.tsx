import { Moon, Sun } from "lucide-react"

import { cn } from "@/lib/utils"
import { useTheme } from "@/hooks/use-theme"

interface AnimatedThemeTogglerProps
  extends React.ComponentPropsWithoutRef<"button"> {}

export const AnimatedThemeToggler = ({
  className,
  ...props
}: AnimatedThemeTogglerProps) => {
  const { actualTheme, setThemeWithViewTransition } = useTheme()

  const toggleTheme = (e: React.MouseEvent<HTMLButtonElement>) => {
    const newTheme = actualTheme === "dark" ? "light" : "dark"
    setThemeWithViewTransition(newTheme, e)
  }

  return (
    <button
      onClick={toggleTheme}
      className={cn(className)}
      {...props}
    >
      {actualTheme === "dark" ? <Sun /> : <Moon />}
      <span className="sr-only">Toggle theme</span>
    </button>
  )
}
