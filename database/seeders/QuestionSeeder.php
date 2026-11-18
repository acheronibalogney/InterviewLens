<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            ['Getting started', 'Tell me about yourself.', ['experience', 'skills', 'background', 'goal', 'role'], false],
            ['Getting started', 'What interests you about this role?', ['role', 'company', 'skills', 'contribute', 'interest'], false],
            ['Getting started', 'Why should we hire you?', ['experience', 'skills', 'team', 'results', 'contribute'], false],
            ['Behavioral', 'Tell me about a challenge you faced and how you handled it.', ['challenge', 'problem', 'team', 'solution', 'result'], true],
            ['Behavioral', 'Describe a time you worked with a team to reach a goal.', ['team', 'goal', 'collaborate', 'communication', 'result'], true],
            ['Behavioral', 'Tell me about a mistake you made and what you learned.', ['mistake', 'learned', 'improve', 'feedback', 'change'], true],
            ['Behavioral', 'Describe a time you had to manage competing priorities.', ['priorities', 'organized', 'deadline', 'planned', 'completed'], true],
            ['Strengths & goals', 'What is one strength you would bring to this role?', ['strength', 'example', 'skills', 'role', 'contribute'], false],
            ['Strengths & goals', 'What skill are you working to improve?', ['skill', 'improve', 'feedback', 'practice', 'progress'], false],
            ['Strengths & goals', 'Where would you like to grow in the next few years?', ['goal', 'learn', 'grow', 'career', 'skills'], false],
        ];
        foreach ($questions as [$category, $question, $keywords, $behavioral]) {
            Question::query()->updateOrCreate(['category' => $category, 'question' => $question], ['keywords' => $keywords, 'is_behavioral' => $behavioral, 'is_active' => true]);
        }
    }
}
