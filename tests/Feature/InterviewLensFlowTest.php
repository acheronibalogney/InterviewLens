<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\User;
use App\Services\AnswerEvaluator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InterviewLensFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_can_open_sign_in_and_registration_pages(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Good to have you back.');
        $this->get(route('register'))->assertOk()->assertSee('Make room to practice.');
    }

    public function test_guest_is_redirected_to_sign_in_before_practice(): void
    {
        $this->get(route('practice'))->assertRedirect(route('login'));
    }

    public function test_job_seeker_can_register_practice_and_review_four_criterion_feedback_rows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->post(route('register.store'), [
            'full_name' => 'Alex Morgan', 'email' => 'alex@example.com',
            'password' => 'practice-safe-password', 'password_confirmation' => 'practice-safe-password',
            'terms' => 'on', 'role' => 'administrator',
        ])->assertRedirect(route('practice'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'alex@example.com', 'full_name' => 'Alex Morgan', 'role' => 'job_seeker']);
        $question = Question::query()->where('question_text', 'Describe a time you solved a problem.')->firstOrFail();
        $answer = 'When our team faced a problem, my task was to finish the project. I organized the work, created a plan, and communicated with everyone. As a result, we completed the solution and achieved our goal.';

        $response = $this->post(route('evaluate'), ['question_id' => $question->id, 'answer' => $answer]);
        $attempt = Attempt::query()->sole();
        $response->assertRedirect(route('results.show', $attempt));
        $this->assertSame($answer, $attempt->answer_text);
        $this->assertSame(4, $attempt->feedbackItems()->count());
        $this->assertSame($attempt->total_score, $attempt->feedbackItems()->sum('criterion_score'));
        $this->get(route('results.show', $attempt))->assertOk()->assertSee('Your feedback')->assertSee('YOUR PRACTICE SCORE')->assertSee('A FEW RECOMMENDED NEXT READS');
        $this->get(route('history'))->assertOk()->assertSee($question->question_text);
    }

    public function test_job_seeker_cannot_read_another_users_attempt(): void
    {
        $this->seed(DatabaseSeeder::class);
        $question = Question::query()->firstOrFail();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $attempt = Attempt::query()->create([
            'user_id' => $owner->id, 'question_id' => $question->id, 'answer_text' => str_repeat('Example answer ', 10),
            'total_score' => 50, 'word_count' => 20, 'filler_count' => 0, 'star_components' => [], 'matched_keywords' => [], 'feedback' => [],
        ]);

        $this->actingAs($otherUser)->get(route('results.show', $attempt))->assertNotFound();
    }

    public function test_registered_job_seeker_can_sign_in_with_case_insensitive_email(): void
    {
        $user = User::factory()->create(['email' => 'jules@example.com']);

        $this->post(route('login.store'), ['email' => 'JULES@EXAMPLE.COM', 'password' => 'password'])
            ->assertRedirect(route('practice'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_answer_under_twenty_characters_is_rejected_without_saving(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $question = Question::query()->firstOrFail();

        $this->actingAs($user)->post(route('evaluate'), ['question_id' => $question->id, 'answer' => 'Too short'])
            ->assertSessionHasErrors(['answer' => 'The answer field must be at least 20 characters.']);

        $this->assertDatabaseCount('attempts', 0);
    }

    public function test_job_seeker_is_denied_question_bank_access(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.questions.index'))->assertForbidden();
    }

    public function test_administrator_can_add_and_deactivate_question_without_deleting_it(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->get(route('admin.questions.index'))->assertOk()->assertSee('Question bank');
        $this->actingAs($admin)->post(route('admin.questions.store'), [
            'category' => 'Behavioral',
            'question_text' => 'Describe a time you solved a problem.',
            'key_terms' => "problem\nsolution\nresult",
            'is_behavioral' => '1',
        ])->assertRedirect(route('admin.questions.index'));

        $question = Question::query()->where('question_text', 'Describe a time you solved a problem.')->firstOrFail();
        $this->assertSame(['problem', 'solution', 'result'], $question->key_terms);
        $this->put(route('admin.questions.update', $question), [
            'category' => 'Behavioral',
            'question_text' => 'Describe how you solved a problem.',
            'key_terms' => 'problem, solution, result',
            'is_behavioral' => '1',
        ])->assertRedirect(route('admin.questions.index'));
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'question_text' => 'Describe how you solved a problem.']);
        $this->patch(route('admin.questions.status', $question))->assertRedirect(route('admin.questions.index'));
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'is_active' => false]);
    }

    public function test_same_answer_and_question_produce_same_weighted_score(): void
    {
        $this->seed(DatabaseSeeder::class);
        $question = Question::query()->where('question_text', 'Describe a time you solved a problem.')->firstOrFail();
        $answer = 'When I had a problem, my task was to help. I created a solution and completed it with the team. As a result, we improved the outcome.';
        $evaluator = app(AnswerEvaluator::class);

        $first = $evaluator->evaluate($answer, $question);
        $second = $evaluator->evaluate($answer, $question);

        $this->assertSame($first['total_score'], $second['total_score']);
        $this->assertSame($first['total_score'], array_sum(array_column($first['feedback'], 'criterion_score')));
    }
}
