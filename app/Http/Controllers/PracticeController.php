<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\Resource;
use App\Services\AnswerEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PracticeController extends Controller
{
    public function index(Request $request): View
    {
        $recent = $request->user()->attempts()->with('question')->latest()->take(8)->get();

        return view('practice.index', [
            'questions' => Question::query()->where('is_active', true)->orderBy('category')->orderBy('id')->get(),
            'recent' => $recent,
            'totalAnswers' => $request->user()->attempts()->count(),
            'averageScore' => (int) round($recent->avg('total_score') ?? 0),
        ]);
    }

    public function evaluate(Request $request, AnswerEvaluator $evaluator): RedirectResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'answer' => ['required', 'string', 'min:20', 'max:12000'],
        ]);
        $question = Question::query()->where('is_active', true)->findOrFail($data['question_id']);
        $result = $evaluator->evaluate($data['answer'], $question);

        $attempt = DB::transaction(function () use ($request, $question, $data, $result): Attempt {
            $attempt = $request->user()->attempts()->create([
                'question_id' => $question->id,
                'answer_text' => $data['answer'],
                ...$result,
            ]);
            $attempt->feedbackItems()->createMany($result['feedback']);

            return $attempt;
        });

        return redirect()->route('results.show', $attempt);
    }

    public function results(Request $request, Attempt $attempt): View
    {
        abort_unless($attempt->user_id === $request->user()->id, 404);
        $attempt->load(['question', 'feedbackItems']);
        $weaknesses = [];
        if ($attempt->word_count < 80 || $attempt->word_count > 220) {
            $weaknesses[] = 'answer_length';
        }
        if ($attempt->filler_count > 0) {
            $weaknesses[] = 'filler_words';
        }
        if (count($attempt->star_components) < 4) {
            $weaknesses[] = 'star_structure';
        }
        if (count($attempt->matched_keywords) < 2) {
            $weaknesses[] = 'keyword_relevance';
        }
        $recommendations = $weaknesses ? Resource::query()->whereJsonContains('skills', $weaknesses[0])->orWhere(function ($query) use ($weaknesses): void {
            foreach (array_slice($weaknesses, 1) as $weakness) {
                $query->orWhereJsonContains('skills', $weakness);
            }
        })->take(3)->get() : collect();

        return view('practice.results', compact('attempt', 'recommendations'));
    }

    public function history(Request $request): View
    {
        $attempts = $request->user()->attempts()->with('question')->latest()->paginate(20);
        $recentScores = $request->user()->attempts()->latest()->take(8)->pluck('total_score');

        return view('practice.history', [
            'attempts' => $attempts,
            'totalAnswers' => $request->user()->attempts()->count(),
            'averageScore' => (int) round($recentScores->avg() ?? 0),
        ]);
    }

    public function resources(): View
    {
        return view('practice.resources', ['resources' => Resource::query()->orderBy('category')->orderBy('title')->get()]);
    }
}
