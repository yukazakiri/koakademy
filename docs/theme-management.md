# Theme management

Koakademy uses `yukazakiri/laravel-shadebridge` for build-time theme imports, removal, and system defaults. The custom profile personalization UI stays in Koakademy; theme-management Artisan commands belong to the package.

## Installation and updates

Shadebridge `0.2.0` is released and locked in this application. The public GitHub VCS repository is configured explicitly, so installation does not depend on Packagist registration. Install with `composer install` on the supported PHP version (`>=8.5.12 <8.6`). To update within `0.2.x`, run:

```sh
composer update yukazakiri/laravel-shadebridge --with-dependencies
php artisan theme:default
npm run build
```

Commit the resulting `composer.lock`. No machine-specific Composer path repository is needed. Package source and release: https://github.com/yukazakiri/laravel-shadebridge/releases/tag/0.2.0.

## Import and update

```sh
php artisan theme:import https://tweakcn.com/r/themes/kodama-grove.json
php artisan theme:import https://tweakcn.com/r/themes/kodama-grove.json --force
```

`theme:add` is an alias for the same importer. Review generated CSS, catalog metadata, and CSS imports before committing. Only theme variables and supported simple base rules are installed, not registry scripts/components. Source changes require rebuilding frontend assets.

## Set the system default

```sh
php artisan theme:default kodama-grove
php artisan theme:default --reset
```

The default is stored as `defaultColorTheme` in `resources/js/conf/themes.ts`. It applies to visitors without a valid saved color choice. Existing browser selections (`ui-color-theme`) take precedence. Removed or invalid selections fall back to the system default; the fallback is not saved as a user preference. The original `default` palette remains available and protected.

The package's `theme-tools::initialize` Blade view applies the same choice before hydration. Koakademy's React provider validates stored IDs again and removes stale `theme-*` classes. Light/dark appearance remains independent (`ui-theme`).

## Remove old themes

```sh
php artisan theme:remove --list
php artisan theme:remove obsolete-theme
# Or choose several themes interactively:
php artisan theme:remove
npm run build
```

Removal cleans files, CSS imports, catalog entries, and type IDs. Removing the system-default theme resets the system default to the original `default` palette. The old application-specific `theme:default --update=URL` command is replaced by importing a named theme and selecting it with `theme:default ID`.

Do not run `theme:setup --force` or overwrite Koakademy's customized profile and theme provider during package upgrades.
