<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Class DocumentLocation
 *
 * @method static Builder<static>|DocumentLocation newModelQuery()
 * @method static Builder<static>|DocumentLocation newQuery()
 * @method static Builder<static>|DocumentLocation query()
 *
 * @mixin \Eloquent
 */
final class DocumentLocation extends Model
{
    public const array DOCUMENT_FIELDS = [
        'birth_certificate',
        'form_138',
        'form_137',
        'good_moral_cert',
        'transfer_credentials',
        'transcript_records',
        'picture_1x1',
    ];

    public $timestamps = false;

    protected $table = 'document_locations';

    protected $fillable = self::DOCUMENT_FIELDS;

    public function resolveDocumentUrl(?string $path): ?string
    {
        if (! is_string($path) || mb_trim($path) === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::url($path);
    }

    /**
     * @return array<string, string|null>
     */
    public function toResolvedDocumentArray(): array
    {
        $documents = [];

        foreach (self::DOCUMENT_FIELDS as $field) {
            /** @var mixed $value */
            $value = $this->getAttribute($field);
            $documents[$field] = is_string($value) ? $this->resolveDocumentUrl($value) : null;
        }

        return $documents;
    }
}
