<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Location;
use App\Models\Type;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CandidateFormSubmissionSeeder extends Seeder
{
    private array $typeNames = [];

    private array $locationNames = [];

    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        $this->typeNames = Type::where('agency_id', $agency->id)
            ->where('type', 'candidate')
            ->pluck('name', 'id')
            ->toArray();

        $this->locationNames = Location::where('agency_id', $agency->id)
            ->pluck('location', 'id')
            ->toArray();

        $form = $this->ensureForm($agency);

        $candidates = Candidate::where('agency_id', $agency->id)->get();

        foreach ($candidates as $candidate) {
            $this->seedSubmission($form, $candidate);
        }

        $this->command->info('Candidate form submission seeder completed successfully!');
        $this->command->info('Created/ensured submissions for: '.$candidates->count().' candidates');
    }

    private function ensureForm(Agency $agency): Form
    {
        $schema = [
            'blocks' => [
                [
                    'name' => 'Basic Information',
                    'description' => null,
                    'sections' => [
                        [
                            'name' => 'Basic Information',
                            'fields' => [
                                ['key' => 'first_name', 'label' => 'First Name', 'type' => 'text_box', 'is_required' => true, 'width' => 6],
                                ['key' => 'last_name', 'label' => 'Last Name', 'type' => 'text_box', 'is_required' => false, 'width' => 6],
                                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'is_required' => true, 'width' => 6],
                                ['key' => 'image', 'label' => 'Profile Image', 'type' => 'file_upload', 'is_required' => false, 'width' => 6],
                                ['key' => 'type_id', 'label' => 'User Type', 'type' => 'multi_select_checkbox', 'is_required' => false, 'width' => 6, 'options' => array_values($this->typeNames)],
                                ['key' => 'location_id', 'label' => 'Location', 'type' => 'multi_select_checkbox', 'is_required' => false, 'width' => 6, 'options' => array_values($this->locationNames)],
                                ['key' => 'mobile', 'label' => 'Phone Number', 'type' => 'text_box', 'is_required' => false, 'width' => 6],
                                ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'text_box', 'is_required' => false, 'width' => 6],
                                ['key' => 'street_address', 'label' => 'Street Address', 'type' => 'text_box', 'is_required' => false, 'width' => 6],
                                ['key' => 'city', 'label' => 'City', 'type' => 'text_box', 'is_required' => false, 'width' => 6],
                                ['key' => 'province', 'label' => 'Province/State', 'type' => 'text_box', 'is_required' => false, 'width' => 6],
                                ['key' => 'postal_code', 'label' => 'Postal Code', 'type' => 'text_box', 'is_required' => false, 'width' => 6],
                                ['key' => 'country', 'label' => 'Country', 'type' => 'text_box', 'is_required' => false, 'width' => 6],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Professional Information',
                    'description' => 'Enter your Professional information',
                    'sections' => [
                        [
                            'name' => 'Professional Information',
                            'fields' => [
                                ['key' => 'experience', 'label' => 'Years of Experience', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter your Experience'],
                                ['key' => 'position', 'label' => 'Position', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter your Position'],
                                ['key' => 'employment_type', 'label' => 'Employment Type', 'type' => 'radio', 'is_required' => true, 'width' => 6, 'options' => ['Full-Time', 'Part-Time', 'Contract']],
                                ['key' => 'skills', 'label' => 'Skills', 'type' => 'multi_select_checkbox', 'is_required' => false, 'width' => 6, 'options' => ['PHP', 'Laravel', 'React', 'Vue']],
                                ['key' => 'agree_terms', 'label' => 'I agree to the terms', 'type' => 'single_checkbox', 'is_required' => true, 'width' => 12],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'References',
                    'description' => 'Enter your References information',
                    'sections' => [
                        [
                            'name' => 'References Information',
                            'fields' => [
                                ['key' => 'referer_name', 'label' => 'Referer Name', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter your Referar name'],
                                ['key' => 'referar_email', 'label' => 'Referar Email', 'type' => 'email', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter email'],
                                ['key' => 'referer_phone', 'label' => 'Referer Phone', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter Referar phone'],
                                ['key' => 'referer_relation', 'label' => 'Referer Relation', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter Referar relationship'],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Family Information',
                    'description' => 'Enter your Family information',
                    'sections' => [
                        [
                            'name' => 'Family Information',
                            'fields' => [
                                ['key' => 'father_name', 'label' => 'Father Name', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter your father name'],
                                ['key' => 'mother_name', 'label' => 'Mother Name', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter your mother name'],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Documents',
                    'description' => 'Upload your required documents',
                    'sections' => [
                        [
                            'name' => 'Identity & Certification Documents',
                            'fields' => [
                                ['key' => 'resume', 'label' => 'Resume', 'type' => 'file_upload', 'is_required' => true, 'width' => 6, 'placeholder' => 'Upload your resume'],
                                ['key' => 'nid', 'label' => 'NID', 'type' => 'file_upload', 'is_required' => true, 'width' => 6, 'placeholder' => 'Upload your NID'],
                                ['key' => 'birth_certificate', 'label' => 'Birth Certificate', 'type' => 'file_upload', 'is_required' => false, 'width' => 6, 'placeholder' => 'Upload your birth certificate'],
                                ['key' => 'driving_licence', 'label' => 'Driving Licence', 'type' => 'file_upload', 'is_required' => false, 'width' => 6, 'placeholder' => 'Upload your driving licence'],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Additional Information',
                    'description' => 'Some Additional information',
                    'sections' => [
                        [
                            'name' => 'Additional Information',
                            'fields' => [
                                ['key' => 'hear_about_us', 'label' => 'How did you hear about us?', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'How did you hear about us?'],
                                ['key' => 'cpr_aid', 'label' => 'Are you CPR and First Aid certified', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Are you CPR and First Aid certified'],
                                ['key' => 'ok_with_pet', 'label' => 'OK with pets in the home?', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'OK with pets in the home?'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return Form::firstOrCreate(
            ['agency_id' => $agency->id, 'name' => 'Candidate Registration Form'],
            [
                'slug' => Str::slug('Candidate Registration Form'),
                'entity' => 'candidate',
                'application_type' => 'registration',
                'user_type' => 'candidate',
                'schema' => $schema,
                'status' => true,
            ]
        );
    }

    private function seedSubmission(Form $form, Candidate $candidate): void
    {
        $slug = Str::slug($candidate->first_name.'-'.$candidate->last_name);

        $blocks = [
            [
                'name' => 'Basic Information',
                'description' => null,
                'sections' => [
                    [
                        'name' => 'Basic Information',
                        'fields' => [
                            [
                                'key' => 'first_name',
                                'label' => 'First Name',
                                'type' => 'text_box',
                                'is_required' => true,
                                'value' => $candidate->first_name,
                            ],
                            [
                                'key' => 'last_name',
                                'label' => 'Last Name',
                                'type' => 'text_box',
                                'is_required' => false,
                                'value' => $candidate->last_name,
                            ],
                            [
                                'key' => 'email',
                                'label' => 'Email',
                                'type' => 'email',
                                'is_required' => true,
                                'value' => $candidate->email,
                            ],
                            [
                                'key' => 'image',
                                'label' => 'Profile Image',
                                'type' => 'file_upload',
                                'is_required' => false,
                                'value' => $candidate->image
                                    ? asset('storage/'.$candidate->image)
                                    : "http://127.0.0.1:8000/storage/candidates/{$slug}.jpg",
                            ],
                            [
                                'key' => 'type_id',
                                'label' => 'User Type',
                                'type' => 'multi_select_checkbox',
                                'is_required' => false,
                                'value' => $this->resolveTypeIds($candidate->type_id),
                            ],
                            [
                                'key' => 'location_id',
                                'label' => 'Location',
                                'type' => 'multi_select_checkbox',
                                'is_required' => false,
                                'value' => $this->resolveLocationIds($candidate->location_id),
                            ],
                            [
                                'key' => 'mobile',
                                'label' => 'Phone Number',
                                'type' => 'text_box',
                                'is_required' => false,
                                'value' => $candidate->mobile,
                            ],
                            [
                                'key' => 'nationality',
                                'label' => 'Nationality',
                                'type' => 'text_box',
                                'is_required' => false,
                                'value' => $candidate->nationality,
                            ],
                            [
                                'key' => 'street_address',
                                'label' => 'Street Address',
                                'type' => 'text_box',
                                'is_required' => false,
                                'value' => $candidate->street_address,
                            ],
                            [
                                'key' => 'city',
                                'label' => 'City',
                                'type' => 'text_box',
                                'is_required' => false,
                                'value' => $candidate->city,
                            ],
                            [
                                'key' => 'province',
                                'label' => 'Province/State',
                                'type' => 'text_box',
                                'is_required' => false,
                                'value' => $candidate->province,
                            ],
                            [
                                'key' => 'postal_code',
                                'label' => 'Postal Code',
                                'type' => 'text_box',
                                'is_required' => false,
                                'value' => $candidate->postal_code,
                            ],
                            [
                                'key' => 'country',
                                'label' => 'Country',
                                'type' => 'text_box',
                                'is_required' => false,
                                'value' => $candidate->country,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Professional Information',
                'description' => 'Enter your Professional information',
                'sections' => [
                    [
                        'name' => 'Professional Information',
                        'fields' => [
                            [
                                'key' => 'experience',
                                'label' => 'Years of Experience',
                                'type' => 'text_box',
                                'placeholder' => 'Enter your Experience',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => $candidate->years_of_experience,
                            ],
                            [
                                'key' => 'position',
                                'label' => 'Position',
                                'type' => 'text_box',
                                'placeholder' => 'Enter your Position',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => null,
                            ],
                            [
                                'key' => 'employment_type',
                                'label' => 'Employment Type',
                                'type' => 'radio',
                                'placeholder' => null,
                                'is_required' => true,
                                'width' => 6,
                                'options' => ['Full-Time', 'Part-Time', 'Contract'],
                                'value' => $this->resolveEmploymentType($candidate->commitment),
                            ],
                            [
                                'key' => 'skills',
                                'label' => 'Skills',
                                'type' => 'multi_select_checkbox',
                                'placeholder' => null,
                                'is_required' => false,
                                'width' => 6,
                                'options' => ['PHP', 'Laravel', 'React', 'Vue'],
                                'value' => $this->resolveSkills($candidate),
                            ],
                            [
                                'key' => 'agree_terms',
                                'label' => 'I agree to the terms',
                                'type' => 'single_checkbox',
                                'placeholder' => null,
                                'is_required' => true,
                                'width' => 12,
                                'options' => null,
                                'value' => 'true',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'References',
                'description' => 'Enter your References information',
                'sections' => [
                    [
                        'name' => 'References Information',
                        'fields' => [
                            [
                                'key' => 'referer_name',
                                'label' => 'Referer Name',
                                'type' => 'text_box',
                                'placeholder' => 'Enter your Referar name',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => trim($candidate->reference_first_name.' '.$candidate->reference_last_name) ?: null,
                            ],
                            [
                                'key' => 'referar_email',
                                'label' => 'Referar Email',
                                'type' => 'email',
                                'placeholder' => 'Enter email',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => $candidate->reference_email,
                            ],
                            [
                                'key' => 'referer_phone',
                                'label' => 'Referer Phone',
                                'type' => 'text_box',
                                'placeholder' => 'Enter Referar phone',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => $candidate->reference_phone,
                            ],
                            [
                                'key' => 'referer_relation',
                                'label' => 'Referer Relation',
                                'type' => 'text_box',
                                'placeholder' => 'Enter Referar relationship',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => $candidate->reference_relation,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Family Information',
                'description' => 'Enter your Family information',
                'sections' => [
                    [
                        'name' => 'Family Information',
                        'fields' => [
                            [
                                'key' => 'father_name',
                                'label' => 'Father Name',
                                'type' => 'text_box',
                                'placeholder' => 'Enter your father name',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => null,
                            ],
                            [
                                'key' => 'mother_name',
                                'label' => 'Mother Name',
                                'type' => 'text_box',
                                'placeholder' => 'Enter your mother name',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => null,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Documents',
                'description' => 'Upload your required documents',
                'sections' => [
                    [
                        'name' => 'Identity & Certification Documents',
                        'fields' => [
                            [
                                'key' => 'resume',
                                'label' => 'Resume',
                                'type' => 'file_upload',
                                'placeholder' => 'Upload your resume',
                                'is_required' => true,
                                'width' => 6,
                                'options' => null,
                                'value' => "http://127.0.0.1:8000/storage/form-submissions/candidate/{$slug}-resume.pdf",
                            ],
                            [
                                'key' => 'nid',
                                'label' => 'NID',
                                'type' => 'file_upload',
                                'placeholder' => 'Upload your NID',
                                'is_required' => true,
                                'width' => 6,
                                'options' => null,
                                'value' => "http://127.0.0.1:8000/storage/form-submissions/candidate/{$slug}-nid.jpg",
                            ],
                            [
                                'key' => 'birth_certificate',
                                'label' => 'Birth Certificate',
                                'type' => 'file_upload',
                                'placeholder' => 'Upload your birth certificate',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => "http://127.0.0.1:8000/storage/form-submissions/candidate/{$slug}-birth.jpg",
                            ],
                            [
                                'key' => 'driving_licence',
                                'label' => 'Driving Licence',
                                'type' => 'file_upload',
                                'placeholder' => 'Upload your driving licence',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => "http://127.0.0.1:8000/storage/form-submissions/candidate/{$slug}-licence.png",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Additional Information',
                'description' => 'Some Additional information',
                'sections' => [
                    [
                        'name' => 'Additional Information',
                        'fields' => [
                            [
                                'key' => 'hear_about_us',
                                'label' => 'How did you hear about us?',
                                'type' => 'text_box',
                                'placeholder' => 'How did you hear about us?',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => $candidate->hear_about_us,
                            ],
                            [
                                'key' => 'cpr_aid',
                                'label' => 'Are you CPR and First Aid certified',
                                'type' => 'text_box',
                                'placeholder' => 'Are you CPR and First Aid certified',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => $candidate->cpr_first_aid,
                            ],
                            [
                                'key' => 'ok_with_pet',
                                'label' => 'OK with pets in the home?',
                                'type' => 'text_box',
                                'placeholder' => 'OK with pets in the home?',
                                'is_required' => false,
                                'width' => 6,
                                'options' => null,
                                'value' => $candidate->ok_with_pets,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $existing = FormSubmission::where('form_id', $form->id)
            ->where('entity_id', $candidate->id)
            ->where('entity_type', 'candidate')
            ->first();

        if ($existing) {
            $data = $existing->data;
            if (isset($data['data'])) {
                $data['data']['id'] = $existing->id;
                $existing->update(['data' => $data]);
            }

            return;
        }

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $candidate->id,
            'entity_type' => 'candidate',
            'data' => [
                'status' => true,
                'data' => [
                    'id' => null,
                    'form_id' => $form->id,
                    'form_name' => $form->name,
                    'blocks' => $blocks,
                ],
            ],
        ]);

        $data = $submission->data;
        $data['data']['id'] = $submission->id;
        $submission->update(['data' => $data]);
    }

    private function resolveTypeIds(?array $typeIds): array
    {
        if (empty($typeIds)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($id) => $this->typeNames[(int) $id] ?? null,
            $typeIds
        )));
    }

    private function resolveLocationIds(?array $locationIds): array
    {
        if (empty($locationIds)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($id) => $this->locationNames[(int) $id] ?? null,
            $locationIds
        )));
    }

    private function resolveEmploymentType(?string $commitment): string
    {
        return match ($commitment) {
            'long_term' => 'Full-Time',
            'short_term', 'temporary' => 'Part-Time',
            default => 'Full-Time',
        };
    }

    private function resolveSkills(Candidate $candidate): array
    {
        $skills = [];

        if ($candidate->bilingual && ! in_array($candidate->bilingual, ['None', 'English', ''])) {
            $skills[] = $candidate->bilingual;
        }

        $experienceBased = match (true) {
            str_starts_with((string) $candidate->years_of_experience, '10') => ['PHP', 'Laravel', 'React'],
            str_starts_with((string) $candidate->years_of_experience, '5') => ['Laravel', 'Vue'],
            default => ['PHP'],
        };

        return array_merge($skills, $experienceBased);
    }
}
