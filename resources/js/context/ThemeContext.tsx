import type { ThemeContextProps } from "@/types/Context";
import { createContext } from "react";

const ThemeContext = createContext<ThemeContextProps | undefined>(undefined);

export default ThemeContext;
