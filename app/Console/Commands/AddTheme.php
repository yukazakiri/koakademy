<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

final class AddTheme extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'theme:add {url : The URL of the theme JSON from tweakcn.com}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download and install a new theme from a JSON definition';

    private string $themesConfigPath;

    private string $appCssPath;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->themesConfigPath = resource_path('js/conf/themes.ts');
        $this->appCssPath = resource_path('css/app.css');

        $url = $this->argument('url') ?: text(
            label: 'What is the theme JSON URL?',
            placeholder: 'https://tweakcn.com/themes/catppuccin.json',
            required: true,
            validate: fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_URL) ? null : 'The URL must be valid.'
        );

        $data = spin(
            fn (): array => Http::get($url)->json() ?? [],
            "Fetching theme from $url..."
        );

        if ($data === []) {
            error('Failed to download theme definition or invalid JSON.');

            return self::FAILURE;
        }

        try {
            if (! isset($data['name']) || ! isset($data['cssVars'])) {
                error('Invalid theme JSON structure.');

                return self::FAILURE;
            }

            $themeName = $data['name'];
            $themeId = Str::kebab($themeName);
            $displayName = Str::title(str_replace('-', ' ', $themeName));

            $messages = spin(function () use ($themeId, $displayName, $data): array {
                $messages = [];

                // 1. Generate CSS Content
                $cssContent = $this->generateCss($themeId, $data['cssVars']);

                // 2. Save CSS File
                $cssPath = resource_path("css/themes/{$themeId}.css");
                File::put($cssPath, $cssContent);
                $messages[] = "Created CSS file: $cssPath";

                // 3. Update app.css
                $messages = array_merge($messages, $this->updateAppCss($themeId));

                // 4. Update themes.ts
                $messages = array_merge($messages, $this->updateThemesTs($themeId, $displayName, $data['cssVars']));

                return $messages;
            }, "Installing theme: $displayName...");

            foreach ($messages as $message) {
                info($message);
            }

            info("Theme '$displayName' installed successfully!");

        } catch (Exception $e) {
            error('Error: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function generateCss($themeId, array $vars): string
    {
        $light = $vars['light'] ?? [];
        $dark = $vars['dark'] ?? [];
        $common = $vars['theme'] ?? [];

        $css = '';

        // Attempt to generate Google Fonts imports
        $fonts = ['font-sans', 'font-serif', 'font-mono'];
        foreach ($fonts as $fontKey) {
            if (isset($common[$fontKey])) {
                $fontValue = $common[$fontKey];
                // Extract first font family name (before comma)
                $fontFamily = mb_trim(explode(',', $fontValue)[0]);
                // Remove quotes if present
                $fontFamily = mb_trim($fontFamily, "'\"");

                // Ignore generic/system fonts
                $ignored = ['ui-sans-serif', 'system-ui', 'sans-serif', 'serif', 'monospace', 'inherit'];
                if (! in_array(mb_strtolower($fontFamily), $ignored) && ! Str::startsWith($fontValue, 'var(')) {
                    $encodedFamily = urlencode($fontFamily);
                    $css .= "@import url('https://fonts.googleapis.com/css2?family={$encodedFamily}&display=swap');\n";
                }
            }
        }

        if ($css !== '' && $css !== '0') {
            $css .= "\n";
        }

        // Combine common vars into light/dark for simplicity in our output structure,
        // or just put them in the selector.
        // Our project structure puts everything in .theme-name

        $css .= ":root.theme-{$themeId},\n";
        $css .= ".theme-{$themeId} {\n";

        // Merge common into light as defaults (if needed) or just output them.
        // Usually 'theme' vars in shadcn json are radius, fonts, etc.
        foreach ($common as $key => $value) {
            $css .= "  --{$key}: {$value};\n";
        }

        foreach ($light as $key => $value) {
            $css .= "  --{$key}: {$value};\n";
        }

        // Handle shadows explicitly if present in common/light
        // Our existing themes define shadow variables directly.
        // If the imported theme uses different shadow variables, they might not work without adaptation.
        // However, the provided JSON example includes --shadow-*, so we should just dump them.

        $css .= "}\n\n";

        $css .= ":root.dark.theme-{$themeId},\n";
        $css .= ".dark.theme-{$themeId} {\n";
        foreach ($dark as $key => $value) {
            $css .= "  --{$key}: {$value};\n";
        }

        return $css."}\n";
    }

    private function updateAppCss($themeId): array
    {
        $messages = [];
        $content = File::get($this->appCssPath);

        $importLine = "@import \"./themes/{$themeId}.css\";";

        if (! Str::contains($content, $importLine)) {
            // Find the last theme import
            $pattern = '/@import\s+["\']\.\/themes\/[^"\']+["\'];/';
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $lastMatch = end($matches[0]);
                $insertPosition = $lastMatch[1] + mb_strlen($lastMatch[0]);
                $content = mb_substr($content, 0, $insertPosition)."\n".$importLine.mb_substr($content, $insertPosition);
            } else {
                // No existing theme imports, add after tw-animate-css import
                $animatePattern = '/@import\s+["\']tw-animate-css["\'];/';
                if (preg_match($animatePattern, $content, $match, PREG_OFFSET_CAPTURE)) {
                    $insertPosition = $match[0][1] + mb_strlen($match[0][0]);
                    $content = mb_substr($content, 0, $insertPosition)."\n\n".$importLine.mb_substr($content, $insertPosition);
                } else {
                    // Fallback: insert before @source
                    $content = str_replace('@source', "$importLine\n\n@source", $content);
                }
            }

            File::put($this->appCssPath, $content);
            $messages[] = 'Updated app.css';
        } else {
            $messages[] = 'app.css already contains the import.';
        }

        return $messages;
    }

    private function updateThemesTs($themeId, $displayName, array $vars): array
    {
        $messages = [];
        $content = File::get($this->themesConfigPath);

        // 1. Update ColorTheme type definition
        $colorThemePattern = '/export type ColorTheme = ([^\n]+)/';
        if (preg_match($colorThemePattern, $content, $matches)) {
            $existingTypes = $matches[1];
            if (! str_contains($existingTypes, "\"{$themeId}\"")) {
                // Add the new theme type
                $newTypes = mb_rtrim($existingTypes)." | \"{$themeId}\"";
                $content = preg_replace($colorThemePattern, "export type ColorTheme = {$newTypes}", $content);
            }
        }

        // 2. Extract font
        $font = 'System Sans';
        foreach (['theme', 'light'] as $section) {
            if (isset($vars[$section]['font-sans'])) {
                $fontValue = $vars[$section]['font-sans'];
                $font = mb_trim(explode(',', (string) $fontValue)[0], "\"'");
                break;
            }
        }

        // 3. Add theme config to array
        $light = $vars['light'] ?? [];
        $primary = $light['primary'] ?? 'oklch(0.5 0.2 250)';
        $secondary = $light['secondary'] ?? 'oklch(0.9 0.05 250)';
        $accent = $light['accent'] ?? 'oklch(0.9 0.05 250)';

        $newThemeConfig = <<<EOT
    {
        id: "{$themeId}",
        name: "{$displayName}",
        description: "Imported from tweakcn.",
        font: "{$font}",
        colors: {
            primary: "{$primary}",
            secondary: "{$secondary}",
            accent: "{$accent}",
        }
    }
EOT;

        if (! Str::contains($content, "id: \"{$themeId}\"")) {
            // Find the closing bracket of the themes array and insert before it
            $closingBracketPos = mb_strrpos((string) $content, ']');
            if ($closingBracketPos !== false) {
                $beforeClosing = mb_substr((string) $content, 0, $closingBracketPos);
                $afterClosing = mb_substr((string) $content, $closingBracketPos);
                $beforeClosing = mb_rtrim($beforeClosing);

                $content = $beforeClosing.",\n".$newThemeConfig."\n".$afterClosing;
                File::put($this->themesConfigPath, $content);
                $messages[] = 'Updated themes.ts';
            }
        } else {
            $messages[] = 'themes.ts already contains this theme.';
        }

        return $messages;
    }
}
