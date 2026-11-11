<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Override;

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

    #[Override]
    public $timestamps = false;

    #[Override]
    protected $table = 'document_locations';

    #[Override]
    protected $fillable = self::DOCUMENT_FIELDS;

    public function student(): HasOne
    {
        return $this->hasOne(Student::class, 'document_location_id');
    }

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
    public function toResolvedDocumentArray(?Student $student = null): array
    {
        $student = $student ?? $this->student;
        $documents = [];

        foreach (self::DOCUMENT_FIELDS as $field) {
            $value = $this->getAttribute($field);
            if (! is_string($value) || mb_trim($value) === '') {
                $documents[$field] = null;

                continue;
            }

            if (filter_var($value, FILTER_VALIDATE_URL) || str_starts_with($value, '/')) {
                $documents[$field] = $value;

                continue;
            }

            if ($student) {
                $documents[$field] = route('administrators.students.documents.fixed.show', [
                    'student' => $student->id,
                    'documentType' => $field,
                ]);
            } else {
                $documents[$field] = $this->resolveDocumentUrl($value);
            }
        }

        return $documents;
    }
}
