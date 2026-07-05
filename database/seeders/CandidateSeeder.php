<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\CandidateAvailability;
use App\Models\CandidateAvailabilityDay;
use App\Models\CandidateDocument;
use App\Models\CandidateUnavailability;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateField;
use App\Models\DocumentTemplateSigner;
use App\Models\Location;
use App\Models\Type;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Seeds the full candidate roster for the agency. This is the single source
 * of candidates — JobSeeder references the candidates created here so every
 * candidate ends up tied to real jobs, applications and documents.
 */
class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        $role = Role::where('name', 'candidate')->where('guard_name', 'api')->first();

        $candidateTypes = Type::where('agency_id', $agency->id)->where('type', 'candidate')->orderBy('id')->get();
        $locations = Location::where('agency_id', $agency->id)->orderBy('id')->get();

        if ($candidateTypes->isEmpty() || $locations->isEmpty()) {
            $this->command->error('Supporting data not found. Run SupportingDataSeeder first.');

            return;
        }

        $candidatesData = [
            [
                'first_name' => 'Maria', 'last_name' => 'Garcia',
                'email' => 'candidate@gmail.com', 'mobile' => '+1 305 111 0001',
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
                'available_for' => ['full_time', 'live_out'],
                'interested_in_iowa' => false,
                'start_date' => now()->subYears(4)->format('Y-m-d'),
                'last_position_end_reason' => 'Family relocated abroad',
                'reference_first_name' => 'Carla', 'reference_last_name' => 'Lopez',
                'reference_phone' => '+1 305 555 0111', 'reference_email' => 'carla.lopez@example.com',
                'reference_relation' => 'Former Employer', 'reference_description' => 'Cared for 2 children over 4 years.',
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
                'available_for' => ['part_time', 'date_nights', 'weekends'],
                'interested_in_iowa' => false,
                'start_date' => now()->subYears(2)->format('Y-m-d'),
                'last_position_end_reason' => 'Returned to school',
                'reference_first_name' => 'Megan', 'reference_last_name' => 'Foster',
                'reference_phone' => '+1 305 555 0112', 'reference_email' => 'megan.foster@example.com',
                'reference_relation' => 'Former Employer', 'reference_description' => 'Reliable weekend sitter for 2 years.',
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
                'available_for' => ['temporary', 'short_term', 'travel'],
                'interested_in_iowa' => true,
                'start_date' => now()->subYears(6)->format('Y-m-d'),
                'last_position_end_reason' => 'Prefers temporary assignments',
                'reference_first_name' => 'Helen', 'reference_last_name' => 'Park',
                'reference_phone' => '+1 305 555 0113', 'reference_email' => 'helen.park@example.com',
                'reference_relation' => 'Agency Director', 'reference_description' => 'Placed with 6+ families successfully.',
            ],
            [
                'first_name' => 'Jennifer', 'last_name' => 'Martinez',
                'email' => 'jennifer.martinez@example.com', 'mobile' => '+1 305 111 0004',
                'date_of_birth' => '1996-09-12', 'nationality' => 'US',
                'street_address' => '555 Palm Dr', 'city' => 'Miami', 'province' => 'FL',
                'postal_code' => '33125', 'country' => 'US',
                'years_of_experience' => '5-10', 'commitment' => 'long_term',
                'pay_range_per_hour' => '22-28', 'hours_per_week' => '40',
                'bilingual' => 'Spanish', 'drivers_license' => 'dl_and_car',
                'cpr_first_aid' => 'yes', 'vaccinations' => 'yes',
                'ok_with_pets' => 'dog', 'ok_with_travel' => 'domestic',
                'work_legally_in_us' => true, 'comfortable_paid_legally' => true, 'has_ssn' => true,
                'hear_about_us' => 'Agency Website',
                'available_for' => ['full_time', 'live_out'],
                'interested_in_iowa' => false,
                'start_date' => now()->subYears(2)->format('Y-m-d'),
                'last_position_end_reason' => 'Family moved out of state',
                'reference_first_name' => 'Amanda', 'reference_last_name' => 'Smith',
                'reference_phone' => '+1 305 555 0101', 'reference_email' => 'amanda.smith@example.com',
                'reference_relation' => 'Former Employer', 'reference_description' => 'Worked for 3 years as full-time nanny.',
            ],
            [
                'first_name' => 'Ashley', 'last_name' => 'Brown',
                'email' => 'ashley.brown@example.com', 'mobile' => '+1 305 111 0005',
                'date_of_birth' => '2000-05-18', 'nationality' => 'US',
                'street_address' => '777 Coral Way', 'city' => 'Coral Gables', 'province' => 'FL',
                'postal_code' => '33134', 'country' => 'US',
                'years_of_experience' => '2-5', 'commitment' => 'short_term',
                'pay_range_per_hour' => '16-20', 'hours_per_week' => '25',
                'bilingual' => 'None', 'drivers_license' => 'dl_only',
                'cpr_first_aid' => 'willing', 'vaccinations' => 'yes',
                'ok_with_pets' => 'cat', 'ok_with_travel' => 'no_travel',
                'work_legally_in_us' => true, 'comfortable_paid_legally' => true, 'has_ssn' => true,
                'hear_about_us' => 'Friend',
                'available_for' => ['part_time', 'date_nights', 'weekends'],
                'interested_in_iowa' => false,
                'start_date' => now()->subYear()->format('Y-m-d'),
                'last_position_end_reason' => 'Completed seasonal position',
                'reference_first_name' => 'Rachel', 'reference_last_name' => 'Green',
                'reference_phone' => '+1 305 555 0102', 'reference_email' => 'rachel.green@example.com',
                'reference_relation' => 'Former Employer', 'reference_description' => 'Babysat for 2 years on weekends.',
            ],
            [
                'first_name' => 'Stephanie', 'last_name' => 'Wilson',
                'email' => 'stephanie.wilson@example.com', 'mobile' => '+1 305 111 0006',
                'date_of_birth' => '1991-11-30', 'nationality' => 'US',
                'street_address' => '222 Bayshore Dr', 'city' => 'Key Biscayne', 'province' => 'FL',
                'postal_code' => '33149', 'country' => 'US',
                'years_of_experience' => '10+', 'commitment' => 'temporary',
                'pay_range_per_hour' => '28-35', 'hours_per_week' => '20',
                'bilingual' => 'Portuguese', 'drivers_license' => 'dl_and_car',
                'cpr_first_aid' => 'yes', 'vaccinations' => 'yes',
                'ok_with_pets' => 'neither', 'ok_with_travel' => 'international',
                'work_legally_in_us' => true, 'comfortable_paid_legally' => true, 'has_ssn' => true,
                'hear_about_us' => 'Referral',
                'available_for' => ['temporary', 'short_term', 'travel'],
                'interested_in_iowa' => true,
                'start_date' => now()->subYears(5)->format('Y-m-d'),
                'last_position_end_reason' => 'Wanted to explore temporary assignments',
                'reference_first_name' => 'Patricia', 'reference_last_name' => 'Adams',
                'reference_phone' => '+1 305 555 0103', 'reference_email' => 'patricia.adams@example.com',
                'reference_relation' => 'Agency Director', 'reference_description' => 'Placed Stephanie with 5+ families.',
            ],
            [
                'first_name' => 'Nicole', 'last_name' => 'Taylor',
                'email' => 'nicole.taylor@example.com', 'mobile' => '+1 305 111 0007',
                'date_of_birth' => '1994-07-08', 'nationality' => 'US',
                'street_address' => '444 Ocean Blvd', 'city' => 'Fort Lauderdale', 'province' => 'FL',
                'postal_code' => '33301', 'country' => 'US',
                'years_of_experience' => '5-10', 'commitment' => 'long_term',
                'pay_range_per_hour' => '20-26', 'hours_per_week' => '35',
                'bilingual' => 'French', 'drivers_license' => 'dl_and_car',
                'cpr_first_aid' => 'yes', 'vaccinations' => 'yes',
                'ok_with_pets' => 'dog', 'ok_with_travel' => 'domestic',
                'work_legally_in_us' => true, 'comfortable_paid_legally' => true, 'has_ssn' => true,
                'hear_about_us' => 'Google',
                'available_for' => ['full_time', 'live_in', 'live_out'],
                'interested_in_iowa' => false,
                'start_date' => now()->subYears(3)->format('Y-m-d'),
                'last_position_end_reason' => 'Contract ended',
                'reference_first_name' => 'Monica', 'reference_last_name' => 'Geller',
                'reference_phone' => '+1 305 555 0104', 'reference_email' => 'monica.geller@example.com',
                'reference_relation' => 'Former Employer', 'reference_description' => 'Excellent care for 2 infants.',
            ],
            [
                'first_name' => 'Amanda', 'last_name' => 'Clark',
                'email' => 'amanda.clark@example.com', 'mobile' => '+1 305 111 0008',
                'date_of_birth' => '1997-02-14', 'nationality' => 'US',
                'street_address' => '888 Sunrise Ave', 'city' => 'Miami Beach', 'province' => 'FL',
                'postal_code' => '33139', 'country' => 'US',
                'years_of_experience' => '2-5', 'commitment' => 'long_term',
                'pay_range_per_hour' => '18-24', 'hours_per_week' => '40',
                'bilingual' => 'Spanish', 'drivers_license' => 'neither',
                'cpr_first_aid' => 'yes', 'vaccinations' => 'yes',
                'ok_with_pets' => 'dog', 'ok_with_travel' => 'no_travel',
                'work_legally_in_us' => true, 'comfortable_paid_legally' => true, 'has_ssn' => true,
                'hear_about_us' => 'Social Media',
                'available_for' => ['full_time', 'live_out'],
                'interested_in_iowa' => false,
                'start_date' => now()->subMonths(8)->format('Y-m-d'),
                'last_position_end_reason' => 'Seeking better opportunity',
                'reference_first_name' => 'Lisa', 'reference_last_name' => 'Kudrow',
                'reference_phone' => '+1 305 555 0105', 'reference_email' => 'lisa.kudrow@example.com',
                'reference_relation' => 'Former Employer', 'reference_description' => 'Reliable and caring nanny for 1 year.',
            ],
        ];

        $candidates = [];
        foreach ($candidatesData as $index => $data) {
            $data['agency_id'] = $agency->id;
            $data['type_id'] = [$candidateTypes[$index % $candidateTypes->count()]->id];
            $data['location_id'] = [$locations[$index % $locations->count()]->id];

            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'mobile' => $data['mobile'],
                    'agency_id' => $agency->id,
                    'is_owner' => 0,
                    'password' => bcrypt('111111'),
                ]
            );

            if ($role && ! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            $data['user_id'] = $user->id;

            $candidate = Candidate::firstOrCreate(
                ['email' => $data['email']],
                $data
            );

            $candidates[] = $candidate;
        }

        $this->seedAvailability($candidates);
        $this->seedUnavailability($candidates);
        $this->seedDocuments($agency, $candidates);

        $this->command->info('Candidate seeder completed successfully!');
        $this->command->info('Created/ensured: '.count($candidates).' candidates');
    }

    /**
     * @param  array<int, Candidate>  $candidates
     */
    private function seedAvailability(array $candidates): void
    {
        $timezones = ['America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles'];

        foreach ($candidates as $index => $candidate) {
            $availability = CandidateAvailability::firstOrCreate(
                ['candidate_id' => $candidate->id],
                ['timezone' => $timezones[$index % count($timezones)]]
            );

            if (CandidateAvailabilityDay::where('candidate_availability_id', $availability->id)->count() > 0) {
                continue;
            }

            $days = [
                ['day_of_week' => 1, 'is_available' => true, 'start_time' => '08:00:00', 'end_time' => '18:00:00'],
                ['day_of_week' => 2, 'is_available' => true, 'start_time' => '08:00:00', 'end_time' => '18:00:00'],
                ['day_of_week' => 3, 'is_available' => true, 'start_time' => '08:00:00', 'end_time' => '18:00:00'],
                ['day_of_week' => 4, 'is_available' => true, 'start_time' => '08:00:00', 'end_time' => '18:00:00'],
                ['day_of_week' => 5, 'is_available' => true, 'start_time' => '08:00:00', 'end_time' => '16:00:00'],
                ['day_of_week' => 6, 'is_available' => $index % 2 === 0, 'start_time' => '10:00:00', 'end_time' => '16:00:00'],
                ['day_of_week' => 0, 'is_available' => false, 'start_time' => null, 'end_time' => null],
            ];

            foreach ($days as $day) {
                CandidateAvailabilityDay::create(array_merge(
                    ['candidate_availability_id' => $availability->id],
                    $day
                ));
            }
        }
    }

    /**
     * @param  array<int, Candidate>  $candidates
     */
    private function seedUnavailability(array $candidates): void
    {
        $unavailabilityData = [
            ['candidateIdx' => 0, 'title' => 'Vacation', 'start' => '+30 days', 'end' => '+37 days'],
            ['candidateIdx' => 1, 'title' => 'Doctor Appointment', 'start' => '+5 days', 'end' => '+5 days'],
            ['candidateIdx' => 2, 'title' => 'Family Event', 'start' => '+14 days', 'end' => '+16 days'],
            ['candidateIdx' => 3, 'title' => 'Moving', 'start' => '+60 days', 'end' => '+62 days'],
            ['candidateIdx' => 4, 'title' => 'Holiday', 'start' => '+20 days', 'end' => '+24 days'],
            ['candidateIdx' => 5, 'title' => 'Conference', 'start' => '+45 days', 'end' => '+47 days'],
            ['candidateIdx' => 6, 'title' => 'Personal Leave', 'start' => '+10 days', 'end' => '+12 days'],
            ['candidateIdx' => 7, 'title' => 'Travel', 'start' => '+25 days', 'end' => '+30 days'],
        ];

        foreach ($unavailabilityData as $data) {
            if (! isset($candidates[$data['candidateIdx']])) {
                continue;
            }

            CandidateUnavailability::firstOrCreate(
                [
                    'candidate_id' => $candidates[$data['candidateIdx']]->id,
                    'title' => $data['title'],
                    'start_date' => now()->modify($data['start'])->format('Y-m-d'),
                ],
                [
                    'end_date' => now()->modify($data['end'])->format('Y-m-d'),
                ]
            );
        }
    }

    /**
     * @param  array<int, Candidate>  $candidates
     */
    private function seedDocuments(Agency $agency, array $candidates): void
    {
        $candidateTemplate = DocumentTemplate::firstOrCreate(
            [
                'agency_id' => $agency->id,
                'name' => 'Candidate Service Agreement',
                'user_type' => 'candidate',
            ],
            [
                'content_type' => 'text',
                'content' => 'This agreement is between [AGENCY_NAME] and [CANDIDATE_NAME] for placement as a childcare provider. The candidate agrees to abide by agency policies and code of conduct.',
                'org_signer_name' => $agency->name.' Representative',
                'org_name' => $agency->name,
            ]
        );

        $candidateFields = [
            ['field_type' => 'text', 'field_label' => 'Full Name', 'field_tag' => 'candidate_name', 'is_required' => true],
            ['field_type' => 'text', 'field_label' => 'Email', 'field_tag' => 'candidate_email', 'is_required' => true],
            ['field_type' => 'text', 'field_label' => 'Phone', 'field_tag' => 'candidate_phone', 'is_required' => true],
            ['field_type' => 'date', 'field_label' => 'Available Start Date', 'field_tag' => 'available_date', 'is_required' => true],
            ['field_type' => 'textarea', 'field_label' => 'Certifications & Training', 'field_tag' => 'certifications', 'is_required' => false],
            ['field_type' => 'signature', 'field_label' => 'Candidate Signature', 'field_tag' => 'candidate_signature', 'is_required' => true],
            ['field_type' => 'signature', 'field_label' => 'Agency Signature', 'field_tag' => 'agency_signature', 'is_required' => true],
        ];

        foreach ($candidateFields as $field) {
            DocumentTemplateField::firstOrCreate(
                [
                    'document_template_id' => $candidateTemplate->id,
                    'field_tag' => $field['field_tag'],
                ],
                $field
            );
        }

        foreach (['Candidate', 'Agency Representative'] as $label) {
            DocumentTemplateSigner::firstOrCreate(
                [
                    'document_template_id' => $candidateTemplate->id,
                    'signer_label' => $label,
                ]
            );
        }

        $requiredDocuments = [
            ['key' => 'resume', 'status' => 'uploaded', 'mime_type' => 'application/pdf', 'extension' => 'pdf'],
            ['key' => 'certification', 'status' => 'uploaded', 'mime_type' => 'image/jpeg', 'extension' => 'jpg'],
            ['key' => 'background_check', 'status' => 'pending', 'mime_type' => null, 'extension' => null],
        ];

        foreach ($candidates as $candidate) {
            foreach ($requiredDocuments as $document) {
                $isUploaded = $document['status'] === 'uploaded';
                $slug = strtolower($candidate->first_name.'-'.$candidate->last_name.'-'.$document['key']);
                $fileName = $slug.'.'.($document['extension'] ?? 'pdf');

                CandidateDocument::firstOrCreate(
                    [
                        'candidate_id' => $candidate->id,
                        'required_key' => $document['key'],
                    ],
                    [
                        'agency_id' => $agency->id,
                        'category' => 'required',
                        'title' => ucwords(str_replace('_', ' ', $document['key'])),
                        'description' => 'Required '.$document['key'].' document for candidate.',
                        'file_path' => $isUploaded ? 'candidate-documents/'.$candidate->id.'/'.$fileName : null,
                        'original_file_name' => $isUploaded ? $fileName : null,
                        'mime_type' => $document['mime_type'],
                        'size' => $isUploaded ? rand(150000, 950000) : null,
                        'status' => $document['status'],
                        'metadata' => json_encode(['upload_required' => true]),
                    ]
                );
            }

            $agreementFileName = strtolower($candidate->first_name.'-'.$candidate->last_name).'-service-agreement.pdf';

            CandidateDocument::firstOrCreate(
                [
                    'candidate_id' => $candidate->id,
                    'document_template_id' => $candidateTemplate->id,
                ],
                [
                    'agency_id' => $agency->id,
                    'category' => 'agreement',
                    'title' => $candidateTemplate->name.' - '.$candidate->first_name.' '.$candidate->last_name,
                    'description' => $candidateTemplate->content,
                    'file_path' => 'candidate-documents/'.$candidate->id.'/'.$agreementFileName,
                    'original_file_name' => $agreementFileName,
                    'mime_type' => 'application/pdf',
                    'size' => rand(200000, 600000),
                    'status' => 'signed',
                    'signed_at' => now()->subDays(rand(5, 60)),
                    'metadata' => json_encode(['sent_via' => 'email', 'template_name' => $candidateTemplate->name]),
                ]
            );
        }
    }
}
