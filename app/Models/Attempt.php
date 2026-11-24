<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attempt extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'question_id', 'answer_text', 'total_score', 'word_count', 'filler_count', 'star_components', 'matched_keywords', 'feedback'];

    protected function casts(): array
    {
        return ['star_components' => 'array', 'matched_keywords' => 'array', 'feedback' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function feedbackItems(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }
}
