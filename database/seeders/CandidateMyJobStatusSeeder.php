<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\Location;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobChild;
use App\Models\LongTermJobSchedule;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobApplication;
use App\Models\ShortTermJobChild;
use App\Models\ShortTermJobDate;
use Illuminate\Database\Seeder;

/**
 * Seeds one job per status for both short-term and long-term job types,
 * all assigned to the first candidate. This lets the candidate's "My Jobs"
 * view be exercised with every possible status filter during local development.
 *
 * Prerequisites: AgencySeeder, SupportingDataSeeder, CandidateSeeder, ClientSeeder.
 */
class CandidateMyJobStatusSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        $candidate = Candidate::where('agency_id', $agency->id)->orderBy('id')->first();
        $client = Client::where('agency_id', $agency->id)->orderBy('id')->first();
        $location = Location::where('agency_id', $agency->id)->orderBy('id')->first();

        if (! $candidate || ! $client) {
            $this->command->error('Candidate or client not found. Run CandidateSeeder and ClientSeeder first.');

            return;
        }

        $shortTermJobs = $this->seedShortTermJobs($agency, $candidate, $client, $location);
        $longTermJobs = $this->seedLongTermJobs($agency, $candidate, $client, $location);

        $this->seedShortTermDatesAndChildren($shortTermJobs);
        $this->seedLongTermChildrenAndSchedules($longTermJobs);
        $this->seedMarketplaceApplicants($agency, $shortTermJobs, $longTermJobs, $candidate);

        $this->command->info('CandidateMyJobStatus seeder completed.');
        $this->command->info(
            'Created: '.count($shortTermJobs).' short-term jobs, '.count($longTermJobs).' long-term jobs'
            .' — all assigned to candidate #'.$candidate->id.' ('.$candidate->first_name.' '.$candidate->last_name.').'
        );
    }

    /**
     * Short-term covers every value in the status enum:
     * draft | pending_payment | pending_approval | marketplace | running | completed | cancelled | rejected
     *
     * Only statuses reached after a hire (running, completed, cancelled) get the
     * showcase candidate on candidate_id — earlier statuses (draft, pending_payment,
     * pending_approval, marketplace) and rejected postings never had anyone hired,
     * so leaving candidate_id set there falsely surfaces a "hired candidate" to
     * clients for jobs no one was ever hired on.
     *
     * @return array<int, ShortTermJob>
     */
    private function seedShortTermJobs(Agency $agency, Candidate $candidate, Client $client, ?Location $location): array
    {
        $configs = [
            ['status' => 'draft',            'title' => 'Status Showcase — Draft (Short-Term)'],
            ['status' => 'pending_payment',  'title' => 'Status Showcase — Pending Payment (Short-Term)'],
            ['status' => 'pending_approval', 'title' => 'Status Showcase — Pending Approval (Short-Term)'],
            ['status' => 'marketplace',      'title' => 'Status Showcase — Marketplace (Short-Term)'],
            ['status' => 'running',          'title' => 'Status Showcase — Running (Short-Term)'],
            ['status' => 'completed',        'title' => 'Status Showcase — Completed (Short-Term)'],
            ['status' => 'cancelled',        'title' => 'Status Showcase — Cancelled (Short-Term)'],
            ['status' => 'rejected',         'title' => 'Status Showcase — Rejected (Short-Term)'],
        ];

        $hiredStatuses = ['running', 'completed', 'cancelled'];

        $jobs = [];
        foreach ($configs as $config) {
            $jobs[] = ShortTermJob::firstOrCreate(
                [
                    'agency_id' => $agency->id,
                    'client_id' => $client->id,
                    'title' => $config['title'],
                ],
                [
                    'candidate_id' => in_array($config['status'], $hiredStatuses, true) ? $candidate->id : null,
                    'location_id' => $location?->id,
                    'description' => 'Candidate My Jobs status showcase ('.$config['status'].').',
                    'job_address' => '123 Status Lane',
                    'home_city' => 'Miami',
                    'home_province' => 'FL',
                    'home_postal_code' => '33101',
                    'country' => 'US',
                    'compensation_amount' => 20.00,
                    'compensation_currency' => 'usd',
                    'compensation_type' => 'per_hour',
                    'status' => $config['status'],
                    'rejection_reason' => $config['status'] === 'rejected'
                        ? 'Agency could not verify the requested availability for this booking.'
                        : null,
                    'cancellation_reason' => $config['status'] === 'cancelled'
                        ? 'Client cancelled due to a change in family plans.'
                        : null,
                    'cancelled_at' => $config['status'] === 'cancelled' ? now()->subDays(3) : null,
                ]
            );
        }

        return $jobs;
    }

    /**
     * Long-term covers every value in the status enum:
     * pending_approval | marketplace | running | completed | cancelled | rejected
     *
     * Only statuses reached after a hire (running, completed, cancelled) get the
     * showcase candidate on candidate_id — see seedShortTermJobs() for why.
     *
     * @return array<int, LongTermJob>
     */
    private function seedLongTermJobs(Agency $agency, Candidate $candidate, Client $client, ?Location $location): array
    {
        $configs = [
            ['status' => 'pending_approval', 'title' => 'Status Showcase — Pending Approval (Long-Term)',  'start' => '+30 days',   'end' => '+395 days'],
            ['status' => 'marketplace',      'title' => 'Status Showcase — Marketplace (Long-Term)',       'start' => '+14 days',   'end' => '+379 days'],
            ['status' => 'running',          'title' => 'Status Showcase — Running (Long-Term)',           'start' => '-30 days',   'end' => '+335 days'],
            ['status' => 'completed',        'title' => 'Status Showcase — Completed (Long-Term)',         'start' => '-400 days',  'end' => '-35 days'],
            ['status' => 'cancelled',        'title' => 'Status Showcase — Cancelled (Long-Term)',         'start' => '-15 days',   'end' => '+200 days'],
            ['status' => 'rejected',         'title' => 'Status Showcase — Rejected (Long-Term)',          'start' => '+20 days',   'end' => '+300 days'],
        ];

        $hiredStatuses = ['running', 'completed', 'cancelled'];

        $jobs = [];
        foreach ($configs as $config) {
            $jobs[] = LongTermJob::firstOrCreate(
                [
                    'agency_id' => $agency->id,
                    'client_id' => $client->id,
                    'title' => $config['title'],
                ],
                [
                    'candidate_id' => in_array($config['status'], $hiredStatuses, true) ? $candidate->id : null,
                    'location_id' => $location?->id,
                    'description' => 'Candidate My Jobs status showcase ('.$config['status'].').',
                    'job_address' => '456 Status Dr',
                    'home_city' => 'Miami Beach',
                    'home_province' => 'FL',
                    'home_postal_code' => '33139',
                    'country' => 'US',
                    'start_date' => now()->modify($config['start'])->format('Y-m-d'),
                    'end_date' => now()->modify($config['end'])->format('Y-m-d'),
                    'compensation_amount' => 25.00,
                    'compensation_currency' => 'usd',
                    'compensation_type' => 'per_hour',
                    'status' => $config['status'],
                    'rejection_reason' => $config['status'] === 'rejected'
                        ? 'Family requirements could not be matched with an available nanny at this time.'
                        : null,
                    'cancellation_reason' => $config['status'] === 'cancelled'
                        ? 'Placement cancelled after the family relocated unexpectedly.'
                        : null,
                    'cancelled_at' => $config['status'] === 'cancelled' ? now()->subDays(5) : null,
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
                    'booking_date' => now()->addDays(7)->format('Y-m-d'),
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                ]);
            }

            if (ShortTermJobChild::where('short_term_job_id', $job->id)->count() === 0) {
                ShortTermJobChild::create([
                    'short_term_job_id' => $job->id,
                    'first_name' => 'Emma',
                    'last_name' => 'Sample',
                    'date_of_birth' => now()->subYears(5)->format('Y-m-d'),
                    'gender' => 'female',
                    'interests' => 'Drawing, Reading',
                    'allergies' => 'None',
                ]);
            }
        }
    }

    /**
     * @param  array<int, LongTermJob>  $jobs
     */
    private function seedLongTermChildrenAndSchedules(array $jobs): void
    {
        foreach ($jobs as $job) {
            if (LongTermJobChild::where('long_term_job_id', $job->id)->count() === 0) {
                LongTermJobChild::create([
                    'long_term_job_id' => $job->id,
                    'first_name' => 'Sophia',
                    'last_name' => 'Sample',
                    'date_of_birth' => now()->subYears(3)->format('Y-m-d'),
                    'gender' => 'female',
                    'interests' => 'Piano, Art',
                    'allergies' => 'None',
                ]);
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
     * The marketplace showcase jobs have no hired candidate yet, so give them
     * a few pending applicants — otherwise the client's Applicants tab has
     * nothing to show for the one status where applicants actually matter.
     *
     * @param  array<int, ShortTermJob>  $shortTermJobs
     * @param  array<int, LongTermJob>  $longTermJobs
     */
    private function seedMarketplaceApplicants(Agency $agency, array $shortTermJobs, array $longTermJobs, Candidate $excludeCandidate): void
    {
        $messages = [
            'I am available for this booking and have great references from similar families.',
            'I would love to help your family out. I have years of babysitting experience.',
            'This schedule works perfectly for me. Happy to start right away.',
        ];

        $applicants = Candidate::where('agency_id', $agency->id)
            ->where('id', '!=', $excludeCandidate->id)
            ->orderBy('id')
            ->take(3)
            ->get();

        $shortTermMarketplaceJob = collect($shortTermJobs)->firstWhere('status', 'marketplace');

        if ($shortTermMarketplaceJob) {
            foreach ($applicants as $index => $applicant) {
                ShortTermJobApplication::firstOrCreate(
                    [
                        'short_term_job_id' => $shortTermMarketplaceJob->id,
                        'candidate_id' => $applicant->id,
                    ],
                    [
                        'agency_id' => $agency->id,
                        'application_message' => $messages[$index % count($messages)],
                        'status' => 'pending',
                    ]
                );
            }
        }

        $longTermMarketplaceJob = collect($longTermJobs)->firstWhere('status', 'marketplace');

        if ($longTermMarketplaceJob) {
            foreach ($applicants as $index => $applicant) {
                LongTermJobApplication::firstOrCreate(
                    [
                        'long_term_job_id' => $longTermMarketplaceJob->id,
                        'candidate_id' => $applicant->id,
                    ],
                    [
                        'agency_id' => $agency->id,
                        'application_message' => $messages[$index % count($messages)],
                        'status' => 'pending',
                    ]
                );
            }
        }
    }
}
