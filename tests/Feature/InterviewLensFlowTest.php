<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Question;
use App\Models\User;
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

    public function test_job_seeker_can_register_practice_and_review_feedback(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->post(route('register.store'), [
            'name' => 'Alex Morgan', 'email' => 'alex@example.com',
            'password' => 'practice-safe-password', 'password_confirmation' => 'practice-safe-password',
            'terms' => 'on',
        ])->assertRedirect(route('practice'));

        $this->assertAuthenticated();
        $question = Question::query()->where('question', 'Tell me about a challenge you faced and how you handled it.')->firstOrFail();
        $answer = 'During my placement project, our team had to finish an important report under a short deadline. I was responsible for organizing the work. I created a task plan, communicated with each teammate, and checked our progress every day. As a result, we completed the report on time and I learned how clear planning helps a team achieve its goal.';

        $response = $this->post(route('evaluate'), ['question_id' => $question->id, 'answer' => $answer]);
        $evaluation = Evaluation::query()->sole();
        $response->assertRedirect(route('results.show', $evaluation));
        $this->assertSame($answer, $evaluation->answer);
        $this->get(route('results.show', $evaluation))->assertOk()->assertSee('Your feedback')->assertSee('YOUR PRACTICE SCORE')->assertSee('A FEW RECOMMENDED NEXT READS');
        $this->get(route('history'))->assertOk()->assertSee($question->question);
    }

    public function test_job_seeker_cannot_read_another_users_evaluation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $question = Question::query()->firstOrFail();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $evaluation = Evaluation::query()->create([
            'user_id' => $owner->id, 'question_id' => $question->id, 'answer' => str_repeat('Example answer ', 10),
            'score' => 50, 'word_count' => 20, 'filler_count' => 0, 'star_components' => [], 'matched_keywords' => [], 'feedback' => [],
        ]);

        $this->actingAs($otherUser)->get(route('results.show', $evaluation))->assertNotFound();
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

        $this->assertDatabaseCount('evaluations', 0);
    }
}
