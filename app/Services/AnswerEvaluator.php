<?php

namespace App\Services;

use App\Models\Question;

class AnswerEvaluator
{
    /**
     * @return array{total_score:int,word_count:int,filler_count:int,star_components:array<int,string>,matched_keywords:array<int,string>,feedback:array<int,array{criterion:string,criterion_score:int,tip:string}>}
     */
    public function evaluate(string $answer, Question $question): array
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[\'’][\p{L}\p{N}]+)*/u', $answer, $wordMatches);
        $wordCount = count($wordMatches[0]);
        $lower = mb_strtolower($answer);
        $fillers = ['um', 'uh', 'like', 'basically'];
        $fillerCount = 0;
        foreach ($fillers as $filler) {
            preg_match_all('/(?<![\p{L}\p{N}])'.preg_quote($filler, '/').'(?![\p{L}\p{N}])/u', $lower, $matches);
            $fillerCount += count($matches[0]);
        }

        $markers = [
            'Situation' => ['when', 'while', 'during', 'at my', 'in my', 'our team', 'the situation', 'context'],
            'Task' => ['responsible', 'my role', 'my goal', 'needed to', 'task was', 'asked to', 'challenge was', 'i had to'],
            'Action' => ['i created', 'i led', 'i built', 'i organized', 'i analyzed', 'i decided', 'i worked', 'i implemented', 'i communicated', 'i developed', 'i took', 'i improved', 'i coordinated', 'i researched'],
            'Result' => ['result', 'as a result', 'which led', 'increased', 'reduced', 'improved', 'achieved', 'completed', 'saved', 'learned', 'successfully', 'percent', '%'],
        ];
        $starComponents = [];
        foreach ($markers as $component => $terms) {
            foreach ($terms as $term) {
                if (str_contains($lower, $term)) {
                    $starComponents[] = $component;
                    break;
                }
            }
        }

        $keyTerms = $question->key_terms ?? [];
        $matchedTerms = array_values(array_filter($keyTerms, fn (string $term): bool => $term !== '' && str_contains($lower, mb_strtolower($term))));
        $scores = [
            'answer_length' => min(25, (int) round($wordCount / 100 * 25)),
            'star_structure' => (int) round(count($starComponents) / 4 * 35),
            'filler_words' => max(0, 20 - min(20, $fillerCount * 4)),
            'key_terms' => count($keyTerms) ? (int) round(count($matchedTerms) / count($keyTerms) * 20) : 0,
        ];

        $tips = [
            'answer_length' => $wordCount < 80
                ? 'Add enough detail to explain your experience clearly; aim for about 100 to 180 words.'
                : ($wordCount > 220 ? 'Tighten the answer and keep details focused on the question.' : 'Your answer has room for a clear example; keep each detail relevant.'),
            'star_structure' => count($starComponents) < 4
                ? 'Make the Situation, Task, Action, and Result easy to identify in your answer.'
                : 'Your text includes cues for all four STAR components; keep your actions specific.',
            'filler_words' => $fillerCount > 0
                ? "We detected {$fillerCount} possible filler words. Try a short pause instead."
                : 'No common filler words were detected in the text you entered.',
            'key_terms' => count($matchedTerms) < count($keyTerms)
                ? 'Where accurate to your experience, connect your answer to the question terms: '.implode(', ', array_diff($keyTerms, $matchedTerms)).'.'
                : 'Your answer includes the terms associated with this question; use them naturally.',
        ];
        $feedback = [];
        foreach ($scores as $criterion => $score) {
            $feedback[] = ['criterion' => $criterion, 'criterion_score' => $score, 'tip' => $tips[$criterion]];
        }

        return [
            'total_score' => array_sum($scores),
            'word_count' => $wordCount,
            'filler_count' => $fillerCount,
            'star_components' => $starComponents,
            'matched_keywords' => $matchedTerms,
            'feedback' => $feedback,
        ];
    }
}
