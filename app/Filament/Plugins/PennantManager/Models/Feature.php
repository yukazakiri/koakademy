<?php

declare(strict_types=1);

namespace App\Filament\Plugins\PennantManager\Models;

use Illuminate\Database\Eloquent\Model;

final class Feature extends Model
{
    public $timestamps = true;

    protected $table = 'features';

    protected $fillable = ['name', 'scope', 'value'];

    protected $casts = [];
}
