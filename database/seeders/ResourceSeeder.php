<?php

namespace Database\Seeders;

use App\Models\Resource;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            ['Use the STAR method to answer interview questions', 'A practical guide to structuring a clear example with Situation, Task, Action, and Result.', 'Answer structure', 'https://www.indeed.com/career-advice/interviewing/how-to-use-the-star-interview-response-technique', ['star_structure']],
            ['How to prepare for a job interview', 'Simple preparation steps for researching a role and practicing your responses.', 'Interview preparation', 'https://www.indeed.com/career-advice/interviewing/how-to-prepare-for-an-interview', ['answer_length']],
            ['Common interview questions and sample answers', 'Explore frequently asked questions and ways to shape your own examples.', 'Practice prompts', 'https://www.indeed.com/career-advice/interviewing/interview-questions-and-answers', ['keyword_relevance']],
            ['Build confidence in your communication', 'Guidance on communicating clearly, listening well, and presenting your ideas.', 'Communication', 'https://www.coursera.org/articles/communication-skills', ['filler_words']],
        ];
        foreach ($resources as [$title, $description, $category, $url, $skills]) {
            Resource::query()->updateOrCreate(['title' => $title], compact('description', 'category', 'url', 'skills'));
        }
    }
}
