<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attempts')->orderBy('id')->chunkById(100, function (Collection $attempts): void {
            foreach ($attempts as $attempt) {
                $feedbackItems = DB::table('feedback')->where('attempt_id', $attempt->id)->get();

                if ($feedbackItems->isEmpty()) {
                    continue;
                }

                $totalScore = 0;

                foreach ($feedbackItems as $feedbackItem) {
                    $score = (int) $feedbackItem->criterion_score;

                    if (in_array(mb_strtolower($feedbackItem->criterion), ['situation', 'task', 'action', 'result'], true)) {
                        $score = max(1, min($score, 25));

                        if ($score !== (int) $feedbackItem->criterion_score) {
                            DB::table('feedback')->where('id', $feedbackItem->id)->update([
                                'criterion_score' => $score,
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    $totalScore += $score;
                }

                if ($totalScore !== (int) $attempt->total_score) {
                    DB::table('attempts')->where('id', $attempt->id)->update([
                        'total_score' => $totalScore,
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        /**
         * The original invalid STAR scores cannot be restored after normalization.
         */
    }
};
