<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

final class DefaultTheme extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'theme:default {--update= : The URL of the theme JSON from tweakcn.com} {--reset : Reset the default theme to system originals}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the default theme from a JSON definition';

    private string $appCssPath;

    private string $themesTsPath;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->appCssPath = resource_path('css/app.css');
        $this->themesTsPath = resource_path('js/conf/themes.ts');

        if ($this->option('reset')) {
            return $this->resetTheme();
        }

        $url = $this->option('update');

        if (! $url) {
            info('Default theme current settings:');
            $this->showCurrentSettings();
            $this->newLine();
            $this->line('Use <comment>--update=URL</comment> to update the default theme.');
            $this->line('Use <comment>--reset</comment> to reset the default theme to system originals.');

            return self::SUCCESS;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            error('The URL must be valid.');

            return self::FAILURE;
        }

        $data = spin(
            fn (): array => Http::get($url)->json() ?? [],
            "Fetching theme from $url..."
        );

        if ($data === []) {
            error('Failed to download theme definition or invalid JSON.');

            return self::FAILURE;
        }

        try {
            if (! isset($data['cssVars'])) {
                error('Invalid theme JSON structure: missing cssVars.');

                return self::FAILURE;
            }

            $messages = spin(function () use ($data): array {
                $messages = [];

                // 1. Update app.css
                $messages = array_merge($messages, $this->updateAppCss($data['cssVars']));

                // 2. Update themes.ts
                $messages = array_merge($messages, $this->updateThemesTs($data['cssVars']));

                return $messages;
            }, 'Updating default theme...');

            foreach ($messages as $message) {
                info($message);
            }

            info('Default theme updated successfully!');

        } catch (Exception $e) {
            error('Error: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Run <comment>npm run build</comment> or <comment>npm run dev</comment> to apply the changes.');

        return self::SUCCESS;
    }

    private function showCurrentSettings(): void
    {
        $content = File::get($this->themesTsPath);
        $pattern = '/\{\s*id:\s*["\']default["\'][^}]*colors:\s*\{([^}]*)\}\s*\}/s';

        if (preg_match($pattern, $content, $matches)) {
            $colorsBlock = $matches[1];
            if (preg_match_all('/\s*(\w+):\s*["\']([^"\']+)["\']/', $colorsBlock, $colorMatches)) {
                foreach ($colorMatches[1] as $i => $key) {
                    $this->line('  <comment>'.ucfirst($key).":</comment> {$colorMatches[2][$i]}");
                }
            }
        }

        $fontPattern = '/\{\s*id:\s*["\']default["\'][^}]*font:\s*["\']([^"\']*)["\']/s';
        if (preg_match($fontPattern, $content, $matches)) {
            $this->line("  <comment>Font:</comment> {$matches[1]}");
        }
    }

    private function resetTheme(): int
    {
        $messages = spin(function (): array {
            $messages = [];

            // Reset app.css
            $content = File::get($this->appCssPath);

            $defaultRoot = <<<'EOT'
:root {
    --background: oklch(0.9751 0.0127 244.2507);
    --foreground: oklch(0.3729 0.0306 259.7328);
    --card: oklch(1 0 0);
    --card-foreground: oklch(0.3729 0.0306 259.7328);
    --popover: oklch(1 0 0);
    --popover-foreground: oklch(0.3729 0.0306 259.7328);
    --primary: oklch(0.685 0.169 237.323);
    --primary-foreground: oklch(1 0 0);
    --secondary: oklch(0.9514 0.025 236.8242);
    --secondary-foreground: oklch(0.4461 0.0263 256.8018);
    --muted: oklch(0.967 0.0029 264.5419);
    --muted-foreground: oklch(0.551 0.0234 264.3637);
    --accent: oklch(0.9505 0.0507 163.0508);
    --accent-foreground: oklch(0.3729 0.0306 259.7328);
    --destructive: oklch(0.6368 0.2078 25.3313);
    --destructive-foreground: oklch(1 0 0);
    --border: oklch(0.9276 0.0058 264.5313);
    --input: oklch(0.9276 0.0058 264.5313);
    --ring: oklch(0.7227 0.192 149.5793);
    --chart-1: oklch(0.7227 0.192 149.5793);
    --chart-2: oklch(0.6959 0.1491 162.4796);
    --chart-3: oklch(0.596 0.1274 163.2254);
    --chart-4: oklch(0.5081 0.1049 165.6121);
    --chart-5: oklch(0.4318 0.0865 166.9128);
    --radius: 0.5rem;
    --sidebar: oklch(0.9514 0.025 236.8242);
    --sidebar-foreground: oklch(0.3729 0.0306 259.7328);
    --sidebar-primary: oklch(0.7227 0.192 149.5793);
    --sidebar-primary-foreground: oklch(1 0 0);
    --sidebar-accent: oklch(0.9505 0.0507 163.0508);
    --sidebar-accent-foreground: oklch(0.3729 0.0306 259.7328);
    --sidebar-border: oklch(0.9276 0.0058 264.5313);
    --sidebar-ring: oklch(0.7227 0.192 149.5793);
    --font-sans: DM Sans, sans-serif;
    --font-serif: Lora, serif;
    --font-mono: IBM Plex Mono, monospace;
    --shadow-color: hsl(0 0% 0%);
    --shadow-opacity: 0.1;
    --shadow-blur: 8px;
    --shadow-spread: -1px;
    --shadow-offset-x: 0px;
    --shadow-offset-y: 4px;
    --letter-spacing: 0em;
    --spacing: 0.25rem;
    --shadow-2xs: 0px 4px 8px -1px hsl(0 0% 0% / 0.05);
    --shadow-xs: 0px 4px 8px -1px hsl(0 0% 0% / 0.05);
    --shadow-sm: 0px 4px 8px -1px hsl(0 0% 0% / 0.1), 0px 1px 2px -2px hsl(0 0% 0% / 0.1);
    --shadow: 0px 4px 8px -1px hsl(0 0% 0% / 0.1), 0px 1px 2px -2px hsl(0 0% 0% / 0.1);
    --shadow-md: 0px 4px 8px -1px hsl(0 0% 0% / 0.1), 0px 2px 4px -2px hsl(0 0% 0% / 0.1);
    --shadow-lg: 0px 4px 8px -1px hsl(0 0% 0% / 0.1), 0px 4px 6px -2px hsl(0 0% 0% / 0.1);
    --shadow-xl: 0px 4px 8px -1px hsl(0 0% 0% / 0.1), 0px 8px 10px -2px hsl(0 0% 0% / 0.1);
    --shadow-2xl: 0px 4px 8px -1px hsl(0 0% 0% / 0.25);
    --tracking-normal: 0em;
}
EOT;

            $defaultDark = <<<'EOT'
.dark {
    --background: oklch(0.2077 0.0398 265.7549);
    --foreground: oklch(0.8717 0.0093 258.3382);
    --card: oklch(0.2795 0.0368 260.031);
    --card-foreground: oklch(0.8717 0.0093 258.3382);
    --popover: oklch(0.2795 0.0368 260.031);
    --popover-foreground: oklch(0.8717 0.0093 258.3382);
    --primary: oklch(0.746 0.16 232.661);
    --primary-foreground: oklch(0.2077 0.0398 265.7549);
    --secondary: oklch(0.3351 0.0331 260.912);
    --secondary-foreground: oklch(0.7118 0.0129 286.0665);
    --muted: oklch(0.2463 0.0275 259.9628);
    --muted-foreground: oklch(0.551 0.0234 264.3637);
    --accent: oklch(0.3729 0.0306 259.7328);
    --accent-foreground: oklch(0.7118 0.0129 286.0665);
    --destructive: oklch(0.6368 0.2078 25.3313);
    --destructive-foreground: oklch(0.2077 0.0398 265.7549);
    --border: oklch(0.4461 0.0263 256.8018);
    --input: oklch(0.4461 0.0263 256.8018);
    --ring: oklch(0.7729 0.1535 163.2231);
    --chart-1: oklch(0.7729 0.1535 163.2231);
    --chart-2: oklch(0.7845 0.1325 181.912);
    --chart-3: oklch(0.7227 0.192 149.5793);
    --chart-4: oklch(0.6959 0.1491 162.4796);
    --chart-5: oklch(0.596 0.1274 163.2254);
    --sidebar: oklch(0.2795 0.0368 260.031);
    --sidebar-foreground: oklch(0.8717 0.0093 258.3382);
    --sidebar-primary: oklch(0.7729 0.1535 163.2231);
    --sidebar-primary-foreground: oklch(0.2077 0.0398 265.7549);
    --sidebar-accent: oklch(0.3729 0.0306 259.7328);
    --sidebar-accent-foreground: oklch(0.7118 0.0129 286.0665);
    --sidebar-border: oklch(0.4461 0.0263 256.8018);
    --sidebar-ring: oklch(0.7729 0.1535 163.2231);
    --radius: 0.5rem;
    --font-sans: DM Sans, sans-serif;
    --font-serif: Lora, serif;
    --font-mono: IBM Plex Mono, monospace;
    --shadow-color: hsl(0 0% 0%);
    --shadow-opacity: 0.1;
    --shadow-blur: 8px;
    --shadow-spread: -1px;
    --shadow-offset-x: 0px;
    --shadow-offset-y: 4px;
    --letter-spacing: 0em;
    --spacing: 0.25rem;
    --shadow-2xs: 0px 4px 8px -1px hsl(0 0% 0% / 0.05);
    --shadow-xs: 0px 4px 8px -1px hsl(0 0% 0% / 0.05);
    --shadow-sm: 0px 4px 8px -1px hsl(0 0% 0% / 0.1), 0px 1px 2px -2px hsl(0 0% 0% / 0.1);
    --shadow: 0px 4px 8px -1px hsl(0 0% 0% / 0.1), 0px 1px 2px -2px hsl(0 0% 0% / 0.1);
    --shadow-md: 0px 4px 8px -1px hsl(0 0% 0% / 0.1), 0px 2px 4px -2px hsl(0 0% 0% / 0.1);
    --shadow-lg: 0px 4px 8px -1px hsl(0 0% 0% / 0.1), 0px 4px 6px -2px hsl(0 0% 0% / 0.1);
    --shadow-xl: 0px 4px 8px -1px hsl(0 0% 0% / 0.1), 0px 8px 10px -2px hsl(0 0% 0% / 0.1);
    --shadow-2xl: 0px 4px 8px -1px hsl(0 0% 0% / 0.25);
}
EOT;

            $content = preg_replace('/\n:root\s*\{([^}]*)\}/s', "\n".$defaultRoot, $content);
            $content = preg_replace('/\n\.dark\s*\{([^}]*)\}/s', "\n".$defaultDark, $content);

            File::put($this->appCssPath, $content);
            $messages[] = 'Reset app.css variables to default';

            // Reset themes.ts
            $tsContent = File::get($this->themesTsPath);

            // Default colors
            $primary = 'oklch(0.6850 0.1690 237.3230)';
            $secondary = 'oklch(0.9514 0.0250 236.8242)';
            $accent = 'oklch(0.9505 0.0507 163.0508)';
            $font = 'DM Sans';

            $pattern = '/(\{\s*id:\s*["\']default["\'][^}]*colors:\s*\{)([^}]*)(\}\s*\})/s';
            $replacement = <<<EOT
$1
            primary: "{$primary}",
            secondary: "{$secondary}",
            accent: "{$accent}",
        $3
EOT;
            $tsContent = preg_replace($pattern, $replacement, $tsContent);

            $fontPattern = '/(\{\s*id:\s*["\']default["\'][^}]*font:\s*["\'])([^"\']*)(["\'])/s';
            $tsContent = preg_replace($fontPattern, "\${1}{$font}\${3}", $tsContent);

            File::put($this->themesTsPath, $tsContent);
            $messages[] = 'Reset default theme in themes.ts';

            return $messages;
        }, 'Resetting default theme...');

        foreach ($messages as $message) {
            info($message);
        }

        info('Default theme reset successfully!');

        return self::SUCCESS;
    }

    private function updateAppCss(array $vars): array
    {
        $messages = [];
        $content = File::get($this->appCssPath);

        $light = $vars['light'] ?? [];
        $dark = $vars['dark'] ?? [];
        $common = $vars['theme'] ?? [];

        // 1. Update :root block - only the one at the start of the line to avoid matching .dark * or other scoped blocks
        $rootPattern = '/\n:root\s*\{([^}]*)\}/s';
        if (preg_match($rootPattern, $content, $matches)) {
            $existingVars = $matches[1];
            $newVars = $this->mergeVars($existingVars, array_merge($common, $light));
            $content = preg_replace($rootPattern, "\n:root {\n{$newVars}}", $content);
            $messages[] = 'Updated :root variables in app.css';
        }

        // 2. Update .dark block - only the one at the start of the line
        $darkPattern = '/\n\.dark\s*\{([^}]*)\}/s';
        if (preg_match($darkPattern, (string) $content, $matches)) {
            $existingVars = $matches[1];
            $newVars = $this->mergeVars($existingVars, $dark);
            $content = preg_replace($darkPattern, "\n.dark {\n{$newVars}}", (string) $content);
            $messages[] = 'Updated .dark variables in app.css';
        }

        File::put($this->appCssPath, $content);

        return $messages;
    }

    /**
     * Merge new variables into existing ones, preserving others.
     */
    private function mergeVars(string $existingContent, array $newVars): string
    {
        $vars = [];
        // Extract existing vars
        if (preg_match_all('/\s*(--[\w-]+):\s*([^;]+);/', $existingContent, $matches)) {
            foreach ($matches[1] as $i => $key) {
                $vars[$key] = $matches[2][$i];
            }
        }

        // Update with new vars
        foreach ($newVars as $key => $value) {
            $vars["--{$key}"] = $value;
        }

        // Reconstruct content
        $content = '';
        foreach ($vars as $key => $value) {
            $content .= "    {$key}: {$value};\n";
        }

        return $content;
    }

    private function updateThemesTs(array $vars): array
    {
        $messages = [];
        $content = File::get($this->themesTsPath);

        $light = $vars['light'] ?? [];
        $primary = $light['primary'] ?? 'oklch(0.6850 0.1690 237.3230)';
        $secondary = $light['secondary'] ?? 'oklch(0.9514 0.0250 236.8242)';
        $accent = $light['accent'] ?? 'oklch(0.9505 0.0507 163.0508)';

        // Extract font
        $font = 'DM Sans';
        foreach (['theme', 'light'] as $section) {
            if (isset($vars[$section]['font-sans'])) {
                $fontValue = $vars[$section]['font-sans'];
                $font = mb_trim(explode(',', (string) $fontValue)[0], "\"'");
                break;
            }
        }

        // Find the "default" theme entry in the themes array
        // We'll use a more robust regex to find the object with id: "default"
        $pattern = '/(\{\s*id:\s*["\']default["\'][^}]*colors:\s*\{)([^}]*)(\}\s*\})/s';

        if (preg_match($pattern, $content)) {
            $replacement = <<<EOT
\$1
            primary: "{$primary}",
            secondary: "{$secondary}",
            accent: "{$accent}",
        \$3
EOT;
            // Also need to update font and potentially description/name if needed,
            // but for now let's focus on font and colors.
            $content = preg_replace($pattern, $replacement, $content);

            // Update font
            $fontPattern = '/(\{\s*id:\s*["\']default["\'][^}]*font:\s*["\'])([^"\']*)(["\'])/s';
            $content = preg_replace($fontPattern, "\${1}{$font}\${3}", (string) $content);

            $messages[] = 'Updated default theme in themes.ts';
        }

        File::put($this->themesTsPath, $content);

        return $messages;
    }
}
