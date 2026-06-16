<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\CheckList;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateField;
use App\Models\DocumentTemplateSigner;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Location;
use App\Models\Status;
use App\Models\Tag;
use App\Models\Type;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Seeds the full client roster for the agency. This is the single source of
 * clients — JobSeeder references the clients created here so every client
 * ends up tied to real jobs, documents and events.
 */
class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        $role = Role::where('name', 'client')->where('guard_name', 'api')->first();

        $types = Type::where('agency_id', $agency->id)->where('type', 'client')->orderBy('id')->get();
        $locations = Location::where('agency_id', $agency->id)->orderBy('id')->get();
        $tags = Tag::where('agency_id', $agency->id)->where('type', 'client')->orderBy('id')->get();
        $statuses = Status::where('agency_id', $agency->id)->where('type', 'client')->orderBy('id')->get();
        $checklists = CheckList::where('agency_id', $agency->id)->where('type', 'client')->orderBy('id')->get();
        $eventTypes = EventType::where('agency_id', $agency->id)->where('type', 'client')->orderBy('id')->get();

        if ($types->isEmpty() || $locations->isEmpty()) {
            $this->command->error('Supporting data not found. Run SupportingDataSeeder first.');

            return;
        }

        $clientsData = [
            [
                'first_name' => 'Alex', 'last_name' => 'Johnson',
                'email' => 'client@gmail.com', 'mobile' => '+1 305 200 0001',
                'hear_about_us' => 'Google', 'payment_status' => 'paid',
                'typeIdx' => 0, 'locationIdx' => 0, 'tagIdx' => 2, 'statusIdx' => 0,
                'checklistIdxs' => [0, 1, 2],
            ],
            [
                'first_name' => 'Olivia', 'last_name' => 'Bennett',
                'email' => 'olivia.bennett@example.com', 'mobile' => '+1 305 200 0002',
                'hear_about_us' => 'Friend Referral', 'payment_status' => 'paid',
                'typeIdx' => 1, 'locationIdx' => 1, 'tagIdx' => 0, 'statusIdx' => 0,
                'checklistIdxs' => [0, 1],
            ],
            [
                'first_name' => 'Daniel', 'last_name' => 'Carter',
                'email' => 'daniel.carter@example.com', 'mobile' => '+1 305 200 0003',
                'hear_about_us' => 'Social Media', 'payment_status' => 'paid',
                'typeIdx' => 2, 'locationIdx' => 2, 'tagIdx' => 3, 'statusIdx' => 0,
                'checklistIdxs' => [0, 1, 2, 3],
            ],
            [
                'first_name' => 'Sophia', 'last_name' => 'Mitchell',
                'email' => 'sophia.mitchell@example.com', 'mobile' => '+1 305 200 0004',
                'hear_about_us' => 'Google', 'payment_status' => 'pending',
                'typeIdx' => 3, 'locationIdx' => 3, 'tagIdx' => 1, 'statusIdx' => 2,
                'checklistIdxs' => [0],
            ],
            [
                'first_name' => 'James', 'last_name' => 'Anderson',
                'email' => 'james.anderson@example.com', 'mobile' => '+1 305 200 0005',
                'hear_about_us' => 'Agency Website', 'payment_status' => 'failed',
                'typeIdx' => 0, 'locationIdx' => 1, 'tagIdx' => 2, 'statusIdx' => 1,
                'checklistIdxs' => [0, 2],
            ],
            [
                'first_name' => 'Michael', 'last_name' => 'Rodriguez',
                'email' => 'michael.rodriguez@example.com', 'mobile' => '+1 305 200 0006',
                'hear_about_us' => 'Friend Referral', 'payment_status' => 'paid',
                'typeIdx' => 1, 'locationIdx' => 2, 'tagIdx' => 3, 'statusIdx' => 0,
                'checklistIdxs' => [0, 1],
            ],
        ];

        $clients = [];
        foreach ($clientsData as $data) {
            $client = Client::firstOrCreate(
                ['email' => $data['email']],
                [
                    'agency_id' => $agency->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'mobile' => $data['mobile'],
                    'hear_about_us' => $data['hear_about_us'],
                    'payment_status' => $data['payment_status'],
                    'type_id' => [$types[$data['typeIdx'] % $types->count()]->id],
                    'location_id' => [$locations[$data['locationIdx'] % $locations->count()]->id],
                    'tag_id' => $tags->isNotEmpty() ? [$tags[$data['tagIdx'] % $tags->count()]->id] : [],
                    'status_id' => $statuses->isNotEmpty() ? [$statuses[$data['statusIdx'] % $statuses->count()]->id] : [],
                    'checklist_id' => $checklists->isNotEmpty()
                        ? array_map(fn ($i) => $checklists[$i % $checklists->count()]->id, $data['checklistIdxs'])
                        : [],
                ]
            );

            $user = User::firstOrCreate(
                ['email' => $client->email],
                [
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'mobile' => $client->mobile,
                    'agency_id' => $agency->id,
                    'is_owner' => 0,
                    'password' => bcrypt('111111'),
                ]
            );

            if ($role && ! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            $clients[] = $client;
        }

        $documentTemplates = $this->seedDocumentTemplates($agency);
        $this->seedClientDocuments($agency, $clients, $documentTemplates);
        $this->seedEvents($agency, $clients, $locations, $eventTypes);

        $this->command->info('Client seeder completed successfully!');
        $this->command->info('Created/ensured: '.count($clients).' clients');
    }

    /**
     * @return array<int, DocumentTemplate>
     */
    private function seedDocumentTemplates(Agency $agency): array
    {
        $templatesData = [
            [
                'name' => 'Standard Service Agreement',
                'user_type' => 'client',
                'content_type' => 'text',
                'content' => 'This Service Agreement is entered into between [AGENCY_NAME] and [CLIENT_NAME] for the provision of childcare services. Terms and conditions apply as per agency policy.',
                'org_signer_name' => $agency->name.' Representative',
                'org_name' => $agency->name,
                'signer_labels' => ['Client', 'Agency Representative'],
                'fields' => [
                    ['field_type' => 'text', 'field_label' => 'Full Name', 'field_tag' => 'client_name', 'is_required' => true],
                    ['field_type' => 'text', 'field_label' => 'Email', 'field_tag' => 'client_email', 'is_required' => true],
                    ['field_type' => 'text', 'field_label' => 'Phone', 'field_tag' => 'client_phone', 'is_required' => true],
                    ['field_type' => 'date', 'field_label' => 'Start Date', 'field_tag' => 'start_date', 'is_required' => true],
                    ['field_type' => 'textarea', 'field_label' => 'Special Requirements', 'field_tag' => 'special_requirements', 'is_required' => false],
                    ['field_type' => 'signature', 'field_label' => 'Client Signature', 'field_tag' => 'client_signature', 'is_required' => true],
                    ['field_type' => 'signature', 'field_label' => 'Agency Signature', 'field_tag' => 'agency_signature', 'is_required' => true],
                ],
            ],
            [
                'name' => 'Liability Waiver',
                'user_type' => 'client',
                'content_type' => 'text',
                'content' => 'I, [CLIENT_NAME], hereby release [AGENCY_NAME] from any and all liability arising from the childcare services provided. I understand the risks and agree to the terms outlined.',
                'org_signer_name' => $agency->name.' Representative',
                'org_name' => $agency->name,
                'signer_labels' => ['Client'],
                'fields' => [
                    ['field_type' => 'text', 'field_label' => 'Full Name', 'field_tag' => 'client_name', 'is_required' => true],
                    ['field_type' => 'date', 'field_label' => 'Date', 'field_tag' => 'date', 'is_required' => true],
                    ['field_type' => 'signature', 'field_label' => 'Signature', 'field_tag' => 'client_signature', 'is_required' => true],
                ],
            ],
            [
                'name' => 'Emergency Contact Form',
                'user_type' => 'client',
                'content_type' => 'text',
                'content' => 'Please provide emergency contact information for the duration of the childcare services.',
                'org_signer_name' => null,
                'org_name' => $agency->name,
                'signer_labels' => ['Client'],
                'fields' => [
                    ['field_type' => 'text', 'field_label' => 'Full Name', 'field_tag' => 'client_name', 'is_required' => true],
                    ['field_type' => 'text', 'field_label' => 'Emergency Contact Name', 'field_tag' => 'emergency_name', 'is_required' => true],
                    ['field_type' => 'text', 'field_label' => 'Emergency Contact Phone', 'field_tag' => 'emergency_phone', 'is_required' => true],
                    ['field_type' => 'text', 'field_label' => 'Emergency Contact Relation', 'field_tag' => 'emergency_relation', 'is_required' => true],
                    ['field_type' => 'textarea', 'field_label' => 'Medical Conditions / Allergies', 'field_tag' => 'medical_conditions', 'is_required' => false],
                    ['field_type' => 'signature', 'field_label' => 'Client Signature', 'field_tag' => 'client_signature', 'is_required' => true],
                ],
            ],
        ];

        $documentTemplates = [];
        foreach ($templatesData as $tmpl) {
            $template = DocumentTemplate::firstOrCreate(
                [
                    'agency_id' => $agency->id,
                    'name' => $tmpl['name'],
                ],
                [
                    'user_type' => $tmpl['user_type'],
                    'content_type' => $tmpl['content_type'],
                    'content' => $tmpl['content'],
                    'org_signer_name' => $tmpl['org_signer_name'],
                    'org_name' => $tmpl['org_name'],
                ]
            );

            foreach ($tmpl['fields'] as $field) {
                DocumentTemplateField::firstOrCreate(
                    [
                        'document_template_id' => $template->id,
                        'field_tag' => $field['field_tag'],
                    ],
                    $field
                );
            }

            foreach ($tmpl['signer_labels'] as $label) {
                DocumentTemplateSigner::firstOrCreate(
                    [
                        'document_template_id' => $template->id,
                        'signer_label' => $label,
                    ]
                );
            }

            $documentTemplates[] = $template;
        }

        return $documentTemplates;
    }

    /**
     * @param  array<int, Client>  $clients
     * @param  array<int, DocumentTemplate>  $documentTemplates
     */
    private function seedClientDocuments(Agency $agency, array $clients, array $documentTemplates): void
    {
        foreach ($clients as $client) {
            foreach ($documentTemplates as $template) {
                ClientDocument::firstOrCreate(
                    [
                        'client_id' => $client->id,
                        'document_template_id' => $template->id,
                    ],
                    [
                        'agency_id' => $agency->id,
                        'title' => $template->name.' - '.$client->first_name.' '.$client->last_name,
                        'description' => $template->content,
                        'status' => 'pending',
                        'metadata' => json_encode(['sent_via' => 'email', 'template_name' => $template->name]),
                    ]
                );
            }
        }
    }

    /**
     * @param  array<int, Client>  $clients
     * @param  Collection<int, Location>  $locations
     * @param  Collection<int, EventType>  $eventTypes
     */
    private function seedEvents(Agency $agency, array $clients, $locations, $eventTypes): void
    {
        if ($eventTypes->isEmpty()) {
            return;
        }

        foreach ($clients as $index => $client) {
            Event::firstOrCreate(
                [
                    'event_title' => 'Client Consultation - '.$client->first_name.' '.$client->last_name,
                    'client_id' => $client->id,
                ],
                [
                    'agency_id' => $agency->id,
                    'event_type_id' => $eventTypes[$index % $eventTypes->count()]->id,
                    'event_date' => now()->addDays(rand(3, 30))->format('Y-m-d'),
                    'event_time' => '10:00:00',
                    'location' => $locations[$index % $locations->count()]->location,
                    'note' => 'Initial consultation meeting with the family.',
                    'is_email_notify' => true,
                ]
            );
        }
    }
}
