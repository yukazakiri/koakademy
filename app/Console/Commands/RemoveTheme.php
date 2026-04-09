<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\spin;

final class RemoveTheme extends Command
{
    protected $signature = 'theme:remove
                            {theme?* : The theme ID(s) to remove}
                            {--list : List all available themes}
                            {--force : Remove without confirmation}';

    protected $description = 'Remove a theme from the application';

    private string $themesDir;

    private string $themesConfigPath;

    private string $appCssPath;

    /** @var array<string> */
    private array $protectedThemes = ['default'];

    public function handle(): int
    {
        $this->themesDir = resource_path('css/themes');
        $this->themesConfigPath = resource_path('js/conf/themes.ts');
        $this->appCssPath = resource_path('css/app.css');

        if ($this->option('list')) {
            return $this->listThemes();
        }

        $themeIds = $this->argument('theme');

        if (empty($themeIds)) {
            $themes = $this->getAvailableThemes();

            $removableThemes = array_values(array_diff($themes, $this->protectedThemes));

            if ($removableThemes === []) {
                error('No themes available to remove.');

                return self::FAILURE;
            }

            $themeIds = multiselect(
                label: 'Which theme(s) would you like to remove?',
                options: $removableThemes,
                required: true
            );
        }

        foreach ($themeIds as $themeId) {
            if (in_array($themeId, $this->protectedThemes)) {
                error("The '{$themeId}' theme is protected and cannot be removed.");

                continue;
            }

            if (! $this->themeExists($themeId)) {
                error("Theme '{$themeId}' does not exist.");

                continue;
            }

            if (! $this->option('force') && ! confirm("Are you sure you want to remove the '{$themeId}' theme?")) {
                info("Skipped removal of '{$themeId}'.");

                continue;
            }

            $messages = spin(function () use ($themeId): array {
                $messages = [];

                // Remove CSS file
                $cssFile = "{$this->themesDir}/{$themeId}.css";
                if (File::exists($cssFile)) {
                    File::delete($cssFile);
                    $messages[] = "Removed CSS file: {$cssFile}";
                } else {
                    $messages[] = "CSS file not found: {$cssFile}";
                }

                // Remove import from app.css
                $appCss = File::get($this->appCssPath);
                $patterns = [
                    '/@import\s+["\']\.\/themes\/'.$themeId.'\.css["\'];\n?/',
                    '/@import\s+["\']\.\.\/css\/themes\/'.$themeId.'\.css["\'];\n?/',
                ];
                $modified = false;
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, (string) $appCss)) {
                        $appCss = preg_replace($pattern, '', (string) $appCss);
                        $modified = true;
                    }
                }
                if ($modified) {
                    $appCss = preg_replace('/\n{3,}/', "\n\n", (string) $appCss);
                    File::put($this->appCssPath, $appCss);
                    $messages[] = 'Removed import from app.css';
                } else {
                    $messages[] = 'Import not found in app.css';
                }

                // Remove from themes.ts config
                $themesConfig = File::get($this->themesConfigPath);
                $configModified = false;
                $colorThemePattern = '/export type ColorTheme = ([^\n]+)/';
                if (preg_match($colorThemePattern, $themesConfig, $matches)) {
                    $existingTypes = $matches[1];
                    $newTypes = preg_replace('/\s*\|\s*["\']'.preg_quote((string) $themeId, '/').'["\']/', '', $existingTypes);
                    $newTypes = preg_replace('/["\']'.preg_quote((string) $themeId, '/').'["\']\s*\|\s*/', '', (string) $newTypes);
                    if ($newTypes !== $existingTypes) {
                        $themesConfig = preg_replace($colorThemePattern, "export type ColorTheme = {$newTypes}", $themesConfig);
                        $configModified = true;
                    }
                }
                $themeObjectPattern = '/,?\s*\{\s*id:\s*["\']'.preg_quote((string) $themeId, '/').'["\'][^}]*colors:\s*\{[^}]*\}\s*\}/s';
                if (preg_match($themeObjectPattern, (string) $themesConfig)) {
                    $themesConfig = preg_replace($themeObjectPattern, '', (string) $themesConfig);
                    $configModified = true;
                }
                $themeObjectPatternFirst = '/\{\s*id:\s*["\']'.preg_quote((string) $themeId, '/').'["\'][^}]*colors:\s*\{[^}]*\}\s*\},?\s*/s';
                if (preg_match($themeObjectPatternFirst, (string) $themesConfig)) {
                    $themesConfig = preg_replace($themeObjectPatternFirst, '', (string) $themesConfig);
                    $configModified = true;
                }
                if ($configModified) {
                    $themesConfig = preg_replace('/,\s*,/', ',', (string) $themesConfig);
                    $themesConfig = preg_replace('/,\s*\]/', "\n]", (string) $themesConfig);
                    $themesConfig = preg_replace('/\[\s*,/', '[', (string) $themesConfig);
                    File::put($this->themesConfigPath, $themesConfig);
                    $messages[] = 'Removed theme from themes.ts configuration';
                } else {
                    $messages[] = 'Theme not found in themes.ts configuration';
                }

                return $messages;
            }, "Removing theme: {$themeId}");

            foreach ($messages as $message) {
                info($message);
            }

            info("Theme '{$themeId}' removed successfully!");
        }

        $this->newLine();
        info('Selected themes processed.');
        $this->newLine();
        $this->line('Run <comment>npm run build</comment> or <comment>npm run dev</comment> to apply the changes.');

        return self::SUCCESS;
    }

    private function listThemes(): int
    {
        $themes = $this->getAvailableThemes();

        if ($themes === []) {
            $this->info('No custom themes installed.');

            return self::SUCCESS;
        }

        $this->info('Available themes:');
        $this->newLine();

        foreach ($themes as $theme) {
            $protected = in_array($theme, $this->protectedThemes) ? ' <comment>(protected)</comment>' : '';
            $this->line("  - {$theme}{$protected}");
        }

        return self::SUCCESS;
    }

    /**
     * Get all available theme IDs from the themes directory.
     *
     * @return array<string>
     */
    private function getAvailableThemes(): array
    {
        if (! File::isDirectory($this->themesDir)) {
            return [];
        }

        $files = File::files($this->themesDir);
        $themes = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'css') {
                $themes[] = $file->getFilenameWithoutExtension();
            }
        }

        // Also include 'default' since it's in the base app.css
        if (! in_array('default', $themes)) {
            array_unshift($themes, 'default');
        }

        sort($themes);

        return $themes;
    }

    private function themeExists(string $themeId): bool
    {
        // Check if it's the default theme (no CSS file, but exists in config)
        if ($themeId === 'default') {
            return true;
        }

        $cssFile = "{$this->themesDir}/{$themeId}.css";

        return File::exists($cssFile);
    }
}
