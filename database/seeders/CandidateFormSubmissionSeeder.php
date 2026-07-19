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
    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        $typeNames = Type::where('agency_id', $agency->id)
            ->where('type', 'candidate')
            ->pluck('name', 'id')
            ->toArray();

        $locationNames = Location::where('agency_id', $agency->id)
            ->pluck('location', 'id')
            ->toArray();

        $form = $this->ensureForm($agency);

        $candidates = Candidate::where('agency_id', $agency->id)->get();

        foreach ($candidates as $candidate) {
            $this->seedSubmission($form, $candidate, $typeNames, $locationNames);
        }

        $this->command->info('Candidate form submission seeder completed successfully!');
        $this->command->info('Created/ensured submissions for: '.$candidates->count().' candidates');
    }

    private function ensureForm(Agency $agency): Form
    {
        $schema = [
            'blocks' => [
                [
                    'name' => 'Professional Information',
                    'description' => 'Enter your Professional information',
                    'sections' => [
                        [
                            'name' => 'Professional Information',
                            'fields' => [
                                ['name' => 'experience', 'label' => 'Years of Experience', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter your Experience', 'profile_label' => 'Years of Experience'],
                                ['name' => 'position', 'label' => 'Position', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter your Position', 'profile_label' => 'Position'],
                                ['name' => 'employment_type', 'label' => 'Employment Type', 'type' => 'radio', 'is_required' => true, 'width' => 6, 'options' => ['Full-Time', 'Part-Time', 'Contract'], 'profile_label' => 'Employment Type'],
                                ['name' => 'skills', 'label' => 'Skills', 'type' => 'multi_select_checkbox', 'is_required' => false, 'width' => 6, 'options' => ['PHP', 'Laravel', 'React', 'Vue'], 'profile_label' => 'Skills'],
                                ['name' => 'agree_terms', 'label' => 'I agree to the terms', 'type' => 'single_checkbox', 'is_required' => true, 'width' => 12, 'profile_label' => null],
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
                                ['name' => 'referer_name', 'label' => 'Referer Name', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter your Referar name', 'profile_label' => 'Referer Name'],
                                ['name' => 'referar_email', 'label' => 'Referar Email', 'type' => 'email', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter email', 'profile_label' => 'Referar Email'],
                                ['name' => 'referer_phone', 'label' => 'Referer Phone', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter Referar phone', 'profile_label' => 'Referer Phone'],
                                ['name' => 'referer_relation', 'label' => 'Referer Relation', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter Referar relationship', 'profile_label' => 'Referer Relation'],
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
                                ['name' => 'father_name', 'label' => 'Father Name', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter your father name', 'profile_label' => 'Father Name'],
                                ['name' => 'mother_name', 'label' => 'Mother Name', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Enter your mother name', 'profile_label' => 'Mother Name'],
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
                                ['name' => 'resume', 'label' => 'Resume', 'type' => 'file_upload', 'is_required' => true, 'width' => 6, 'placeholder' => 'Upload your resume', 'profile_label' => 'Resume'],
                                ['name' => 'nid', 'label' => 'NID', 'type' => 'file_upload', 'is_required' => true, 'width' => 6, 'placeholder' => 'Upload your NID', 'profile_label' => 'NID'],
                                ['name' => 'birth_certificate', 'label' => 'Birth Certificate', 'type' => 'file_upload', 'is_required' => false, 'width' => 6, 'placeholder' => 'Upload your birth certificate', 'profile_label' => 'Birth Certificate'],
                                ['name' => 'driving_licence', 'label' => 'Driving Licence', 'type' => 'file_upload', 'is_required' => false, 'width' => 6, 'placeholder' => 'Upload your driving licence', 'profile_label' => 'Driving Licence'],
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
                                ['name' => 'hear_about_us', 'label' => 'How did you hear about us?', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'How did you hear about us?', 'profile_label' => 'How did you hear about us?'],
                                ['name' => 'cpr_aid', 'label' => 'Are you CPR and First Aid certified', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'Are you CPR and First Aid certified', 'profile_label' => 'CPR / First Aid'],
                                ['name' => 'ok_with_pet', 'label' => 'OK with pets in the home?', 'type' => 'text_box', 'is_required' => false, 'width' => 6, 'placeholder' => 'OK with pets in the home?', 'profile_label' => 'OK with Pets'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return Form::updateOrCreate(
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

    private function seedSubmission(Form $form, Candidate $candidate, array $typeNames, array $locationNames): void
    {
        $slug = Str::slug($candidate->first_name.'-'.$candidate->last_name);
        $refererName = trim($candidate->reference_first_name.' '.$candidate->reference_last_name) ?: null;

        $answers = [
            // Professional Information
            'experience' => $candidate->years_of_experience,
            'position' => null,
            'employment_type' => $this->resolveEmploymentType($candidate->commitment),
            'skills' => $this->resolveSkills($candidate),
            'agree_terms' => 'true',
            // References
            'referer_name' => $refererName,
            'referar_email' => $candidate->reference_email,
            'referer_phone' => $candidate->reference_phone,
            'referer_relation' => $candidate->reference_relation,
            // Family
            'father_name' => null,
            'mother_name' => null,
            // Documents (relative storage paths — profileFieldValue converts to full URLs)
            'resume' => "form-submissions/candidate/{$slug}-resume.pdf",
            'nid' => "form-submissions/candidate/{$slug}-nid.jpg",
            'birth_certificate' => "form-submissions/candidate/{$slug}-birth.jpg",
            'driving_licence' => "form-submissions/candidate/{$slug}-licence.png",
            // Additional
            'hear_about_us' => $candidate->hear_about_us,
            'cpr_aid' => $candidate->cpr_first_aid,
            'ok_with_pet' => $candidate->ok_with_pets,
        ];

        $submission = FormSubmission::updateOrCreate(
            [
                'form_id' => $form->id,
                'entity_id' => $candidate->id,
                'entity_type' => 'candidate',
            ],
            ['data' => $answers]
        );
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
