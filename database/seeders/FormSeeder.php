<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldValue;
use App\Models\FormSubmission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FormSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Forms with fields
        |--------------------------------------------------------------------------
        */
        $formsData = [
            [
                'name' => 'Client Intake Form',
                'entity' => 'client',
                'fields' => [
                    ['label' => 'Full Name', 'name' => 'full_name', 'type' => 'text', 'is_required' => true],
                    ['label' => 'Email', 'name' => 'email', 'type' => 'email', 'is_required' => true],
                    ['label' => 'Phone', 'name' => 'phone', 'type' => 'text', 'is_required' => true],
                    ['label' => 'Number of Children', 'name' => 'children_count', 'type' => 'select', 'is_required' => true, 'options' => ['1', '2', '3', '4+']],
                    ['label' => 'Care Type Needed', 'name' => 'care_type', 'type' => 'radio', 'is_required' => true, 'options' => ['Full-Time', 'Part-Time', 'Occasional']],
                    ['label' => 'Additional Notes', 'name' => 'notes', 'type' => 'textarea', 'is_required' => false],
                ],
            ],
            [
                'name' => 'Candidate Application Form',
                'entity' => 'candidate',
                'fields' => [
                    ['label' => 'Full Name', 'name' => 'full_name', 'type' => 'text', 'is_required' => true],
                    ['label' => 'Email', 'name' => 'email', 'type' => 'email', 'is_required' => true],
                    ['label' => 'Years of Experience', 'name' => 'experience', 'type' => 'select', 'is_required' => true, 'options' => ['0-2', '2-5', '5-10', '10+']],
                    ['label' => 'CPR Certified', 'name' => 'cpr', 'type' => 'radio', 'is_required' => true, 'options' => ['Yes', 'No', 'Willing to certify']],
                    ['label' => 'Resume', 'name' => 'resume', 'type' => 'file', 'is_required' => false],
                ],
            ],
        ];

        $forms = [];
        foreach ($formsData as $data) {
            $form = Form::firstOrCreate(
                ['agency_id' => $agency->id, 'name' => $data['name']],
                ['slug' => Str::slug($data['name']), 'entity' => $data['entity'], 'status' => true]
            );

            $fields = [];
            foreach ($data['fields'] as $serial => $field) {
                $fields[$field['name']] = FormField::firstOrCreate(
                    ['form_id' => $form->id, 'name' => $field['name']],
                    [
                        'label' => $field['label'],
                        'type' => $field['type'],
                        'options' => $field['options'] ?? null,
                        'placeholder' => 'Enter '.strtolower($field['label']),
                        'is_required' => $field['is_required'],
                        'serial' => $serial + 1,
                        'width' => 12,
                        'status' => true,
                    ]
                );
            }

            $forms[$data['entity']] = ['form' => $form, 'fields' => $fields];
        }

        /*
        |--------------------------------------------------------------------------
        | Submissions with values
        |--------------------------------------------------------------------------
        */
        $client = Client::where('agency_id', $agency->id)->first();
        if ($client && isset($forms['client'])) {
            $this->seedSubmission($forms['client'], $client->id, [
                'full_name' => $client->first_name.' '.$client->last_name,
                'email' => $client->email,
                'phone' => $client->mobile,
                'children_count' => '2',
                'care_type' => 'Full-Time',
                'notes' => 'Looking for an experienced nanny for two toddlers.',
            ]);
        }

        $candidate = Candidate::where('agency_id', $agency->id)->first();
        if ($candidate && isset($forms['candidate'])) {
            $this->seedSubmission($forms['candidate'], $candidate->id, [
                'full_name' => $candidate->first_name.' '.$candidate->last_name,
                'email' => $candidate->email,
                'experience' => '5-10',
                'cpr' => 'Yes',
                'resume' => null,
            ]);
        }

        $this->command->info('Form seeder completed successfully!');
    }

    /**
     * @param  array{form: Form, fields: array<string, FormField>}  $formData
     * @param  array<string, string|null>  $values
     */
    private function seedSubmission(array $formData, int $entityId, array $values): void
    {
        $submission = FormSubmission::firstOrCreate([
            'form_id' => $formData['form']->id,
            'entity_id' => $entityId,
        ]);

        foreach ($values as $fieldName => $value) {
            if (! isset($formData['fields'][$fieldName])) {
                continue;
            }

            FormFieldValue::firstOrCreate(
                [
                    'submission_id' => $submission->id,
                    'form_field_id' => $formData['fields'][$fieldName]->id,
                ],
                ['value' => $value]
            );
        }
    }
}
