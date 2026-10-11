<?php

declare(strict_types=1);

namespace App\Email;

/**
 * Resolves the effective subject and intro copy for a template-driven email.
 *
 * Resolution order for every field is: stored override -> built-in catalog default.
 * A template may therefore set only the parts an institution cares about and inherit
 * the rest. Tokens use the `{{ token }}` / `{{token}}` spelling used by the editor.
 */
final class EmailTemplateRenderer
{
    public function __construct(private readonly SystemEmailTemplateStore $store) {}

    /**
     * Effective subject for the given key, falling back to the built-in default.
     *
     * Resolution order: caller-supplied override -> installation-wide override ->
     * built-in catalog default.
     *
     * @param  array<string, mixed>  $tokens
     * @param  array<string, mixed>|null  $override
     */
    public function subject(string $key, array $tokens = [], ?array $override = null): string
    {
        $default = EmailTemplateCatalog::defaultFor($key)['subject'] ?? '';

        $subject = $this->stringOrNull($override['subject'] ?? null)
            ?? $this->stringOrNull($this->storedFor($key)['subject'] ?? null)
            ?? $default;

        return $this->interpolate($subject, $tokens);
    }

    /**
     * Effective intro copy. Returns an empty string when nothing defines one, so
     * callers can skip rendering an intro paragraph.
     *
     * @param  array<string, mixed>  $tokens
     * @param  array<string, mixed>|null  $override
     */
    public function intro(string $key, array $tokens = [], ?array $override = null): string
    {
        $default = EmailTemplateCatalog::defaultFor($key)['intro'] ?? '';

        $stored = $this->storedFor($key);

        $intro = $this->stringOrNull($override['intro'] ?? $override['custom_body'] ?? null)
            ?? $this->stringOrNull($stored['intro'] ?? $stored['custom_body'] ?? null)
            ?? $default;

        return mb_trim($this->interpolate($intro, $tokens));
    }

    /**
     * Emit the intro copy as MailMessage lines, preserving blank-line paragraphing.
     *
     * @param  array<string, mixed>  $tokens
     * @param  array<string, mixed>|null  $override
     * @return list<string>
     */
    public function introLines(string $key, array $tokens = [], ?array $override = null): array
    {
        $intro = $this->intro($key, $tokens, $override);

        if ($intro === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $intro)), fn (string $line): bool => $line !== ''));
    }

    /**
     * The call-to-action label for a template, falling back to the supplied default.
     *
     * @param  array<string, mixed>|null  $override
     */
    public function buttonLabel(string $key, ?array $override = null, ?string $fallback = null): ?string
    {
        return $this->stringOrNull($override['button_label'] ?? null)
            ?? $this->stringOrNull($this->storedFor($key)['button_label'] ?? null)
            ?? $fallback;
    }

    /**
     * Replace every `{{ token }}` / `{{token}}` placeholder.
     *
     * @param  array<string, mixed>  $tokens
     */
    public function interpolate(string $text, array $tokens): string
    {
        if ($text === '' || $tokens === []) {
            return $text;
        }

        $replacements = [];

        foreach ($tokens as $name => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $replacements['{{ '.$name.' }}'] = (string) $value;
            $replacements['{{'.$name.'}}'] = (string) $value;
        }

        return strtr($text, $replacements);
    }

    /**
     * The editable shape used by the admin UI: only subject and prose are writable.
     *
     * Structured content (tables, attachments, action links) is never part of this.
     *
     * @param  array<string, mixed>|null  $stored
     * @return array{subject: string, intro: string}
     */
    public function editablePayload(?array $stored = null): array
    {
        return [
            'subject' => $this->stringOrNull($stored['subject'] ?? null) ?? '',
            'intro' => $this->stringOrNull($stored['intro'] ?? $stored['custom_body'] ?? null) ?? '',
        ];
    }

    /**
     * Enrollment templates live on the policy draft, so they are never read from the
     * installation-wide store. Everything else may be overridden centrally.
     *
     * @return array<string, mixed>
     */
    private function storedFor(string $key): array
    {
        if (in_array($key, EmailTemplateCatalog::ENROLLMENT_KEYS, true)) {
            return [];
        }

        return $this->store->get($key);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
