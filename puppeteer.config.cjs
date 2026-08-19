/**
 * KoAkademy the PDF rendering to the Gotenberg driver via spatie/laravel-pdf
 * (see config/laravel-pdf.php — the default driver is "gotenberg" and there is
 * no Puppeteer/browsershot fallback).
 *
 * The local Puppeteer browser binaries are therefore never used at runtime, but
 * the `puppeteer` npm package still runs its postinstall browser download on
 * every `npm install` (and thus `composer setup`). That download can fail or be
 * interrupted — leaving a corrupt cache directory that keeps failing forever.
 *
 * Skipping the download here keeps `npm install` / `composer setup` reliable on
 * every machine. This is read by puppeteer's config loader (cosmiconfig) from
 * the project root, so it works portably without any machine-local env vars.
 */
module.exports = {
    skipDownload: true,
};