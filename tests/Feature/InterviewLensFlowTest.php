<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\User;
use App\Services\AnswerEvaluator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
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
        $this->fakeGeminiReview();
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
        $this->get(route('results.show', $attempt))->assertOk()->assertSee('Your feedback')->assertSee('YOUR PRACTICE SCORE')->assertSee('A FEW RECOMMENDED NEXT READS')->assertSee('FROM YOUR AI INTERVIEWER')->assertSee('data-results-page', false);
        $this->get(route('history'))->assertOk()->assertSee($question->question_text);

        Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('x-goog-api-key', 'test-gemini-key')
            && str_contains($request->url(), '/models/gemini-3.8-flash:generateContent')
            && $request['generationConfig']['thinkingConfig']['thinkingLevel'] === 'low'
            && ! isset($request['generationConfig']['temperature'])
            && $request['generationConfig']['responseJsonSchema']['required'] === ['interviewer_response', 'follow_up_question', 'criteria']);
    }

    public function test_ajax_practice_submission_saves_feedback_and_returns_the_results_url(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $question = Question::query()->firstOrFail();
        $this->fakeGeminiReview();

        $response = $this->actingAs($user)->postJson(route('evaluate'), [
            'question_id' => $question->id,
            'answer' => 'This is a detailed example answer that is long enough for the interview flow.',
        ]);

        $attempt = Attempt::query()->sole();
        $response->assertCreated()->assertJsonPath('redirect_url', route('results.show', $attempt));
        $this->assertDatabaseHas('attempts', ['id' => $attempt->id, 'user_id' => $user->id]);
        $this->assertSame(4, $attempt->feedbackItems()->count());
    }

    public function test_star_scores_are_capped_when_the_answer_lacks_two_component_evidence(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $question = Question::query()->firstOrFail();
        $this->fakeGeminiReview();

        $response = $this->actingAs($user)->postJson(route('evaluate'), [
            'question_id' => $question->id,
            'answer' => 'When the email system failed, records were reviewed and tests were completed. As a result, delivery improved and users received messages.',
        ]);

        $attempt = Attempt::query()->sole();
        $response->assertCreated()->assertJsonPath('redirect_url', route('results.show', $attempt));
        $this->assertSame(['Situation', 'Result'], $attempt->star_components);
        $this->assertSame(58, $attempt->total_score);
        $this->assertSame(5, $attempt->feedbackItems()->where('criterion', 'task')->value('criterion_score'));
        $this->assertSame(5, $attempt->feedbackItems()->where('criterion', 'action')->value('criterion_score'));
        $this->assertStringContainsString('capped at 5/25', $attempt->feedbackItems()->where('criterion', 'task')->value('tip'));
    }

    public function test_ajax_gemini_failure_returns_a_message_without_saving_a_partial_attempt(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $question = Question::query()->firstOrFail();
        config()->set('services.gemini.key', 'test-gemini-key');
        config()->set('services.gemini.model', 'gemini-3.8-flash');
        config()->set('services.gemini.fallback_model', null);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503),
        ]);
        Sleep::fake();

        $response = $this->actingAs($user)->postJson(route('evaluate'), [
            'question_id' => $question->id,
            'answer' => 'This is a long enough example answer for the interview practice flow.',
        ]);

        $response->assertStatus(503)->assertJsonPath('message', 'Gemini is temporarily overloaded (HTTP 503). The request was retried; please wait a moment and try again.');
        $this->assertDatabaseCount('attempts', 0);
        Http::assertSentCount(2);
    }

    public function test_gemini_zero_star_score_is_rejected_without_saving_an_attempt(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $question = Question::query()->firstOrFail();
        config()->set('services.gemini.key', 'test-gemini-key');
        config()->set('services.gemini.model', 'gemini-3.8-flash');
        config()->set('services.gemini.fallback_model', null);
        Http::preventStrayRequests();
        $payload = $this->successfulGeminiPayload();
        $review = json_decode($payload['candidates'][0]['content']['parts'][0]['text'], true, 512, JSON_THROW_ON_ERROR);
        $review['criteria'][0]['score'] = 0;
        $payload['candidates'][0]['content']['parts'][0]['text'] = json_encode($review, JSON_THROW_ON_ERROR);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($payload),
        ]);

        $response = $this->actingAs($user)->postJson(route('evaluate'), [
            'question_id' => $question->id,
            'answer' => 'This is a long enough example answer for the interview practice flow.',
        ]);

        $response->assertStatus(503)->assertJsonPath('message', 'Gemini returned incomplete STAR feedback. Please try again.');
        $this->assertDatabaseCount('attempts', 0);
        Http::assertSentCount(1);
    }

    public function test_practice_page_includes_an_accessible_loading_progress_indicator(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs(User::factory()->create())->get(route('practice'))
            ->assertOk()
            ->assertSee('id="submission-progress"', false)
            ->assertSee('role="progressbar"', false)
            ->assertSee('id="submission-status"', false);
    }

    public function test_gemini_project_billing_error_is_explained_without_saving_the_attempt(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $question = Question::query()->firstOrFail();
        config()->set('services.gemini.key', 'test-gemini-key');
        config()->set('services.gemini.model', 'gemini-3.8-flash');
        config()->set('services.gemini.fallback_model', null);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403),
        ]);

        $this->actingAs($user)->from(route('practice'))->post(route('evaluate'), [
            'question_id' => $question->id,
            'answer' => 'This is a long enough example answer for the interview practice flow.',
        ])->assertRedirect(route('practice'))->assertSessionHasErrors([
            'answer' => 'Gemini accepted the API key but denied generation for its Google AI Studio project. Check project access and billing.',
        ]);

        $this->assertDatabaseCount('attempts', 0);
    }

    public function test_temporary_gemini_503_is_retried_and_the_interview_completes_when_it_recovers(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $question = Question::query()->firstOrFail();
        config()->set('services.gemini.key', 'test-gemini-key');
        config()->set('services.gemini.model', 'gemini-3.8-flash');
        config()->set('services.gemini.fallback_model', null);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['status' => 'UNAVAILABLE']], 503)
                ->push($this->successfulGeminiPayload()),
        ]);
        Sleep::fake();

        $response = $this->actingAs($user)->post(route('evaluate'), [
            'question_id' => $question->id,
            'answer' => 'This is a detailed example answer that is long enough for the interview flow.',
        ]);

        $attempt = Attempt::query()->sole();
        $response->assertRedirect(route('results.show', $attempt));
        $this->assertDatabaseCount('attempts', 1);
        Http::assertSentCount(2);
        Sleep::assertSequence([Sleep::for(500)->milliseconds()]);
    }

    public function test_gemini_503_uses_the_fallback_model_and_completes_the_interview(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $question = Question::query()->firstOrFail();
        config()->set('services.gemini.key', 'test-gemini-key');
        config()->set('services.gemini.model', 'gemini-3.8-flash');
        config()->set('services.gemini.fallback_model', 'gemini-3.5-flash-lite');
        Http::preventStrayRequests();
        $successfulPayload = $this->successfulGeminiPayload();
        Http::fake([
            'generativelanguage.googleapis.com/*' => function (HttpRequest $request) use ($successfulPayload) {
                if (str_contains($request->url(), '/models/gemini-3.8-flash:generateContent')) {
                    return Http::response(['error' => ['status' => 'UNAVAILABLE']], 503);
                }

                return Http::response($successfulPayload);
            },
        ]);
        Sleep::fake();

        $response = $this->actingAs($user)->post(route('evaluate'), [
            'question_id' => $question->id,
            'answer' => 'This is a detailed example answer that is long enough for the interview flow.',
        ]);

        $attempt = Attempt::query()->sole();
        $response->assertRedirect(route('results.show', $attempt));
        $this->assertDatabaseCount('attempts', 1);
        Http::assertSentCount(3);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/models/gemini-3.8-flash:generateContent'));
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/models/gemini-3.5-flash-lite:generateContent'));
        Sleep::assertSequence([Sleep::for(500)->milliseconds()]);
    }

    public function test_gemini_rate_limit_uses_the_fallback_model_when_available(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $question = Question::query()->firstOrFail();
        config()->set('services.gemini.key', 'test-gemini-key');
        config()->set('services.gemini.model', 'gemini-3.8-flash');
        config()->set('services.gemini.fallback_model', 'gemini-3.5-flash-lite');
        Http::preventStrayRequests();
        $successfulPayload = $this->successfulGeminiPayload();
        Http::fake([
            'generativelanguage.googleapis.com/*' => function (HttpRequest $request) use ($successfulPayload) {
                if (str_contains($request->url(), '/models/gemini-3.8-flash:generateContent')) {
                    return Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429);
                }

                return Http::response($successfulPayload);
            },
        ]);

        $response = $this->actingAs($user)->post(route('evaluate'), [
            'question_id' => $question->id,
            'answer' => 'This is a detailed example answer that is long enough for the interview flow.',
        ]);

        $attempt = Attempt::query()->sole();
        $response->assertRedirect(route('results.show', $attempt));
        $this->assertDatabaseCount('attempts', 1);
        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/models/gemini-3.5-flash-lite:generateContent'));
    }

    public function test_gemini_connection_failure_uses_the_fallback_model(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $question = Question::query()->firstOrFail();
        config()->set('services.gemini.key', 'test-gemini-key');
        config()->set('services.gemini.model', 'gemini-3.8-flash');
        config()->set('services.gemini.fallback_model', 'gemini-3.5-flash-lite');
        Http::preventStrayRequests();
        $successfulPayload = $this->successfulGeminiPayload();
        Http::fake([
            'generativelanguage.googleapis.com/*' => function (HttpRequest $request) use ($successfulPayload) {
                if (str_contains($request->url(), '/models/gemini-3.8-flash:generateContent')) {
                    return Http::failedConnection();
                }

                return Http::response($successfulPayload);
            },
        ]);

        $response = $this->actingAs($user)->post(route('evaluate'), [
            'question_id' => $question->id,
            'answer' => 'This is a detailed example answer that is long enough for the interview flow.',
        ]);

        $attempt = Attempt::query()->sole();
        $response->assertRedirect(route('results.show', $attempt));
        $this->assertDatabaseCount('attempts', 1);
        Http::assertSentCount(3);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/models/gemini-3.5-flash-lite:generateContent'));
    }

    public function test_gemini_503_after_retries_is_explained_without_saving_the_attempt(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $question = Question::query()->firstOrFail();
        config()->set('services.gemini.key', 'test-gemini-key');
        config()->set('services.gemini.model', 'gemini-3.8-flash');
        config()->set('services.gemini.fallback_model', null);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503),
        ]);
        Sleep::fake();

        $this->actingAs($user)->from(route('practice'))->post(route('evaluate'), [
            'question_id' => $question->id,
            'answer' => 'This is a long enough example answer for the interview practice flow.',
        ])->assertRedirect(route('practice'))->assertSessionHasErrors([
            'answer' => 'Gemini is temporarily overloaded (HTTP 503). The request was retried; please wait a moment and try again.',
        ]);

        $this->assertDatabaseCount('attempts', 0);
        Http::assertSentCount(2);
        Sleep::assertSequence([Sleep::for(500)->milliseconds()]);
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

    private function fakeGeminiReview(): void
    {
        config()->set('services.gemini.key', 'test-gemini-key');
        config()->set('services.gemini.model', 'gemini-3.8-flash');
        config()->set('services.gemini.fallback_model', null);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->successfulGeminiPayload()),
        ]);
    }

    /** @return array{candidates: array<int, array{content: array{parts: array<int, array{text: string}>}}>}} */
    private function successfulGeminiPayload(): array
    {
        return [
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode([
                            'interviewer_response' => 'Thank you for describing how you restored access.',
                            'follow_up_question' => 'How did you confirm the restore worked?',
                            'criteria' => [
                                ['criterion' => 'Situation', 'score' => 23, 'feedback' => 'You explain the team problem.'],
                                ['criterion' => 'Task', 'score' => 22, 'feedback' => 'Your responsibility is clear.'],
                                ['criterion' => 'Action', 'score' => 24, 'feedback' => 'You describe concrete steps.'],
                                ['criterion' => 'Result', 'score' => 25, 'feedback' => 'You state the timely outcome.'],
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ],
            ]],
        ];
    }
}
