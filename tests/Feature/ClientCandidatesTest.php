<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\ClientCandidate;
use App\Models\ClientPaymentMethod;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobInterview;
use App\Models\LongTermJobReview;
use App\Models\Payment;
use App\Models\Type;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stripe\PaymentIntent;
use Tests\TestCase;

class ClientCandidatesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_candidates_tab_returns_resource_cards_with_roles(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $nannyType = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'service']);
        $managerType = Type::create(['agency_id' => $agency->id, 'name' => 'House Manager', 'type' => 'service']);

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Kelsey',
            'last_name' => 'Brooks',
            'email' => 'kelsey@example.com',
            'city' => 'Miami',
            'province' => 'New York',
            'country' => 'USA',
            'years_of_experience' => '5-10',
            'type_id' => [$nannyType->id, $managerType->id],
        ]);

        $job = $this->createLongTermJob($agency, $client);

        LongTermJobApplication::create([
            'long_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'agency_id' => $agency->id,
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/candidates?tab=new');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Kelsey Brooks')
            ->assertJsonPath('data.0.location', 'Miami, New York, USA')
            ->assertJsonPath('data.0.experience_years', '5-10')
            ->assertJsonPath('data.0.roles', ['Nanny', 'House Manager'])
            ->assertJsonPath('data.0.actions.can_view_profile', true);
    }

    public function test_previous_candidates_tab_returns_candidates_from_completed_jobs(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $worked = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Maria',
            'last_name' => 'Gomez',
            'email' => 'maria@example.com',
        ]);

        $applicant = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Nina',
            'last_name' => 'Park',
            'email' => 'nina@example.com',
        ]);

        // A completed placement makes "Maria" a previous candidate, while an
        // application on an open job only makes "Nina" a new candidate.
        $this->createLongTermJob($agency, $client, $worked, 'completed');

        $openJob = $this->createLongTermJob($agency, $client);
        LongTermJobApplication::create([
            'long_term_job_id' => $openJob->id,
            'candidate_id' => $applicant->id,
            'agency_id' => $agency->id,
            'status' => 'pending',
        ]);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/candidates?tab=previous')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Maria Gomez');
    }

    public function test_candidate_detail_returns_profile_tabs_and_reviews(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Kristin',
            'last_name' => 'Ben',
            'email' => 'kristin@example.com',
            'nationality' => 'American',
        ]);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Candidate Registration',
            'slug' => 'candidate-registration',
            'entity' => 'candidate',
            'application_type' => 'registration',
            'user_type' => 'candidate',
            'status' => true,
            'schema' => [
                'blocks' => [[
                    'name' => 'Professional Information',
                    'sections' => [[
                        'name' => 'Professional Information',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Hours per Week', 'name' => 'hours_per_week'],
                            ['type' => 'text_box', 'label' => 'Years of Experience', 'name' => 'years_of_experience'],
                        ],
                    ]],
                ]],
            ],
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $candidate->id,
            'entity_type' => 'candidate',
            'data' => ['hours_per_week' => '40', 'years_of_experience' => '10+'],
        ]);

        $job = $this->createLongTermJob($agency, $client, $candidate, 'completed');

        LongTermJobReview::create([
            'long_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'agency_id' => $agency->id,
            'rating' => 5,
            'review' => 'Excellent with the kids.',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/candidates/{$candidate->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.candidate.header.name', 'Kristin Ben')
            ->assertJsonPath('data.candidate.header.rating.average', 5)
            ->assertJsonPath('data.candidate.header.rating.count', 1)
            ->assertJsonPath('data.candidate.blocks.0.name', 'Basic Information')
            ->assertJsonPath('data.candidate.blocks.0.sections.0.fields.0.key', 'first_name')
            ->assertJsonPath('data.candidate.blocks.0.sections.0.fields.0.value', 'Kristin')
            ->assertJsonPath('data.candidate.blocks.1.sections.0.fields.0.value', '40')
            ->assertJsonPath('data.candidate.blocks.1.sections.0.fields.1.value', '10+')
            ->assertJsonPath('data.reviews.count', 1)
            ->assertJsonPath('data.reviews.my_review.rating', 5)
            ->assertJsonPath('data.actions.can_hire_request', true);
    }

    public function test_client_can_submit_candidate_review(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jana',
            'last_name' => 'Smith',
            'email' => 'jana@example.com',
        ]);

        $this->createLongTermJob($agency, $client, $candidate, 'completed');

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/reviews", [
                'rating' => 4,
                'review' => 'Reliable and punctual.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.review', 'Reliable and punctual.')
            ->assertJsonPath('data.reviewer.name', 'Alex Morrison');

        $this->assertDatabaseHas('long_term_job_reviews', [
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'rating' => 4,
        ]);
    }

    public function test_hire_request_validates_job_type(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Pat',
            'last_name' => 'Doe',
            'email' => 'pat@example.com',
        ]);

        // Invalid job type -> 422
        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/hire-request", [
                'job_type' => 'weekly',
            ])
            ->assertStatus(422);

        // Long-term hires are routed through the interview flow first -> 422
        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/hire-request", [
                'job_type' => 'long-term',
            ])
            ->assertStatus(422);
    }

    public function test_interview_request_persists_long_term_interview_and_links_candidate(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Darlene',
            'last_name' => 'Robertson',
            'email' => 'darlene@example.com',
        ]);

        $date = now()->addDays(5)->toDateString();

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/interview-request", [
                'job_type' => 'long-term',
                'interview_type' => 'zoom',
                'description' => 'Intro call before posting the role.',
                'scheduled_date' => $date,
                'available_from' => '10:00',
                'available_to' => '11:00',
            ]);

        // A profile interview-request now waits on the candidate before the
        // agency confirms it, so it is persisted as "requested" (not scheduled).
        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.interview_type', 'zoom')
            ->assertJsonPath('data.candidate.id', $candidate->id);

        $this->assertDatabaseHas('long_term_job_interviews', [
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'agency_id' => $agency->id,
            'long_term_job_id' => null,
            'long_term_job_application_id' => null,
            'status' => 'requested',
            'interview_type' => 'zoom',
        ]);

        $this->assertDatabaseHas('client_candidates', [
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'status' => 'interview_scheduled',
        ]);
    }

    public function test_interview_request_rejects_short_term(): void
    {
        [$agency, $user] = $this->createClientScenario();

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Pat',
            'last_name' => 'Doe',
            'email' => 'pat@example.com',
        ]);

        // Short-term needs no interview fields; it is rejected by the controller
        // (not by validation) so the client is routed to the hire request flow.
        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/interview-request", [
                'job_type' => 'short-term',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only long-term hires schedule an interview. Short-term hires use the hire request flow.');

        // Long-term, however, must supply the interview fields.
        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/interview-request", [
                'job_type' => 'long-term',
            ])
            ->assertStatus(422)
            ->assertJsonStructure([
                'data' => ['interview_type', 'scheduled_date', 'available_from', 'available_to'],
            ]);
    }

    public function test_scheduled_interview_appears_in_client_interviews_list(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Darlene',
            'last_name' => 'Robertson',
            'email' => 'darlene@example.com',
        ]);

        LongTermJobInterview::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'scheduled_date' => now()->addDays(5)->toDateString(),
            'available_from' => '10:00',
            'available_to' => '11:00',
            'interview_type' => 'zoom',
            'status' => 'scheduled',
        ]);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/interviews?view=list')
            ->assertOk()
            ->assertJsonPath('data.next_interview.candidate.id', $candidate->id)
            ->assertJsonPath('data.interviews.data.0.candidate.id', $candidate->id);
    }

    public function test_update_link_sets_status_notes_and_card_reflects_it(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Kelsey',
            'last_name' => 'Brooks',
            'email' => 'kelsey@example.com',
            'city' => 'Miami',
            'province' => 'New York',
            'country' => 'USA',
        ]);

        $job = $this->createLongTermJob($agency, $client);

        LongTermJobApplication::create([
            'long_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'agency_id' => $agency->id,
            'status' => 'pending',
        ]);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson("/api/client/candidates/{$candidate->id}/link", [
                'status' => 'hired',
                'notes' => 'Kelsey is targeting an August start date.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'hired')
            ->assertJsonPath('data.notes', 'Kelsey is targeting an August start date.');

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/candidates?tab=new')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'hired')
            ->assertJsonPath('data.0.status_label', 'Hired')
            ->assertJsonPath('data.0.notes', 'Kelsey is targeting an August start date.')
            ->assertJsonPath('data.0.actions.can_extend_offer', true);

        $this->assertDatabaseHas('client_candidates', [
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'status' => 'hired',
        ]);
    }

    public function test_short_term_hire_request_links_candidate(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jana',
            'last_name' => 'Smith',
            'email' => 'jana@example.com',
        ]);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/hire-request", [
                'job_type' => 'short-term',
                'title' => 'Weekend Babysitter',
                'compensation_amount' => 25,
                'job_address' => '26 Berkshire Ave.',
                'home_city' => 'Miami',
                'home_province' => 'New York',
                'home_postal_code' => '08401',
                'country' => 'USA',
                'note' => 'Looking forward to working together.',
                'dates' => [
                    ['booking_date' => now()->addDays(7)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00'],
                ],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('client_candidates', [
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'status' => 'hired',
        ]);

        // No agency fee configured -> a $0 pending payment row still captures the note.
        $this->assertDatabaseHas('payments', [
            'client_id' => $client->id,
            'currency' => 'usd',
            'status' => 'pending',
            'note' => 'Looking forward to working together.',
        ]);

        $this->assertInstanceOf(
            ClientCandidate::class,
            ClientCandidate::where('client_id', $client->id)->where('candidate_id', $candidate->id)->first()
        );
    }

    public function test_hire_request_requires_payment_when_agency_charges_fee(): void
    {
        [$agency, $user] = $this->createClientScenario();
        $this->enableAgencyPayments($agency);

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jana',
            'last_name' => 'Smith',
            'email' => 'jana@example.com',
        ]);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/hire-request", $this->shortTermHirePayload())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Payment information is required to send this hire request.');
    }

    public function test_short_term_hire_request_charges_agency_fee_and_saves_card(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();
        $this->enableAgencyPayments($agency);

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jana',
            'last_name' => 'Smith',
            'email' => 'jana@example.com',
        ]);

        $this->mock(StripeService::class, function ($mock) use ($agency, $client): void {
            $mock->shouldReceive('ensureCustomer')->once()->andReturn('cus_test');
            $mock->shouldReceive('createJobPaymentIntent')->once()->andReturn(PaymentIntent::constructFrom(['id' => 'pi_test']));
            $mock->shouldReceive('confirmPaymentIntent')->once()->andReturn(PaymentIntent::constructFrom(['id' => 'pi_test', 'status' => 'succeeded']));
            $mock->shouldReceive('storePaymentMethod')->once()->andReturnUsing(
                fn () => ClientPaymentMethod::create([
                    'agency_id' => $agency->id,
                    'client_id' => $client->id,
                    'stripe_payment_method_id' => 'pm_test',
                    'cardholder_name' => 'Alex Morrison',
                    'brand' => 'visa',
                    'last4' => '4242',
                    'exp_month' => 12,
                    'exp_year' => 2030,
                    'is_default' => true,
                ])
            );
        });

        $payload = array_merge($this->shortTermHirePayload(), [
            'payment_method_id' => 'pm_test',
            'cardholder_name' => 'Alex Morrison',
            'billing_country' => 'US',
            'billing_postal_code' => '30030',
            'save_payment_method' => true,
        ]);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/hire-request", $payload)
            ->assertCreated();

        $this->assertDatabaseHas('payments', [
            'client_id' => $client->id,
            'amount' => 40.00,
            'currency' => 'usd',
            'status' => 'succeeded',
            'stripe_payment_intent_id' => 'pi_test',
            'cardholder_name' => 'Alex Morrison',
            'billing_country' => 'US',
            'billing_postal_code' => '30030',
        ]);

        $this->assertDatabaseHas('client_payment_methods', [
            'client_id' => $client->id,
            'stripe_payment_method_id' => 'pm_test',
            'last4' => '4242',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('short_term_jobs', [
            'client_id' => $client->id,
            'stripe_payment_intent_id' => 'pi_test',
        ]);
    }

    public function test_client_can_list_saved_cards(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        ClientPaymentMethod::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'stripe_payment_method_id' => 'pm_default',
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => true,
        ]);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/payment-methods')
            ->assertOk()
            ->assertJsonPath('data.0.last4', '4242')
            ->assertJsonPath('data.0.brand', 'visa')
            ->assertJsonPath('data.0.is_default', true);
    }

    public function test_candidate_sees_directly_requested_interview(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        Role::create(['name' => 'candidate', 'guard_name' => 'web']);

        $candidateUser = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => 'darlene@example.com',
        ]);
        $candidateUser->assignRole('candidate');

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Darlene',
            'last_name' => 'Robertson',
            'email' => 'darlene@example.com',
        ]);

        $interview = LongTermJobInterview::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'scheduled_date' => now()->addDays(5)->toDateString(),
            'available_from' => '10:00',
            'available_to' => '11:00',
            'interview_type' => 'zoom',
            'status' => 'scheduled',
        ]);

        $this
            ->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/interviews?view=list')
            ->assertOk()
            ->assertJsonPath('data.next_interview.id', $interview->id)
            ->assertJsonPath('data.interviews.data.0.id', $interview->id)
            ->assertJsonPath('data.interviews.data.0.client.id', $client->id);
    }

    private function enableAgencyPayments(Agency $agency): void
    {
        $agency->update([
            'stripe_publishable_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_123',
            'short_term_payment_required' => true,
            'short_term_job_fee' => 40,
            'short_term_job_fee_currency' => 'usd',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shortTermHirePayload(): array
    {
        return [
            'job_type' => 'short-term',
            'title' => 'Weekend Babysitter',
            'compensation_amount' => 25,
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Miami',
            'home_province' => 'New York',
            'home_postal_code' => '08401',
            'country' => 'USA',
            'note' => 'Looking forward to working together.',
            'dates' => [
                ['booking_date' => now()->addDays(7)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00'],
            ],
        ];
    }

    private function createLongTermJob(Agency $agency, Client $client, ?Candidate $candidate = null, string $status = 'marketplace'): LongTermJob
    {
        return LongTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate?->id,
            'title' => 'Full Time Nanny',
            'description' => 'Long-term nanny role.',
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Miami',
            'home_province' => 'New York',
            'home_postal_code' => '08401',
            'country' => 'USA',
            'start_date' => '2026-01-01',
            'compensation_amount' => 35,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => $status,
        ]);
    }

    /**
     * @return array{0: Agency, 1: User, 2: Client}
     */
    private function createClientScenario(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $user = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        Role::create([
            'name' => 'client',
            'guard_name' => 'web',
        ]);

        $user->assignRole('client');

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Alex',
            'last_name' => 'Morrison',
            'email' => $user->email,
        ]);

        return [$agency, $user, $client];
    }
}
