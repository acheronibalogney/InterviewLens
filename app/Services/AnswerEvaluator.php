<?php

namespace App\Services;

use App\Models\Question;

class AnswerEvaluator
{
    /** @return array{score:int,word_count:int,filler_count:int,star_components:array,matched_keywords:array,feedback:array} */
    public function evaluate(string $answer, Question $question): array
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[\'’][\p{L}\p{N}]+)*/u', $answer, $wordMatches);
        $wordCount = count($wordMatches[0]);
        $lower = mb_strtolower($answer);
        $fillerWords = ['um', 'uh', 'like', 'basically', 'actually', 'literally', 'you know', 'i mean', 'sort of', 'kind of'];
        $foundFillers = [];
        foreach ($fillerWords as $filler) {
            preg_match_all('/(?<![\p{L}\p{N}])'.preg_quote($filler, '/').'(?![\p{L}\p{N}])/u', $lower, $matches);
            if (count($matches[0])) {
                $foundFillers[$filler] = count($matches[0]);
            }
        }

        $markers = [
            'Situation' => ['when', 'while', 'during', 'at my', 'in my', 'our team', 'the situation', 'context'],
            'Task' => ['responsible', 'my role', 'my goal', 'needed to', 'task was', 'asked to', 'challenge was', 'i had to'],
            'Action' => ['i created', 'i led', 'i built', 'i organized', 'i analyzed', 'i decided', 'i worked', 'i implemented', 'i communicated', 'i developed', 'i took', 'i improved', 'i coordinated', 'i researched'],
            'Result' => ['result', 'as a result', 'which led', 'increased', 'reduced', 'improved', 'achieved', 'completed', 'saved', 'learned', 'successfully', 'percent', '%'],
        ];
        $star = [];
        foreach ($markers as $name => $terms) {
            foreach ($terms as $term) {
                if (str_contains($lower, $term)) {
                    $star[] = $name;
                    break;
                }
            }
        }
        $keywords = $question->keywords ?? [];
        $matched = array_values(array_filter($keywords, fn (string $term): bool => $term !== '' && str_contains($lower, mb_strtolower($term))));
        $lengthPoints = min(25, (int) round($wordCount / 100 * 25));
        if ($wordCount > 220) {
            $lengthPoints = max(0, 25 - (int) ceil(($wordCount - 220) / 20));
        }
        $starPoints = $question->is_behavioral ? (int) round(count($star) / 4 * 35) : (count($star) >= 2 ? 25 : (count($star) ? 18 : 8));
        $fillerCount = array_sum($foundFillers);
        $fillerPoints = max(0, 20 - min(20, $fillerCount * 4));
        $keywordPoints = count($keywords) ? (int) round(count($matched) / count($keywords) * 20) : 12;

        $feedback = [];
        if ($wordCount < 80) {
            $feedback[] = ['title' => 'Add useful detail', 'text' => 'Aim for roughly 100–180 words. Give enough context for the interviewer to understand your example.'];
        } elseif ($wordCount > 220) {
            $feedback[] = ['title' => 'Tighten the answer', 'text' => 'Your answer is long. Keep the context brief and focus on the actions and outcome.'];
        } else {
            $feedback[] = ['title' => 'Good answer length', 'text' => 'Your answer has enough room for a clear example. Keep each detail relevant to the question.'];
        }

        $missingStar = array_values(array_diff(['Situation', 'Task', 'Action', 'Result'], $star));
        if ($question->is_behavioral && $missingStar) {
            $feedback[] = ['title' => 'Complete the STAR story', 'text' => 'Make the situation, your task, your specific action, and the result easy to identify. Missing: '.implode(', ', $missingStar).'.'];
        } elseif (! $question->is_behavioral && count($star) < 2) {
            $feedback[] = ['title' => 'Make your answer concrete', 'text' => 'Add one specific example, action, or outcome to support your point.'];
        } else {
            $feedback[] = ['title' => 'Clear supporting structure', 'text' => 'Your answer includes concrete context and actions. Make sure the result connects to the question.'];
        }

        if ($fillerCount) {
            $feedback[] = ['title' => 'Reduce filler words', 'text' => $fillerCount.' possible filler '.($fillerCount === 1 ? 'word was' : 'words were').' found ('.implode(', ', array_keys($foundFillers)).'). Pause briefly instead of filling the silence.'];
        } else {
            $feedback[] = ['title' => 'Clean delivery', 'text' => 'No common filler words were detected in the text you entered.'];
        }

        $missingKeywords = array_values(array_diff($keywords, $matched));
        if ($missingKeywords) {
            $feedback[] = ['title' => 'Connect to the role', 'text' => 'Where it fits your real experience, mention relevant ideas such as '.implode(', ', array_slice($missingKeywords, 0, 4)).'.'];
        } else {
            $feedback[] = ['title' => 'Relevant terms included', 'text' => 'Your answer uses terms related to this question. Keep them natural and specific.'];
        }

        return [
            'score' => min(100, $lengthPoints + $starPoints + $fillerPoints + $keywordPoints),
            'word_count' => $wordCount,
            'filler_count' => $fillerCount,
            'star_components' => $star,
            'matched_keywords' => $matched,
            'feedback' => $feedback,
        ];
    }
}
