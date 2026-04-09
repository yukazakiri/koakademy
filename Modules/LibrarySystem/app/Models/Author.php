<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Models;

// use Modules\LibrarySystem\Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Author extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'library_authors';

    protected $fillable = [
        'name',
        'biography',
        'birth_date',
        'nationality',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
