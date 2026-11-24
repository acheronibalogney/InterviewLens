<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = ['category', 'question_text', 'key_terms', 'is_behavioral', 'is_active'];

    protected function casts(): array
    {
        return ['key_terms' => 'array', 'is_behavioral' => 'boolean', 'is_active' => 'boolean'];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }
}
