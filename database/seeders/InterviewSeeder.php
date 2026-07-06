<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobInterview;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seeds long-term interviews for both portals so the client interview screens
 * (GET /client/interviews) and the candidate interview screens
 * (GET /candidate/interviews) show upcoming, previous and cancelled records.
 *
 * Two flows are represented, matching the application code:
 *  - Job-tied interviews: linked to a posted job through an application
 *    (LongTermJobApplicationController@interview).
 *  - Direct interviews: requested straight from a candidate profile with only
 *    client_id + candidate_id and no job (ClientCandidateController@interviewRequest).
 */
class InterviewSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        $clients = Client::where('agency_id', $agency->id)->orderBy('id')->get()->values();
        $candidates = Candidate::where('agency_id', $agency->id)->orderBy('id')->get()->values();

        if ($clients->isEmpty() || $candidates->isEmpty()) {
            $this->command->error('Clients/candidates not found. Run ClientSeeder & CandidateSeeder first.');

            return;
        }

        $jobInterviews = $this->seedJobInterviews($agency);
        $directInterviews = $this->seedDirectInterviews($agency, $clients, $candidates);
        $calendarInterviews = $this->seedCandidateCalendarInterviews($agency, $clients, $candidates);

        $this->command->info('Interview seeder completed successfully!');
        $this->command->info("Created/updated {$jobInterviews} job-tied, {$directInterviews} direct and {$calendarInterviews} calendar interviews.");
    }

    /**
     * Turn a handful of existing applications into scheduled/completed/cancelled
     * interviews so each participating client and candidate has job-tied history.
     */
    private function seedJobInterviews(Agency $agency): int
    {
        $applications = LongTermJobApplication::with('job')
            ->where('agency_id', $agency->id)
            ->whereIn('status', ['pending', 'interviewed'])
            ->whereHas('job')
            ->orderBy('long_term_job_id')
            ->orderBy('id')
            ->get()
            ->unique('long_term_job_id')
            ->take(6)
            ->values();

        $variants = ['upcoming', 'requested', 'previous', 'cancelled', 'rescheduled'];
        $count = 0;

        foreach ($applications as $index => $application) {
            $attributes = $this->variantAttributes($variants[$index % count($variants)], $index);

            LongTermJobInterview::updateOrCreate(
                ['long_term_job_application_id' => $application->id],
                array_merge($attributes, [
                    'long_term_job_id' => $application->long_term_job_id,
                    'candidate_id' => $application->candidate_id,
                    'agency_id' => $agency->id,
                ])
            );

            $application->update(['status' => 'interviewed']);
            $count++;
        }

        return $count;
    }

    /**
     * Interviews requested from a candidate profile: only client_id + candidate_id,
     * no job. Pairs are chosen so they don't collide with the placed candidate.
     *
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, Candidate>  $candidates
     */
    private function seedDirectInterviews(Agency $agency, Collection $clients, Collection $candidates): int
    {
        $configs = [
            ['clientIdx' => 0, 'candidateIdx' => 2, 'variant' => 'requested'],
            ['clientIdx' => 1, 'candidateIdx' => 3, 'variant' => 'upcoming'],
            ['clientIdx' => 2, 'candidateIdx' => 4, 'variant' => 'cancelled'],
            ['clientIdx' => 3, 'candidateIdx' => 5, 'variant' => 'rescheduled'],
            ['clientIdx' => 4, 'candidateIdx' => 6, 'variant' => 'previous'],
        ];

        $count = 0;

        foreach ($configs as $index => $config) {
            $client = $clients[$config['clientIdx'] % $clients->count()];
            $candidate = $candidates[$config['candidateIdx'] % $candidates->count()];
            $attributes = $this->variantAttributes($config['variant'], $index);

            LongTermJobInterview::updateOrCreate(
                [
                    'client_id' => $client->id,
                    'candidate_id' => $candidate->id,
                    'scheduled_date' => $attributes['scheduled_date'],
                ],
                array_merge($attributes, [
                    'long_term_job_id' => null,
                    'long_term_job_application_id' => null,
                    'agency_id' => $agency->id,
                ])
            );

            $count++;
        }

        return $count;
    }

    /**
     * Give every candidate a populated interview calendar for the current
     * month: a same-day pair (morning + afternoon meetings with two different
     * families) plus an earlier completed interview. This is what makes the
     * candidate calendar view (GET /candidate/interviews?view=calendar) show
     * dates carrying multiple interview cards.
     *
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, Candidate>  $candidates
     */
    private function seedCandidateCalendarInterviews(Agency $agency, Collection $clients, Collection $candidates): int
    {
        $count = 0;
        $monthStart = now()->startOfMonth();

        foreach ($candidates as $index => $candidate) {
            $client = $clients[$index % $clients->count()];
            $otherClient = $clients[($index + 1) % $clients->count()];

            // Two interviews on the same day prove the grouped calendar shape.
            $pairDate = $monthStart->copy()->addDays(6 + ($index % 14))->format('Y-m-d');
            $completedDate = $monthStart->copy()->addDays($index % 5)->format('Y-m-d');

            $entries = [
                [
                    'client' => $client,
                    'title' => 'Interview with the '.$client->last_name.' Family',
                    'scheduled_date' => $pairDate,
                    'available_from' => '10:00:00',
                    'available_to' => '11:00:00',
                    'interview_type' => 'zoom',
                    'interview_link' => 'https://zoom.us/j/'.(7100000 + $index),
                    'description' => 'Morning video interview to meet the family.',
                    'status' => 'scheduled',
                ],
                [
                    'client' => $otherClient,
                    'title' => 'Interview with the '.$otherClient->last_name.' Family',
                    'scheduled_date' => $pairDate,
                    'available_from' => '15:00:00',
                    'available_to' => '16:00:00',
                    'interview_type' => 'google_meet',
                    'interview_link' => 'https://meet.google.com/cal-'.(2000 + $index),
                    'description' => 'Afternoon interview with a second family.',
                    'status' => 'scheduled',
                ],
                [
                    'client' => $client,
                    'title' => 'Meet & Greet with the '.$client->last_name.' Family',
                    'scheduled_date' => $completedDate,
                    'available_from' => '09:00:00',
                    'available_to' => '09:45:00',
                    'interview_type' => 'in_person',
                    'interview_link' => null,
                    'description' => 'Completed meet-and-greet at the family home.',
                    'status' => 'completed',
                ],
            ];

            foreach ($entries as $entry) {
                LongTermJobInterview::updateOrCreate(
                    [
                        'client_id' => $entry['client']->id,
                        'candidate_id' => $candidate->id,
                        'scheduled_date' => $entry['scheduled_date'],
                        'available_from' => $entry['available_from'],
                    ],
                    [
                        'agency_id' => $agency->id,
                        'long_term_job_id' => null,
                        'long_term_job_application_id' => null,
                        'title' => $entry['title'],
                        'available_to' => $entry['available_to'],
                        'interview_type' => $entry['interview_type'],
                        'interview_link' => $entry['interview_link'],
                        'description' => $entry['description'],
                        'special_note' => null,
                        'reschedule_reason' => null,
                        'status' => $entry['status'],
                    ]
                );

                $count++;
            }
        }

        return $count;
    }

    /**
     * Field set for a given lifecycle state, shared by both interview flows.
     *
     * @return array<string, mixed>
     */
    private function variantAttributes(string $variant, int $index): array
    {
        return match ($variant) {
            // Client asked; awaiting the agency to set the meeting. No link yet.
            'requested' => [
                'scheduled_date' => now()->addDays(6 + $index)->format('Y-m-d'),
                'available_from' => '13:00:00',
                'available_to' => '14:00:00',
                'interview_type' => 'zoom',
                'interview_link' => null,
                'description' => 'Video interview to meet the family and discuss expectations.',
                'special_note' => null,
                'reschedule_reason' => null,
                'status' => 'requested',
            ],
            'previous' => [
                'scheduled_date' => now()->subDays(7 + $index)->format('Y-m-d'),
                'available_from' => '14:00:00',
                'available_to' => '15:00:00',
                'interview_type' => 'in_person',
                'interview_link' => null,
                'description' => 'In-person meet-and-greet at the family home.',
                'special_note' => 'Bring references and childcare certifications.',
                'reschedule_reason' => null,
                'status' => 'completed',
            ],
            'cancelled' => [
                'scheduled_date' => now()->addDays(2 + $index)->format('Y-m-d'),
                'available_from' => '09:00:00',
                'available_to' => '10:00:00',
                'interview_type' => 'google_meet',
                'interview_link' => 'https://meet.google.com/xyz-'.(1000 + $index),
                'description' => 'Interview cancelled before it took place.',
                'special_note' => null,
                'reschedule_reason' => null,
                'cancellation_reason' => 'The family cancelled due to a change in plans.',
                'status' => 'cancelled',
            ],
            // A confirmed meeting with a client reschedule request awaiting the
            // agency: the confirmed slot stays put; the proposed slot + reason
            // live in the reschedule_* fields until the agency approves.
            'rescheduled' => [
                'scheduled_date' => now()->addDays(5 + $index)->format('Y-m-d'),
                'available_from' => '11:00:00',
                'available_to' => '12:00:00',
                'interview_type' => 'zoom',
                'interview_link' => 'https://zoom.us/j/'.(8000000 + $index),
                'description' => 'Video interview to meet the family and discuss expectations.',
                'special_note' => 'Please join 5 minutes early with a stable connection.',
                'reschedule_reason' => 'Client requested a later time due to a work commitment.',
                'reschedule_requested_at' => now()->subDay(),
                'reschedule_date' => now()->addDays(9 + $index)->format('Y-m-d'),
                'reschedule_from' => '16:00:00',
                'reschedule_to' => '17:00:00',
                'status' => 'scheduled',
            ],
            default => [
                'scheduled_date' => now()->addDays(3 + $index)->format('Y-m-d'),
                'available_from' => '10:00:00',
                'available_to' => '11:00:00',
                'interview_type' => 'zoom',
                'interview_link' => 'https://zoom.us/j/'.(9000000 + $index),
                'description' => 'Video interview to meet the family and discuss expectations.',
                'special_note' => 'Please join 5 minutes early with a stable connection.',
                'reschedule_reason' => null,
                'status' => 'scheduled',
            ],
        };
    }
}
