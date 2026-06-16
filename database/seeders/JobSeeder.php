<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\CandidateClientReport;
use App\Models\CandidateJobRequest;
use App\Models\Client;
use App\Models\JobMessage;
use App\Models\Location;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobAttendance;
use App\Models\LongTermJobChild;
use App\Models\LongTermJobInterview;
use App\Models\LongTermJobNannyPayment;
use App\Models\LongTermJobRefundRequest;
use App\Models\LongTermJobReview;
use App\Models\LongTermJobSchedule;
use App\Models\Payment;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobAttendance;
use App\Models\ShortTermJobChild;
use App\Models\ShortTermJobDate;
use App\Models\ShortTermJobReview;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seeds the entire job lifecycle (short & long term) plus every related
 * record — children, schedules, dates, payments, attendance, reviews,
 * messages, applications, interviews, job requests, reports and refunds.
 *
 * It reads the clients and candidates produced by ClientSeeder and
 * CandidateSeeder so the whole graph is internally consistent: every client
 * and every candidate is wired into real jobs.
 */
class JobSeeder extends Seeder
{
    /** @var Collection<int, Client> */
    private Collection $clients;

    /** @var Collection<int, Candidate> */
    private Collection $candidates;

    /** @var Collection<int, Location> */
    private Collection $locations;

    /** @var array<string, User> */
    private array $usersByEmail = [];

    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        $this->clients = Client::where('agency_id', $agency->id)->orderBy('id')->get()->values();
        $this->candidates = Candidate::where('agency_id', $agency->id)->orderBy('id')->get()->values();
        $this->locations = Location::where('agency_id', $agency->id)->orderBy('id')->get()->values();

        if ($this->clients->isEmpty() || $this->candidates->isEmpty()) {
            $this->command->error('Clients/candidates not found. Run ClientSeeder & CandidateSeeder first.');

            return;
        }

        $emails = $this->clients->pluck('email')->merge($this->candidates->pluck('email'));
        $this->usersByEmail = User::whereIn('email', $emails)->get()->keyBy('email')->all();

        $shortTermJobs = $this->seedShortTermJobs($agency);
        $longTermJobs = $this->seedLongTermJobs($agency);

        $this->seedShortTermDatesAndChildren($shortTermJobs);
        $this->seedLongTermChildrenAndSchedules($longTermJobs);
        $this->seedPayments($agency, $shortTermJobs);
        $this->seedNannyPayments($agency, $longTermJobs);
        $this->seedAttendance($shortTermJobs, $longTermJobs);
        $this->seedReviews($agency, $shortTermJobs, $longTermJobs);
        $this->seedJobMessages($shortTermJobs, $longTermJobs);
        $this->seedApplicationsAndInterviews($agency, $longTermJobs);
        $this->seedCandidateJobRequests($agency, $shortTermJobs, $longTermJobs);
        $this->seedReports($agency, $shortTermJobs);
        $this->seedRefundRequests($agency, $longTermJobs);

        $agency->update([
            'total_clients' => $this->clients->count(),
            'total_candidates' => $this->candidates->count(),
        ]);

        $this->command->info('Job seeder completed successfully!');
        $this->command->info('Created: '.count($shortTermJobs).' short-term jobs, '.count($longTermJobs).' long-term jobs');
    }

    private function client(int $index): Client
    {
        return $this->clients[$index % $this->clients->count()];
    }

    private function candidate(int $index): Candidate
    {
        return $this->candidates[$index % $this->candidates->count()];
    }

    private function location(int $index): Location
    {
        return $this->locations[$index % $this->locations->count()];
    }

    private function userFor(?string $email): ?User
    {
        return $email ? ($this->usersByEmail[$email] ?? null) : null;
    }

    /**
     * @return array<int, ShortTermJob>
     */
    private function seedShortTermJobs(Agency $agency): array
    {
        $configs = [
            ['title' => 'Weekend Babysitting', 'status' => 'completed', 'clientIdx' => 0, 'candidateIdx' => 0],
            ['title' => 'Date Night Childcare', 'status' => 'completed', 'clientIdx' => 0, 'candidateIdx' => 1],
            ['title' => 'After School Care', 'status' => 'running', 'clientIdx' => 1, 'candidateIdx' => 2],
            ['title' => 'Morning Babysitting', 'status' => 'running', 'clientIdx' => 1, 'candidateIdx' => 3],
            ['title' => 'School Pickup & Care', 'status' => 'running', 'clientIdx' => 2, 'candidateIdx' => 4],
            ['title' => 'Holiday Coverage', 'status' => 'completed', 'clientIdx' => 2, 'candidateIdx' => 5],
            ['title' => 'Emergency Babysitting', 'status' => 'running', 'clientIdx' => 3, 'candidateIdx' => 6],
            ['title' => 'Evening Babysitter', 'status' => 'marketplace', 'clientIdx' => 3, 'candidateIdx' => null],
            ['title' => 'Full Day Childcare', 'status' => 'completed', 'clientIdx' => 4, 'candidateIdx' => 7],
            ['title' => 'Toddler Playdate Care', 'status' => 'pending_payment', 'clientIdx' => 4, 'candidateIdx' => null],
            ['title' => 'Weekend Morning Care', 'status' => 'running', 'clientIdx' => 5, 'candidateIdx' => 0],
            ['title' => 'Saturday Sitter', 'status' => 'marketplace', 'clientIdx' => 5, 'candidateIdx' => null],
        ];

        $jobs = [];
        foreach ($configs as $config) {
            $client = $this->client($config['clientIdx']);
            $jobs[] = ShortTermJob::firstOrCreate(
                [
                    'agency_id' => $agency->id,
                    'client_id' => $client->id,
                    'title' => $config['title'],
                ],
                [
                    'candidate_id' => $config['candidateIdx'] !== null ? $this->candidate($config['candidateIdx'])->id : null,
                    'location_id' => $this->location($config['clientIdx'])->id,
                    'description' => 'Looking for a responsible caregiver for '.$config['title'].'. Our family needs someone trustworthy and experienced.',
                    'job_address' => '123 Family Lane',
                    'home_city' => 'Miami',
                    'home_province' => 'FL',
                    'home_postal_code' => '33101',
                    'country' => 'US',
                    'compensation_amount' => 20.00,
                    'compensation_currency' => 'usd',
                    'compensation_type' => 'per_hour',
                    'status' => $config['status'],
                    'broadcast_requested' => $config['status'] === 'marketplace',
                ]
            );
        }

        return $jobs;
    }

    /**
     * @return array<int, LongTermJob>
     */
    private function seedLongTermJobs(Agency $agency): array
    {
        $configs = [
            ['title' => 'Full-Time Live-Out Nanny', 'status' => 'running', 'clientIdx' => 0, 'candidateIdx' => 0, 'start' => '-30 days', 'end' => '+335 days'],
            ['title' => 'Summer Nanny', 'status' => 'running', 'clientIdx' => 1, 'candidateIdx' => 1, 'start' => '-14 days', 'end' => '+76 days'],
            ['title' => 'Live-In Nanny', 'status' => 'running', 'clientIdx' => 2, 'candidateIdx' => 2, 'start' => '-7 days', 'end' => '+180 days'],
            ['title' => 'After-School Nanny', 'status' => 'running', 'clientIdx' => 3, 'candidateIdx' => 3, 'start' => '-21 days', 'end' => '+200 days'],
            ['title' => 'Part-Time Afternoon Nanny', 'status' => 'marketplace', 'clientIdx' => 4, 'candidateIdx' => null, 'start' => '+14 days', 'end' => '+379 days'],
            ['title' => 'Live-In Nanny for Newborn', 'status' => 'pending_approval', 'clientIdx' => 5, 'candidateIdx' => null, 'start' => '+30 days', 'end' => '+395 days'],
            ['title' => 'Weekend Nanny', 'status' => 'running', 'clientIdx' => 0, 'candidateIdx' => 6, 'start' => '-10 days', 'end' => '+90 days'],
            ['title' => 'Twin Newborn Care', 'status' => 'running', 'clientIdx' => 1, 'candidateIdx' => 7, 'start' => '-5 days', 'end' => '+360 days'],
        ];

        $jobs = [];
        foreach ($configs as $config) {
            $client = $this->client($config['clientIdx']);
            $jobs[] = LongTermJob::firstOrCreate(
                [
                    'agency_id' => $agency->id,
                    'client_id' => $client->id,
                    'title' => $config['title'],
                ],
                [
                    'candidate_id' => $config['candidateIdx'] !== null ? $this->candidate($config['candidateIdx'])->id : null,
                    'location_id' => $this->location($config['clientIdx'])->id,
                    'description' => 'We are seeking a loving and professional nanny for our family. Must have experience with infants and toddlers.',
                    'job_address' => '456 Family Dr',
                    'home_city' => 'Miami Beach',
                    'home_province' => 'FL',
                    'home_postal_code' => '33139',
                    'country' => 'US',
                    'start_date' => now()->modify($config['start'])->format('Y-m-d'),
                    'end_date' => now()->modify($config['end'])->format('Y-m-d'),
                    'compensation_amount' => 25.00,
                    'compensation_currency' => 'usd',
                    'compensation_type' => 'per_hour',
                    'bilingual_preference' => true,
                    'special_needs' => $config['title'] === 'Twin Newborn Care',
                    'school_activity' => true,
                    'has_housekeeper' => true,
                    'live_in_required' => str_contains($config['title'], 'Live-In'),
                    'prepare_meals' => true,
                    'travel_with_family' => true,
                    'spouse_work_from_home' => 'yes_i_do',
                    'nanny_own_car' => 'yes',
                    'paid_vacation' => 'vacation_and_holidays',
                    'room_with_wifi' => str_contains($config['title'], 'Live-In'),
                    'negotiable_salary' => 'open',
                    'family_schedule' => 'Both parents work from home. Need coverage 8am-6pm.',
                    'household_tasks' => 'Light meal prep for children, tidy play areas, children laundry.',
                    'family_philosophy' => 'Gentle parenting, Montessori-inspired activities.',
                    'primary_first_name' => $client->first_name,
                    'primary_last_name' => $client->last_name,
                    'primary_email' => $client->email,
                    'primary_phone' => $client->mobile,
                    'secondary_first_name' => 'David',
                    'secondary_last_name' => $client->last_name,
                    'secondary_email' => 'david.'.strtolower($client->last_name).'@example.com',
                    'secondary_phone' => '+1 305 200 0099',
                    'status' => $config['status'],
                    'broadcast_requested' => in_array($config['status'], ['marketplace', 'pending_approval'], true),
                ]
            );
        }

        return $jobs;
    }

    /**
     * @param  array<int, ShortTermJob>  $jobs
     */
    private function seedShortTermDatesAndChildren(array $jobs): void
    {
        foreach ($jobs as $job) {
            if (ShortTermJobDate::where('short_term_job_id', $job->id)->count() === 0) {
                ShortTermJobDate::create([
                    'short_term_job_id' => $job->id,
                    'booking_date' => now()->addDays(rand(1, 14))->format('Y-m-d'),
                    'start_time' => '18:00:00',
                    'end_time' => '23:00:00',
                ]);

                if (in_array($job->status, ['running', 'completed'], true)) {
                    ShortTermJobDate::create([
                        'short_term_job_id' => $job->id,
                        'booking_date' => now()->subDays(rand(5, 20))->format('Y-m-d'),
                        'start_time' => '09:00:00',
                        'end_time' => '17:00:00',
                    ]);
                }
            }

            if (ShortTermJobChild::where('short_term_job_id', $job->id)->count() === 0) {
                foreach (['Emma', 'Liam'] as $childName) {
                    ShortTermJobChild::create([
                        'short_term_job_id' => $job->id,
                        'first_name' => $childName,
                        'last_name' => 'Child',
                        'date_of_birth' => now()->subYears(rand(2, 10))->format('Y-m-d'),
                        'gender' => 'female',
                        'interests' => 'Drawing, Reading, Outdoor play',
                        'allergies' => 'None',
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<int, LongTermJob>  $jobs
     */
    private function seedLongTermChildrenAndSchedules(array $jobs): void
    {
        foreach ($jobs as $job) {
            $client = $this->clients->firstWhere('id', $job->client_id);

            if (LongTermJobChild::where('long_term_job_id', $job->id)->count() === 0) {
                foreach (['Sophia', 'Noah'] as $childName) {
                    LongTermJobChild::create([
                        'long_term_job_id' => $job->id,
                        'first_name' => $childName,
                        'last_name' => $client?->last_name ?? 'Family',
                        'date_of_birth' => now()->subYears(rand(1, 8))->format('Y-m-d'),
                        'gender' => rand(0, 1) ? 'male' : 'female',
                        'interests' => 'Piano, Swimming, Art',
                        'allergies' => 'Peanuts',
                    ]);
                }
            }

            if (LongTermJobSchedule::where('long_term_job_id', $job->id)->count() === 0) {
                foreach (range(1, 5) as $dayOfWeek) {
                    LongTermJobSchedule::create([
                        'long_term_job_id' => $job->id,
                        'day_of_week' => $dayOfWeek,
                        'start_time' => '08:00:00',
                        'end_time' => '18:00:00',
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<int, ShortTermJob>  $jobs
     */
    private function seedPayments(Agency $agency, array $jobs): void
    {
        foreach ($jobs as $job) {
            if (! in_array($job->status, ['completed', 'running', 'pending_payment'], true)) {
                continue;
            }

            Payment::firstOrCreate(
                ['short_term_job_id' => $job->id],
                [
                    'agency_id' => $agency->id,
                    'client_id' => $job->client_id,
                    'stripe_checkout_session_id' => 'cs_test_'.bin2hex(random_bytes(16)),
                    'stripe_payment_intent_id' => 'pi_test_'.bin2hex(random_bytes(16)),
                    'amount' => $job->compensation_amount * 5,
                    'currency' => 'usd',
                    'status' => $job->status === 'pending_payment' ? 'pending' : 'succeeded',
                ]
            );
        }
    }

    /**
     * @param  array<int, LongTermJob>  $jobs
     */
    private function seedNannyPayments(Agency $agency, array $jobs): void
    {
        $i = 0;
        foreach ($jobs as $job) {
            if ($job->status !== 'running' || ! $job->candidate_id) {
                continue;
            }

            LongTermJobNannyPayment::firstOrCreate(
                ['invoice_number' => 'INV-'.now()->format('Ymd').'-'.str_pad(++$i, 3, '0', STR_PAD_LEFT)],
                [
                    'long_term_job_id' => $job->id,
                    'candidate_id' => $job->candidate_id,
                    'agency_id' => $agency->id,
                    'amount' => 4000.00,
                    'currency' => 'usd',
                    'payment_method' => 'bank_transfer',
                    'payment_date' => now()->subDays(3)->format('Y-m-d'),
                    'notes' => 'Monthly payment for nanny services - '.$job->title,
                ]
            );
        }
    }

    /**
     * @param  array<int, ShortTermJob>  $shortTermJobs
     * @param  array<int, LongTermJob>  $longTermJobs
     */
    private function seedAttendance(array $shortTermJobs, array $longTermJobs): void
    {
        foreach ($shortTermJobs as $job) {
            if (! $job->candidate_id || ! in_array($job->status, ['completed', 'running'], true)) {
                continue;
            }

            ShortTermJobAttendance::firstOrCreate(
                [
                    'short_term_job_id' => $job->id,
                    'candidate_id' => $job->candidate_id,
                    'booking_date' => now()->subDays(rand(1, 10))->format('Y-m-d'),
                ],
                [
                    'check_in' => '09:00:00',
                    'check_out' => '17:00:00',
                    'notes' => 'On time, great with kids.',
                ]
            );
        }

        foreach ($longTermJobs as $job) {
            if ($job->status !== 'running' || ! $job->candidate_id) {
                continue;
            }

            foreach (range(0, 4) as $dayOffset) {
                LongTermJobAttendance::firstOrCreate(
                    [
                        'long_term_job_id' => $job->id,
                        'candidate_id' => $job->candidate_id,
                        'date' => now()->subDays($dayOffset)->format('Y-m-d'),
                    ],
                    [
                        'check_in' => '08:00:00',
                        'check_out' => '18:00:00',
                        'is_absent' => rand(0, 5) === 0,
                        'notes' => 'Regular work day.',
                    ]
                );
            }
        }
    }

    /**
     * @param  array<int, ShortTermJob>  $shortTermJobs
     * @param  array<int, LongTermJob>  $longTermJobs
     */
    private function seedReviews(Agency $agency, array $shortTermJobs, array $longTermJobs): void
    {
        foreach ($shortTermJobs as $job) {
            if ($job->status !== 'completed' || ! $job->candidate_id) {
                continue;
            }

            ShortTermJobReview::firstOrCreate(
                [
                    'short_term_job_id' => $job->id,
                    'candidate_id' => $job->candidate_id,
                    'client_id' => $job->client_id,
                ],
                [
                    'agency_id' => $agency->id,
                    'rating' => rand(4, 5),
                    'review' => 'Wonderful with our children. Very punctual and caring. Highly recommend!',
                ]
            );
        }

        foreach ($longTermJobs as $job) {
            if ($job->status !== 'running' || ! $job->candidate_id) {
                continue;
            }

            LongTermJobReview::firstOrCreate(
                [
                    'long_term_job_id' => $job->id,
                    'candidate_id' => $job->candidate_id,
                    'client_id' => $job->client_id,
                ],
                [
                    'agency_id' => $agency->id,
                    'rating' => rand(4, 5),
                    'review' => 'Our family is very happy with the care provided. Reliable, professional, and loving.',
                ]
            );
        }
    }

    /**
     * @param  array<int, ShortTermJob>  $shortTermJobs
     * @param  array<int, LongTermJob>  $longTermJobs
     */
    private function seedJobMessages(array $shortTermJobs, array $longTermJobs): void
    {
        foreach ($shortTermJobs as $job) {
            if (! $job->candidate_id) {
                continue;
            }

            $client = $this->clients->firstWhere('id', $job->client_id);
            $candidate = $this->candidates->firstWhere('id', $job->candidate_id);
            $clientUser = $this->userFor($client?->email);
            $candidateUser = $this->userFor($candidate?->email);

            if ($clientUser && JobMessage::where('short_term_job_id', $job->id)->where('sender_id', $clientUser->id)->count() === 0) {
                JobMessage::create([
                    'short_term_job_id' => $job->id,
                    'sender_id' => $clientUser->id,
                    'thread' => 'client_candidate',
                    'message' => 'Thank you for accepting the job. We are looking forward to having you!',
                    'read_at' => $job->status === 'completed' ? now()->subHours(2) : null,
                ]);
            }

            if ($candidateUser && JobMessage::where('short_term_job_id', $job->id)->where('sender_id', $candidateUser->id)->count() === 0) {
                JobMessage::create([
                    'short_term_job_id' => $job->id,
                    'sender_id' => $candidateUser->id,
                    'thread' => 'client_candidate',
                    'message' => 'I am excited to work with your family. See you soon!',
                    'read_at' => $job->status === 'completed' ? now()->subHours(1) : null,
                ]);
            }
        }

        foreach ($longTermJobs as $job) {
            if (! $job->candidate_id) {
                continue;
            }

            $client = $this->clients->firstWhere('id', $job->client_id);
            $candidate = $this->candidates->firstWhere('id', $job->candidate_id);
            $clientUser = $this->userFor($client?->email);
            $candidateUser = $this->userFor($candidate?->email);

            if ($clientUser && JobMessage::where('long_term_job_id', $job->id)->where('sender_id', $clientUser->id)->count() === 0) {
                JobMessage::create([
                    'long_term_job_id' => $job->id,
                    'sender_id' => $clientUser->id,
                    'thread' => 'client_candidate',
                    'message' => 'Welcome to our family! We are excited to start this journey together.',
                    'read_at' => $job->status === 'running' ? now()->subDay() : null,
                ]);
            }

            if ($candidateUser && JobMessage::where('long_term_job_id', $job->id)->where('sender_id', $candidateUser->id)->count() === 0) {
                JobMessage::create([
                    'long_term_job_id' => $job->id,
                    'sender_id' => $candidateUser->id,
                    'thread' => 'client_candidate',
                    'message' => 'Thank you for this opportunity. I will take great care of your children!',
                    'read_at' => $job->status === 'running' ? now()->subHours(12) : null,
                ]);
            }
        }
    }

    /**
     * @param  array<int, LongTermJob>  $jobs
     */
    private function seedApplicationsAndInterviews(Agency $agency, array $jobs): void
    {
        $marketplaceJob = collect($jobs)->firstWhere('status', 'marketplace');

        if (! $marketplaceJob) {
            return;
        }

        $application = LongTermJobApplication::firstOrCreate(
            [
                'long_term_job_id' => $marketplaceJob->id,
                'candidate_id' => $this->candidate(4)->id,
            ],
            [
                'agency_id' => $agency->id,
                'application_message' => 'I am very interested in this position. I have 7 years of experience.',
                'status' => 'pending',
            ]
        );

        LongTermJobApplication::firstOrCreate(
            [
                'long_term_job_id' => $marketplaceJob->id,
                'candidate_id' => $this->candidate(5)->id,
            ],
            [
                'agency_id' => $agency->id,
                'application_message' => 'I would love to work with your family. Available to start immediately.',
                'status' => 'pending',
            ]
        );

        LongTermJobInterview::firstOrCreate(
            [
                'long_term_job_id' => $marketplaceJob->id,
                'long_term_job_application_id' => $application->id,
            ],
            [
                'candidate_id' => $this->candidate(4)->id,
                'agency_id' => $agency->id,
                'scheduled_date' => now()->addDays(5)->format('Y-m-d'),
                'available_from' => '10:00:00',
                'available_to' => '11:00:00',
                'interview_type' => 'zoom',
                'interview_link' => 'https://zoom.us/j/example123',
                'description' => 'Initial interview to meet the family',
                'status' => 'scheduled',
            ]
        );
    }

    /**
     * @param  array<int, ShortTermJob>  $shortTermJobs
     * @param  array<int, LongTermJob>  $longTermJobs
     */
    private function seedCandidateJobRequests(Agency $agency, array $shortTermJobs, array $longTermJobs): void
    {
        $marketplaceShortJob = collect($shortTermJobs)->firstWhere('status', 'marketplace');

        if ($marketplaceShortJob) {
            foreach ([1, 2] as $candidateIdx) {
                CandidateJobRequest::firstOrCreate(
                    [
                        'client_id' => $marketplaceShortJob->client_id,
                        'candidate_id' => $this->candidate($candidateIdx)->id,
                        'short_term_job_id' => $marketplaceShortJob->id,
                    ],
                    [
                        'agency_id' => $agency->id,
                        'job_type' => 'short_term',
                        'status' => 'pending',
                        'message' => 'Candidate is interested in this position.',
                    ]
                );
            }
        }

        $approvedLongJob = collect($longTermJobs)->first(
            fn ($job) => $job->status === 'running' && $job->candidate_id
        );

        if ($approvedLongJob) {
            CandidateJobRequest::firstOrCreate(
                [
                    'client_id' => $approvedLongJob->client_id,
                    'candidate_id' => $approvedLongJob->candidate_id,
                    'long_term_job_id' => $approvedLongJob->id,
                ],
                [
                    'agency_id' => $agency->id,
                    'job_type' => 'long_term',
                    'status' => 'approved',
                    'message' => 'Candidate has been approved for the long-term position.',
                    'responded_at' => now()->subDay(),
                ]
            );
        }
    }

    /**
     * @param  array<int, ShortTermJob>  $jobs
     */
    private function seedReports(Agency $agency, array $jobs): void
    {
        foreach ($jobs as $job) {
            if ($job->status !== 'completed' || ! $job->candidate_id) {
                continue;
            }

            CandidateClientReport::firstOrCreate(
                [
                    'short_term_job_id' => $job->id,
                    'candidate_id' => $job->candidate_id,
                ],
                [
                    'agency_id' => $agency->id,
                    'client_id' => $job->client_id,
                    'job_type' => 'short_term',
                    'reason' => 'Client reported excellent service and would like to request this candidate again.',
                    'status' => 'reviewed',
                    'resolved_at' => now()->subDays(2),
                ]
            );
        }
    }

    /**
     * @param  array<int, LongTermJob>  $jobs
     */
    private function seedRefundRequests(Agency $agency, array $jobs): void
    {
        $job = collect($jobs)->first(fn ($j) => $j->status === 'running');

        if (! $job) {
            return;
        }

        LongTermJobRefundRequest::firstOrCreate(
            ['long_term_job_id' => $job->id],
            [
                'client_id' => $job->client_id,
                'agency_id' => $agency->id,
                'reason' => 'Overcharged for the first month due to billing cycle miscalculation.',
                'status' => 'approved',
                'agency_note' => 'Refund has been processed. Credit will appear in next statement.',
                'resolved_at' => now()->subDays(5),
            ]
        );
    }
}
