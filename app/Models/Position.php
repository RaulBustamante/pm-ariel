<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'level'])]
class Position extends Model
{
    use RecordsAudit, SoftDeletes;

    protected function casts(): array
    {
        return ['level' => 'integer'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
