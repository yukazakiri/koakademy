<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

final class ImportTheme extends Command
{
    protected $signature = 'theme:import
                            {url? : The URL to the theme JSON file (e.g. https://tweakcn.com/r/themes/vintage-paper.json)}
                            {--name= : Override the theme name}
                            {--description= : Custom description for the theme}';

    protected $description = 'Import a shadcn theme from a tweakcn.com JSON URL';

    private string $themesDir;

    private string $themesConfigPath;

    private string $appCssPath;

    public function handle(): int
    {
        $this->themesDir = resource_path('css/themes');
        $this->themesConfigPath = resource_path('js/conf/themes.ts');
        $this->appCssPath = resource_path('css/app.css');

        $url = $this->argument('url') ?: text(
            label: 'What is the theme JSON URL?',
            placeholder: 'https://tweakcn.com/r/themes/vintage-paper.json',
            required: true,
            validate: fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_URL) ? null : 'The URL must be valid.'
        );

        $themeData = spin(
            fn (): array => Http::timeout(30)->get($url)->json() ?? [],
            "Fetching theme from $url..."
        );

        if ($themeData === []) {
            error('Failed to download theme definition or invalid JSON.');

            return self::FAILURE;
        }

        $rawThemeName = $this->option('name') ?? ($themeData['name'] ?? $this->extractThemeNameFromUrl($url));
        $themeId = Str::slug($rawThemeName);
        // Convert theme name to Title Case for display
        $themeName = Str::title(str_replace(['-', '_'], ' ', $rawThemeName));

        if ($this->themeExists($themeId) && ! confirm("Theme '{$themeId}' already exists. Overwrite?")) {
            info('Import cancelled.');

            return self::SUCCESS;
        }

        $messages = spin(function () use ($themeData, $themeId, $themeName): array {
            $messages = [];

            // Generate CSS file
            $cssContent = $this->generateCssFromThemeData($themeData, $themeId);
            $cssFilePath = "{$this->themesDir}/{$themeId}.css";

            File::ensureDirectoryExists($this->themesDir);
            File::put($cssFilePath, $cssContent);
            $messages[] = "Created CSS file: {$cssFilePath}";

            // Update app.css import
            $messages[] = $this->updateAppCssImport($themeId);

            // Update themes.ts config
            $description = $this->option('description') ?? 'Imported from tweakcn.';
            $messages[] = $this->updateThemesConfig($themeData, $themeId, $themeName, $description);

            return $messages;
        }, "Importing theme: {$themeName}...");

        foreach (array_filter($messages) as $message) {
            info($message);
        }

        $this->newLine();
        info('Theme imported successfully!');
        $this->newLine();
        $this->line('Run <comment>npm run build</comment> or <comment>npm run dev</comment> to apply the theme.');

        return self::SUCCESS;
    }

    private function extractThemeNameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = basename($path, '.json');

        return Str::title(str_replace(['-', '_'], ' ', $filename));
    }

    private function themeExists(string $themeId): bool
    {
        $cssFile = "{$this->themesDir}/{$themeId}.css";

        return File::exists($cssFile);
    }

    /**
     * Generate CSS content from theme JSON data.
     *
     * @param  array<string, mixed>  $themeData
     */
    private function generateCssFromThemeData(array $themeData, string $themeId): string
    {
        $cssVars = $themeData['cssVars'] ?? [];
        $css = $themeData['css'] ?? [];

        $fontImports = $this->extractFontImports($cssVars);
        $lightVars = $this->buildCssVariables($cssVars['light'] ?? [], $cssVars['theme'] ?? []);
        $darkVars = $this->buildCssVariables($cssVars['dark'] ?? [], []);
        $additionalCss = $this->buildAdditionalCss($css, $themeId);

        $output = '';

        // Add font imports
        if ($fontImports !== '' && $fontImports !== '0') {
            $output .= $fontImports."\n";
        }

        // Light mode (root selector)
        $output .= ":root.theme-{$themeId},\n.theme-{$themeId} {\n";
        $output .= $lightVars;
        $output .= "}\n\n";

        // Dark mode
        $output .= ":root.dark.theme-{$themeId},\n.dark.theme-{$themeId} {\n";
        $output .= $darkVars;
        $output .= "}\n";

        // Additional CSS
        if ($additionalCss !== '' && $additionalCss !== '0') {
            $output .= "\n".$additionalCss;
        }

        return $output;
    }

    /**
     * Extract font families and generate @import statements.
     *
     * @param  array<string, mixed>  $cssVars
     */
    private function extractFontImports(array $cssVars): string
    {
        $fonts = [];

        // Check theme, light, and dark sections for font families
        foreach (['theme', 'light', 'dark'] as $section) {
            if (! isset($cssVars[$section])) {
                continue;
            }

            foreach (['font-sans', 'font-mono', 'font-serif'] as $fontVar) {
                if (isset($cssVars[$section][$fontVar])) {
                    $fontValue = $cssVars[$section][$fontVar];
                    // Extract the primary font name (before any comma)
                    $primaryFont = mb_trim(explode(',', (string) $fontValue)[0]);
                    // Remove quotes if present
                    $primaryFont = mb_trim($primaryFont, "\"'");

                    // Skip generic font families
                    if (in_array(mb_strtolower($primaryFont), ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-sans-serif', 'ui-serif', 'ui-monospace'])) {
                        continue;
                    }

                    $fonts[$primaryFont] = true;
                }
            }
        }

        $imports = [];
        foreach (array_keys($fonts) as $font) {
            $encodedFont = urlencode($font);
            $imports[] = "@import url('https://fonts.googleapis.com/css2?family={$encodedFont}&display=swap');";
        }

        return implode("\n", $imports);
    }

    /**
     * Build CSS variable declarations.
     *
     * @param  array<string, string>  $vars
     * @param  array<string, string>  $themeVars
     */
    private function buildCssVariables(array $vars, array $themeVars = []): string
    {
        // Merge theme vars first, then specific vars (light/dark) override
        $allVars = array_merge($themeVars, $vars);

        // Variables to skip (these are handled separately or not needed)
        $skipVars = ['radius'];

        $output = '';
        foreach ($allVars as $key => $value) {
            if (in_array($key, $skipVars) && isset($themeVars[$key])) {
                continue;
            }

            $output .= "  --{$key}: {$value};\n";
        }

        return $output;
    }

    /**
     * Build additional CSS from the css property.
     *
     * @param  array<string, mixed>  $css
     */
    private function buildAdditionalCss(array $css, string $themeId): string
    {
        $output = '';

        foreach ($css as $layer => $rules) {
            if ($layer === '@layer base') {
                $output .= "@layer base {\n";
                foreach ($rules as $selector => $properties) {
                    // Scope to theme
                    $scopedSelector = ".theme-{$themeId} {$selector}";
                    $output .= "  {$scopedSelector} {\n";
                    foreach ($properties as $prop => $value) {
                        $output .= "    {$prop}: {$value};\n";
                    }
                    $output .= "  }\n";
                }
                $output .= "}\n";
            }
        }

        return $output;
    }

    /**
     * Update app.css to include the new theme import.
     */
    private function updateAppCssImport(string $themeId): string
    {
        $appCss = File::get($this->appCssPath);
        $importStatement = "@import \"./themes/{$themeId}.css\";";

        if (str_contains($appCss, $importStatement)) {
            return 'Import already exists in app.css';
        }

        // Find the last theme import and add after it
        $pattern = '/@import\s+["\']\.\/themes\/[^"\']+["\'];/';
        if (preg_match_all($pattern, $appCss, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch = end($matches[0]);
            $insertPosition = $lastMatch[1] + mb_strlen($lastMatch[0]);
            $appCss = mb_substr($appCss, 0, $insertPosition)."\n".$importStatement.mb_substr($appCss, $insertPosition);
        } else {
            // No existing theme imports, add after tw-animate-css import
            $animatePattern = '/@import\s+["\']tw-animate-css["\'];/';
            if (preg_match($animatePattern, $appCss, $match, PREG_OFFSET_CAPTURE)) {
                $insertPosition = $match[0][1] + mb_strlen($match[0][0]);
                $appCss = mb_substr($appCss, 0, $insertPosition)."\n\n".$importStatement.mb_substr($appCss, $insertPosition);
            }
        }

        File::put($this->appCssPath, $appCss);

        return 'Updated app.css with theme import';
    }

    /**
     * Update themes.ts configuration file.
     *
     * @param  array<string, mixed>  $themeData
     */
    private function updateThemesConfig(array $themeData, string $themeId, string $themeName, string $description): ?string
    {
        $themesConfig = File::get($this->themesConfigPath);
        $cssVars = $themeData['cssVars'] ?? [];

        // Extract font
        $font = 'System Sans';
        foreach (['theme', 'light'] as $section) {
            if (isset($cssVars[$section]['font-sans'])) {
                $fontValue = $cssVars[$section]['font-sans'];
                $font = mb_trim(explode(',', (string) $fontValue)[0], "\"'");
                break;
            }
        }

        // Extract colors (primary, secondary, accent)
        $lightVars = $cssVars['light'] ?? [];
        $primary = $lightVars['primary'] ?? 'oklch(0.5 0.1 200)';
        $secondary = $lightVars['secondary'] ?? 'oklch(0.8 0.05 200)';
        $accent = $lightVars['accent'] ?? 'oklch(0.7 0.1 200)';

        // Check if theme ID already exists in ColorTheme type
        $colorThemePattern = '/export type ColorTheme = ([^\n]+)/';
        if (preg_match($colorThemePattern, $themesConfig, $matches)) {
            $existingTypes = $matches[1];
            if (! str_contains($existingTypes, "\"{$themeId}\"")) {
                // Add the new theme type
                $newTypes = mb_rtrim($existingTypes)." | \"{$themeId}\"";
                $themesConfig = preg_replace($colorThemePattern, "export type ColorTheme = {$newTypes}", $themesConfig);
            }
        }

        $resultMessage = null;

        // Check if theme config already exists in array
        $themeConfigPattern = '/id:\s*["\']'.preg_quote($themeId, '/').'["\']/';
        if (preg_match($themeConfigPattern, (string) $themesConfig)) {
            // Update existing theme config
            $resultMessage = 'Theme config already exists, update skipped.';
        } else {
            // Add new theme config before the closing bracket
            $newThemeConfig = <<<EOT
    {
        id: "{$themeId}",
        name: "{$themeName}",
        description: "{$description}",
        font: "{$font}",
        colors: {
            primary: "{$primary}",
            secondary: "{$secondary}",
            accent: "{$accent}",
        }
    }
EOT;

            // Find the closing bracket of the themes array and insert before it
            // Look for the last occurrence of } followed by ]
            $closingBracketPos = mb_strrpos((string) $themesConfig, ']');
            if ($closingBracketPos !== false) {
                // Find the position to insert (before the closing bracket)
                // We need to insert after the last theme entry's closing brace
                $beforeClosing = mb_substr((string) $themesConfig, 0, $closingBracketPos);
                $afterClosing = mb_substr((string) $themesConfig, $closingBracketPos);

                // Trim whitespace from the end of beforeClosing and add proper formatting
                $beforeClosing = mb_rtrim($beforeClosing);

                $themesConfig = $beforeClosing.",\n".$newThemeConfig."\n".$afterClosing;
                $resultMessage = 'Updated themes.ts configuration';
            }
        }

        File::put($this->themesConfigPath, $themesConfig);

        return $resultMessage;
    }
}
