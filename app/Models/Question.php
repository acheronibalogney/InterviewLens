<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = ['category', 'question', 'keywords', 'is_behavioral', 'is_active'];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'is_behavioral' => 'boolean', 'is_active' => 'boolean'];
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
