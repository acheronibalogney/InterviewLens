<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attempts')->orderBy('id')->chunkById(100, function ($attempts): void {
            foreach ($attempts as $attempt) {
                $starComponents = json_decode($attempt->star_components, true) ?: [];
                $feedbackItems = DB::table('feedback')->where('attempt_id', $attempt->id)->get();

                if ($feedbackItems->isEmpty()) {
                    continue;
                }

                $totalScore = 0;

                foreach ($feedbackItems as $feedbackItem) {
                    $component = match (mb_strtolower($feedbackItem->criterion)) {
                        'situation' => 'Situation',
                        'task' => 'Task',
                        'action' => 'Action',
                        'result' => 'Result',
                        default => null,
                    };
                    $score = (int) $feedbackItem->criterion_score;

                    if ($component !== null && ! in_array($component, $starComponents, true)) {
                        $score = min($score, 5);

                        DB::table('feedback')->where('id', $feedbackItem->id)->update([
                            'criterion_score' => $score,
                            'tip' => "The answer did not show clear {$component} evidence. This criterion is capped at 5/25; add a specific detail to earn more points.",
                            'updated_at' => now(),
                        ]);
                    }

                    $totalScore += $score;
                }

                DB::table('attempts')->where('id', $attempt->id)->update([
                    'total_score' => $totalScore,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        /**
         * The incorrect historical scores are not restored because they lacked supporting STAR evidence.
         */
    }
};
