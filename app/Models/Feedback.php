<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    //
    protected $fillable = ['criterion', 'criterion_score', 'tip'];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }
}
