<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Question;
use App\Models\Resource;
use App\Services\AnswerEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PracticeController extends Controller
{
    public function index(Request $request): View
    {
        $recent = $request->user()->evaluations()->with('question')->latest()->take(8)->get();

        return view('practice.index', [
            'questions' => Question::query()->where('is_active', true)->orderBy('category')->orderBy('id')->get(),
            'recent' => $recent,
            'totalAnswers' => $request->user()->evaluations()->count(),
            'averageScore' => (int) round($recent->avg('score') ?? 0),
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
        $evaluation = $request->user()->evaluations()->create(['question_id' => $question->id, 'answer' => $data['answer'], ...$result]);

        return redirect()->route('results.show', $evaluation);
    }

    public function results(Request $request, Evaluation $evaluation): View
    {
        abort_unless($evaluation->user_id === $request->user()->id, 404);
        $evaluation->load('question');
        $weaknesses = [];
        if ($evaluation->word_count < 80 || $evaluation->word_count > 220) {
            $weaknesses[] = 'answer_length';
        }
        if ($evaluation->filler_count > 0) {
            $weaknesses[] = 'filler_words';
        }
        if ($evaluation->question->is_behavioral && count($evaluation->star_components) < 4) {
            $weaknesses[] = 'star_structure';
        }
        if (count($evaluation->matched_keywords) < 2) {
            $weaknesses[] = 'keyword_relevance';
        }
        $recommendations = $weaknesses ? Resource::query()->whereJsonContains('skills', $weaknesses[0])->orWhere(function ($query) use ($weaknesses) {
            foreach (array_slice($weaknesses, 1) as $weakness) {
                $query->orWhereJsonContains('skills', $weakness);
            }
        })->take(3)->get() : collect();

        return view('practice.results', compact('evaluation', 'recommendations'));
    }

    public function history(Request $request): View
    {
        $evaluations = $request->user()->evaluations()->with('question')->latest()->paginate(20);
        $recentScores = $request->user()->evaluations()->latest()->take(8)->pluck('score');

        return view('practice.history', ['evaluations' => $evaluations, 'totalAnswers' => $request->user()->evaluations()->count(), 'averageScore' => (int) round($recentScores->avg() ?? 0)]);
    }

    public function resources(): View
    {
        return view('practice.resources', ['resources' => Resource::query()->orderBy('category')->orderBy('title')->get()]);
    }
}
