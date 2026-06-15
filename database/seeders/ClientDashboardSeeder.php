<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\CandidateClientReport;
use App\Models\CandidateJobRequest;
use App\Models\CheckList;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Event;
use App\Models\EventType;
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
use App\Models\Status;
use App\Models\Tag;
use App\Models\Type;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ClientDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        $role = Role::where('name', 'client')->where('guard_name', 'api')->first();
        $candidateRole = Role::where('name', 'candidate')->where('guard_name', 'api')->first();

        /*
        |--------------------------------------------------------------------------
        | Supporting Data: Types, Locations, Tags, Statuses, CheckLists, EventTypes
        |--------------------------------------------------------------------------
        */
        $types = [];
        foreach (['Full-Time', 'Part-Time', 'Temporary', 'Weekend'] as $name) {
            $types[] = Type::firstOrCreate([
                'agency_id' => $agency->id,
                'name' => $name,
            ], [
                'type' => 'client',
                'status' => 1,
            ]);
        }

        $locations = [];
        foreach (['Miami Beach', 'Coral Gables', 'Brickell', 'Key Biscayne'] as $loc) {
            $locations[] = Location::firstOrCreate([
                'agency_id' => $agency->id,
                'location' => $loc,
            ], [
                'status' => 1,
            ]);
        }

        $tags = [];
        foreach (['VIP', 'New', 'Referral', 'Repeat'] as $name) {
            $tags[] = Tag::firstOrCreate([
                'agency_id' => $agency->id,
                'name' => $name,
                'type' => 'client',
            ], [
                'status' => 1,
            ]);
        }

        $statuses = [];
        foreach ([
            ['Active', '#28a745', 1],
            ['Inactive', '#dc3545', 2],
            ['Lead', '#ffc107', 3],
        ] as [$name, $color, $serial]) {
            $statuses[] = Status::firstOrCreate([
                'agency_id' => $agency->id,
                'name' => $name,
                'type' => 'client',
            ], [
                'color' => $color,
                'serial' => $serial,
            ]);
        }

        $checklists = [];
        foreach (['ID Verified', 'Contract Signed', 'Background Check', 'Orientation Completed'] as $name) {
            $checklists[] = CheckList::firstOrCreate([
                'agency_id' => $agency->id,
                'name' => $name,
                'type' => 'client',
            ], [
                'status' => 1,
            ]);
        }

        $eventTypes = [];
        foreach (['Interview', 'Meeting', 'Follow-up', 'Orientation'] as $name) {
            $eventTypes[] = EventType::firstOrCreate([
                'agency_id' => $agency->id,
                'name' => $name,
                'type' => 'client',
            ], [
                'status' => 1,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Candidates
        |--------------------------------------------------------------------------
        */
        $candidatesData = [
            [
                'first_name' => 'Maria', 'last_name' => 'Garcia',
                'email' => 'maria.garcia@example.com', 'mobile' => '+1 305 111 0001',
                'date_of_birth' => '1995-03-15', 'nationality' => 'US',
                'street_address' => '456 Oak Ave', 'city' => 'Miami', 'province' => 'FL',
                'postal_code' => '33101', 'country' => 'US',
                'years_of_experience' => '5-10', 'commitment' => 'long_term',
                'pay_range_per_hour' => '20-25', 'hours_per_week' => '40',
                'bilingual' => 'Spanish', 'drivers_license' => 'dl_and_car',
                'cpr_first_aid' => 'yes', 'vaccinations' => 'yes',
                'ok_with_pets' => 'dog', 'ok_with_travel' => 'domestic',
                'work_legally_in_us' => true, 'comfortable_paid_legally' => true, 'has_ssn' => true,
                'hear_about_us' => 'Friend',
            ],
            [
                'first_name' => 'Sarah', 'last_name' => 'Johnson',
                'email' => 'sarah.johnson@example.com', 'mobile' => '+1 305 111 0002',
                'date_of_birth' => '1998-07-22', 'nationality' => 'US',
                'street_address' => '789 Pine St', 'city' => 'Miami Beach', 'province' => 'FL',
                'postal_code' => '33139', 'country' => 'US',
                'years_of_experience' => '2-5', 'commitment' => 'short_term',
                'pay_range_per_hour' => '18-22', 'hours_per_week' => '30',
                'bilingual' => 'French', 'drivers_license' => 'dl_only',
                'cpr_first_aid' => 'yes', 'vaccinations' => 'willing',
                'ok_with_pets' => 'cat', 'ok_with_travel' => 'no_travel',
                'work_legally_in_us' => true, 'comfortable_paid_legally' => true, 'has_ssn' => true,
                'hear_about_us' => 'Google',
            ],
            [
                'first_name' => 'Emily', 'last_name' => 'Davis',
                'email' => 'emily.davis@example.com', 'mobile' => '+1 305 111 0003',
                'date_of_birth' => '1993-11-08', 'nationality' => 'US',
                'street_address' => '321 Elm Blvd', 'city' => 'Coral Gables', 'province' => 'FL',
                'postal_code' => '33146', 'country' => 'US',
                'years_of_experience' => '10+', 'commitment' => 'temporary',
                'pay_range_per_hour' => '25-30', 'hours_per_week' => '20',
                'bilingual' => 'None', 'drivers_license' => 'dl_and_car',
                'cpr_first_aid' => 'yes', 'vaccinations' => 'yes',
                'ok_with_pets' => 'neither', 'ok_with_travel' => 'international',
                'work_legally_in_us' => true, 'comfortable_paid_legally' => true, 'has_ssn' => true,
                'hear_about_us' => 'Referral',
            ],
        ];

        $candidates = [];
        foreach ($candidatesData as $data) {
            $data['agency_id'] = $agency->id;
            $data['location_id'] = json_encode([$locations[array_rand($locations)]->id]);
            $candidate = Candidate::firstOrCreate(
                ['email' => $data['email']],
                $data
            );
            $user = User::firstOrCreate(
                ['email' => $candidate->email],
                [
                    'first_name' => $candidate->first_name,
                    'last_name' => $candidate->last_name,
                    'email' => $candidate->email,
                    'mobile' => $candidate->mobile,
                    'agency_id' => $agency->id,
                    'is_owner' => 0,
                    'password' => bcrypt('111111'),
                ]
            );
            if (! $user->hasRole($candidateRole)) {
                $user->assignRole($candidateRole);
            }
            $candidates[] = $candidate;
        }

        /*
        |--------------------------------------------------------------------------
        | Clients
        |--------------------------------------------------------------------------
        */
        $clientsData = [
            [
                'first_name' => 'Emily', 'last_name' => 'Thompson',
                'email' => 'emily.thompson@example.com', 'mobile' => '+1 305 200 0001',
                'hear_about_us' => 'Google',
                'payment_status' => 'paid',
            ],
            [
                'first_name' => 'Michael', 'last_name' => 'Rodriguez',
                'email' => 'michael.rodriguez@example.com', 'mobile' => '+1 305 200 0002',
                'hear_about_us' => 'Friend Referral',
                'payment_status' => 'paid',
            ],
            [
                'first_name' => 'Jessica', 'last_name' => 'Williams',
                'email' => 'jessica.williams@example.com', 'mobile' => '+1 305 200 0003',
                'hear_about_us' => 'Social Media',
                'payment_status' => 'pending',
            ],
        ];

        $existingClient = Client::where('agency_id', $agency->id)->where('email', 'client@gmail.com')->first();
        if ($existingClient) {
            $existingClient->update([
                'type_id' => json_encode([$types[0]->id]),
                'location_id' => json_encode([$locations[0]->id]),
                'tag_id' => json_encode([$tags[2]->id]),
                'status_id' => json_encode([$statuses[0]->id]),
                'checklist_id' => json_encode([$checklists[0]->id, $checklists[1]->id, $checklists[2]->id]),
            ]);
        }

        $clients = $existingClient ? [$existingClient] : [];
        foreach ($clientsData as $index => $data) {
            $data['agency_id'] = $agency->id;
            $data['type_id'] = json_encode([$types[$index % count($types)]->id]);
            $data['location_id'] = json_encode([$locations[$index % count($locations)]->id]);
            $data['tag_id'] = json_encode([$tags[$index % count($tags)]->id]);
            $data['status_id'] = json_encode([$statuses[0]->id]);
            $data['checklist_id'] = json_encode([$checklists[0]->id, $checklists[1]->id]);

            $client = Client::firstOrCreate(
                ['email' => $data['email']],
                $data
            );
            $user = User::firstOrCreate(
                ['email' => $client->email],
                [
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'mobile' => $client->mobile,
                    'agency_id' => $agency->id,
                    'is_owner' => 0,
                    'password' => bcrypt('111111'),
                ]
            );
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
            $clients[] = $client;
        }

        /*
        |--------------------------------------------------------------------------
        | Short-Term Jobs (Babysitting / One-off)
        |--------------------------------------------------------------------------
        */
        $shortTermJobs = [];
        $stJobConfigs = [
            ['title' => 'Weekend Babysitting', 'status' => 'completed', 'clientIdx' => 0, 'candidateIdx' => 0],
            ['title' => 'Date Night Childcare', 'status' => 'completed', 'clientIdx' => 0, 'candidateIdx' => 1],
            ['title' => 'Morning Babysitting', 'status' => 'running', 'clientIdx' => 0, 'candidateIdx' => 2],
            ['title' => 'After School Care', 'status' => 'running', 'clientIdx' => 1, 'candidateIdx' => 2],
            ['title' => 'Weekend Morning Care', 'status' => 'running', 'clientIdx' => 1, 'candidateIdx' => 1],
            ['title' => 'School Pickup & Care', 'status' => 'running', 'clientIdx' => 2, 'candidateIdx' => 0],
            ['title' => 'Emergency Babysitting', 'status' => 'running', 'clientIdx' => 2, 'candidateIdx' => 2],
            ['title' => 'Evening Babysitter', 'status' => 'marketplace', 'clientIdx' => 2, 'candidateIdx' => null],
            ['title' => 'Full Day Childcare', 'status' => 'pending_payment', 'clientIdx' => 3, 'candidateIdx' => null],
            ['title' => 'Holiday Coverage', 'status' => 'completed', 'clientIdx' => 3, 'candidateIdx' => 0],
        ];

        foreach ($stJobConfigs as $config) {
            $client = $clients[$config['clientIdx']];
            $job = ShortTermJob::firstOrCreate(
                [
                    'agency_id' => $agency->id,
                    'client_id' => $client->id,
                    'title' => $config['title'],
                ],
                [
                    'candidate_id' => $config['candidateIdx'] !== null ? $candidates[$config['candidateIdx']]->id : null,
                    'location_id' => $locations[array_rand($locations)]->id,
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
                    'broadcast_requested' => in_array($config['status'], ['marketplace', 'pending_approval']),
                ]
            );

            $shortTermJobs[] = $job;
        }

        /*
        |--------------------------------------------------------------------------
        | Long-Term Jobs (Full-Time Nanny)
        |--------------------------------------------------------------------------
        */
        $longTermJobs = [];
        $ltJobConfigs = [
            [
                'title' => 'Full-Time Live-Out Nanny',
                'status' => 'running',
                'clientIdx' => 0, 'candidateIdx' => 0,
                'start' => '-30 days', 'end' => '+335 days',
            ],
            [
                'title' => 'Full-Time Live-Out Nanny',
                'status' => 'running',
                'clientIdx' => 1, 'candidateIdx' => 0,
                'start' => '-30 days', 'end' => '+335 days',
            ],
            [
                'title' => 'Summer Nanny',
                'status' => 'running',
                'clientIdx' => 2, 'candidateIdx' => 1,
                'start' => '-14 days', 'end' => '+76 days',
            ],
            [
                'title' => 'After-School Nanny',
                'status' => 'running',
                'clientIdx' => 3, 'candidateIdx' => 0,
                'start' => '-7 days', 'end' => '+180 days',
            ],
            [
                'title' => 'Part-Time Afternoon Nanny',
                'status' => 'marketplace',
                'clientIdx' => 2, 'candidateIdx' => null,
                'start' => '+14 days', 'end' => '+379 days',
            ],
            [
                'title' => 'Live-In Nanny for Newborn',
                'status' => 'pending_approval',
                'clientIdx' => 3, 'candidateIdx' => null,
                'start' => '+30 days', 'end' => '+395 days',
            ],
        ];

        foreach ($ltJobConfigs as $config) {
            $client = $clients[$config['clientIdx']];
            $job = LongTermJob::firstOrCreate(
                [
                    'agency_id' => $agency->id,
                    'client_id' => $client->id,
                    'title' => $config['title'],
                ],
                [
                    'candidate_id' => $config['candidateIdx'] !== null ? $candidates[$config['candidateIdx']]->id : null,
                    'location_id' => $locations[array_rand($locations)]->id,
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
                    'special_needs' => false,
                    'school_activity' => true,
                    'has_housekeeper' => true,
                    'live_in_required' => $config['clientIdx'] === 2,
                    'prepare_meals' => true,
                    'travel_with_family' => true,
                    'spouse_work_from_home' => 'yes_i_do',
                    'nanny_own_car' => 'yes',
                    'paid_vacation' => 'vacation_and_holidays',
                    'room_with_wifi' => $config['clientIdx'] === 2,
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
                    'broadcast_requested' => in_array($config['status'], ['marketplace', 'pending_approval']),
                ]
            );

            $longTermJobs[] = $job;
        }

        /*
        |--------------------------------------------------------------------------
        | Short-Term Job Dates & Children
        |--------------------------------------------------------------------------
        */
        foreach ($shortTermJobs as $job) {
            $existingDateCount = ShortTermJobDate::where('short_term_job_id', $job->id)->count();
            if ($existingDateCount === 0) {
                ShortTermJobDate::create([
                    'short_term_job_id' => $job->id,
                    'booking_date' => now()->addDays(rand(1, 14))->format('Y-m-d'),
                    'start_time' => '18:00:00',
                    'end_time' => '23:00:00',
                ]);

                if (in_array($job->status, ['running', 'completed'])) {
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

        /*
        |--------------------------------------------------------------------------
        | Long-Term Job Children & Schedules
        |--------------------------------------------------------------------------
        */
        foreach ($longTermJobs as $job) {
            $client = collect($clients)->firstWhere('id', $job->client_id);

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
                $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                foreach ($dayNames as $dayOfWeek) {
                    LongTermJobSchedule::create([
                        'long_term_job_id' => $job->id,
                        'day_of_week' => array_search($dayOfWeek, $dayNames) + 1,
                        'start_time' => '08:00:00',
                        'end_time' => '18:00:00',
                    ]);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Payments (for Short-Term Jobs)
        |--------------------------------------------------------------------------
        */
        $completedSTJobs = array_filter($shortTermJobs, fn ($j) => in_array($j->status, ['completed', 'running', 'pending_payment']));
        foreach ($completedSTJobs as $job) {
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

        /*
        |--------------------------------------------------------------------------
        | Long-Term Job Nanny Payments
        |--------------------------------------------------------------------------
        */
        $runningLTJobs = array_filter($longTermJobs, fn ($j) => $j->status === 'running' && $j->candidate_id);
        foreach ($runningLTJobs as $i => $job) {
            LongTermJobNannyPayment::firstOrCreate(
                ['invoice_number' => 'INV-'.now()->format('Ymd').'-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT)],
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

        /*
        |--------------------------------------------------------------------------
        | Attendance Records
        |--------------------------------------------------------------------------
        */
        $completedOrRunningSTJobs = array_filter($shortTermJobs, fn ($j) => in_array($j->status, ['completed', 'running']));
        foreach ($completedOrRunningSTJobs as $job) {
            if (! $job->candidate_id) {
                continue;
            }

            $bookingDate = now()->subDays(rand(1, 10))->format('Y-m-d');
            ShortTermJobAttendance::firstOrCreate(
                [
                    'short_term_job_id' => $job->id,
                    'candidate_id' => $job->candidate_id,
                    'booking_date' => $bookingDate,
                ],
                [
                    'check_in' => '09:00:00',
                    'check_out' => '17:00:00',
                    'notes' => 'On time, great with kids.',
                ]
            );
        }

        foreach ($runningLTJobs as $job) {
            foreach (range(0, 4) as $dayOffset) {
                $date = now()->subDays($dayOffset)->format('Y-m-d');
                LongTermJobAttendance::firstOrCreate(
                    [
                        'long_term_job_id' => $job->id,
                        'candidate_id' => $job->candidate_id,
                        'date' => $date,
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

        /*
        |--------------------------------------------------------------------------
        | Reviews
        |--------------------------------------------------------------------------
        */
        // Short-term job reviews
        $reviewableSTJobs = array_filter($shortTermJobs, fn ($j) => $j->status === 'completed' && $j->candidate_id);
        foreach ($reviewableSTJobs as $job) {
            ShortTermJobReview::firstOrCreate(
                [
                    'short_term_job_id' => $job->id,
                    'candidate_id' => $job->candidate_id,
                    'client_id' => $job->client_id,
                ],
                [
                    'agency_id' => $agency->id,
                    'rating' => rand(4, 5),
                    'review' => 'Maria was wonderful with our children. Very punctual and caring. Highly recommend!',
                ]
            );
        }

        foreach ($runningLTJobs as $job) {
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

        /*
        |--------------------------------------------------------------------------
        | Job Messages
        |--------------------------------------------------------------------------
        */
        foreach ($shortTermJobs as $job) {
            if (! $job->candidate_id) {
                continue;
            }

            $clientUser = User::where('email', $job->client->email)->first();
            $candidateUser = User::where('email', $job->candidate->email)->first();

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

            $clientUser = User::where('email', $job->client->email)->first();
            $candidateUser = User::where('email', $job->candidate->email)->first();

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

        /*
        |--------------------------------------------------------------------------
        | Long-Term Job Applications & Interviews
        |--------------------------------------------------------------------------
        */
        $marketplaceLTJobs = array_filter($longTermJobs, fn ($j) => $j->status === 'marketplace');
        $marketplaceLTJob = reset($marketplaceLTJobs) ?: null;
        if ($marketplaceLTJob) {
            $application = LongTermJobApplication::firstOrCreate(
                [
                    'long_term_job_id' => $marketplaceLTJob->id,
                    'candidate_id' => $candidates[0]->id,
                ],
                [
                    'agency_id' => $agency->id,
                    'application_message' => 'I am very interested in this position. I have 7 years of experience.',
                    'status' => 'pending',
                ]
            );

            LongTermJobApplication::firstOrCreate(
                [
                    'long_term_job_id' => $marketplaceLTJob->id,
                    'candidate_id' => $candidates[1]->id,
                ],
                [
                    'agency_id' => $agency->id,
                    'application_message' => 'I would love to work with your family. Available to start immediately.',
                    'status' => 'pending',
                ]
            );

            LongTermJobInterview::firstOrCreate(
                [
                    'long_term_job_id' => $marketplaceLTJob->id,
                    'long_term_job_application_id' => $application->id,
                ],
                [
                    'candidate_id' => $candidates[0]->id,
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

        /*
        |--------------------------------------------------------------------------
        | Candidate Job Requests
        |--------------------------------------------------------------------------
        */
        $stMarketplaceJobs = array_filter($shortTermJobs, fn ($j) => $j->status === 'marketplace');
        $stMarketplaceJob = reset($stMarketplaceJobs) ?: null;
        if ($stMarketplaceJob) {
            CandidateJobRequest::firstOrCreate(
                [
                    'client_id' => $stMarketplaceJob->client_id,
                    'candidate_id' => $candidates[1]->id,
                    'short_term_job_id' => $stMarketplaceJob->id,
                ],
                [
                    'agency_id' => $agency->id,
                    'job_type' => 'short_term',
                    'status' => 'pending',
                    'message' => 'Candidate is interested in this position.',
                ]
            );

            CandidateJobRequest::firstOrCreate(
                [
                    'client_id' => $stMarketplaceJob->client_id,
                    'candidate_id' => $candidates[2]->id,
                    'short_term_job_id' => $stMarketplaceJob->id,
                ],
                [
                    'agency_id' => $agency->id,
                    'job_type' => 'short_term',
                    'status' => 'pending',
                    'message' => 'Candidate is interested in this position.',
                ]
            );
        }

        $ltApprovedJobs = array_filter($longTermJobs, fn ($j) => $j->status === 'running' && $j->candidate_id);
        $ltApprovedJob = reset($ltApprovedJobs) ?: null;
        if ($ltApprovedJob) {
            CandidateJobRequest::firstOrCreate(
                [
                    'client_id' => $ltApprovedJob->client_id,
                    'candidate_id' => $ltApprovedJob->candidate_id,
                    'long_term_job_id' => $ltApprovedJob->id,
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

        /*
        |--------------------------------------------------------------------------
        | Events
        |--------------------------------------------------------------------------
        */
        foreach ($clients as $index => $client) {
            Event::firstOrCreate(
                [
                    'event_title' => 'Client Consultation - '.$client->first_name.' '.$client->last_name,
                    'client_id' => $client->id,
                ],
                [
                    'agency_id' => $agency->id,
                    'event_type_id' => $eventTypes[$index % count($eventTypes)]->id,
                    'event_date' => now()->addDays(rand(3, 30))->format('Y-m-d'),
                    'event_time' => '10:00:00',
                    'location' => $locations[$index % count($locations)]->location,
                    'note' => 'Initial consultation meeting with the family.',
                    'is_email_notify' => true,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Client Documents
        |--------------------------------------------------------------------------
        */
        foreach ($clients as $index => $client) {
            ClientDocument::firstOrCreate(
                [
                    'client_id' => $client->id,
                    'title' => 'Service Agreement - '.$client->first_name.' '.$client->last_name,
                ],
                [
                    'agency_id' => $agency->id,
                    'description' => 'Standard service agreement contract',
                    'status' => $index === 2 ? 'pending' : 'signed',
                    'signed_at' => $index === 2 ? null : now()->subDays(rand(5, 30)),
                    'signed_ip' => '192.168.1.'.rand(10, 200),
                    'metadata' => json_encode(['signed_from' => 'email']),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Candidate Client Reports
        |--------------------------------------------------------------------------
        */
        foreach ($shortTermJobs as $job) {
            if (! $job->candidate_id) {
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
                    'resolved_at' => $job->status === 'completed' ? now()->subDays(2) : null,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Long-Term Job Refund Requests
        |--------------------------------------------------------------------------
        */
        LongTermJobRefundRequest::firstOrCreate(
            ['long_term_job_id' => $longTermJobs[0]->id],
            [
                'client_id' => $longTermJobs[0]->client_id,
                'agency_id' => $agency->id,
                'reason' => 'Overcharged for the first month due to billing cycle miscalculation.',
                'status' => 'approved',
                'agency_note' => 'Refund has been processed. Credit will appear in next statement.',
                'resolved_at' => now()->subDays(5),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Update Agency Counters
        |--------------------------------------------------------------------------
        */
        $agency->update([
            'total_clients' => Client::where('agency_id', $agency->id)->count(),
            'total_candidates' => Candidate::where('agency_id', $agency->id)->count(),
        ]);

        $this->command->info('Client Dashboard seeder completed successfully!');
        $this->command->info('Created: '.count($clients).' clients, '.count($candidates).' candidates');
        $this->command->info('Created: '.count($shortTermJobs).' short-term jobs, '.count($longTermJobs).' long-term jobs');
    }
}
