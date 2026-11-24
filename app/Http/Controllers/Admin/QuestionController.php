<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function index(): View
    {
        return view('admin.questions', ['questions' => Question::query()->orderBy('category')->orderBy('question_text')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Question::query()->create($this->validatedQuestion($request));

        return redirect()->route('admin.questions.index')->with('status', 'Question added to the bank.');
    }

    public function update(Request $request, Question $question): RedirectResponse
    {
        $question->update($this->validatedQuestion($request, $question));

        return redirect()->route('admin.questions.index')->with('status', 'Question updated.');
    }

    public function toggleStatus(Question $question): RedirectResponse
    {
        $question->update(['is_active' => ! $question->is_active]);

        return redirect()->route('admin.questions.index')->with('status', 'Question visibility updated.');
    }

    /** @return array{category:string,question_text:string,key_terms:array<int,string>,is_behavioral:bool,is_active:bool} */
    private function validatedQuestion(Request $request, ?Question $question = null): array
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:80'],
            'question_text' => [
                'required', 'string', 'max:500',
                Rule::unique('questions', 'question_text')
                    ->where(fn ($query) => $query->where('category', $request->input('category')))
                    ->ignore($question?->id),
            ],
            'key_terms' => ['required', 'string', 'max:1000'],
            'is_behavioral' => ['nullable', 'boolean'],
        ]);
        $terms = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\r\n]+/', $data['key_terms']) ?: []))));
        if (count($terms) < 1 || count($terms) > 15) {
            throw ValidationException::withMessages(['key_terms' => 'Enter between 1 and 15 key terms.']);
        }

        return [
            'category' => $data['category'],
            'question_text' => $data['question_text'],
            'key_terms' => $terms,
            'is_behavioral' => (bool) ($data['is_behavioral'] ?? false),
            'is_active' => true,
        ];
    }
}
