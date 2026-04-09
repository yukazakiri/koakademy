<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\LibrarySystem\Database\Factories\CategoryFactory;

final class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'library_categories';

    protected $fillable = [
        'name',
        'description',
        'color',
    ];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    // protected static function newFactory()
    // {
    //     return CategoryFactory::new();
    // }
}
