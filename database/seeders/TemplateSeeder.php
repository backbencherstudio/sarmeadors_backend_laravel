<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Status;
use App\Models\StatusTemplate;
use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
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
        | Templates (email & document)
        |--------------------------------------------------------------------------
        */
        $templatesData = [
            [
                'title' => 'Welcome Email',
                'type' => 'email',
                'content' => 'Hello {{name}}, welcome to '.$agency->name.'! We are delighted to have you with us.',
            ],
            [
                'title' => 'Application Received',
                'type' => 'email',
                'content' => 'Hi {{name}}, we have received your application and our team will review it shortly.',
            ],
            [
                'title' => 'Interview Invitation',
                'type' => 'email',
                'content' => 'Dear {{name}}, you are invited for an interview on {{date}} at {{time}}.',
            ],
            [
                'title' => 'Placement Agreement',
                'type' => 'document',
                'content' => 'This document confirms the placement agreement between '.$agency->name.' and {{name}}.',
            ],
        ];

        $templates = [];
        foreach ($templatesData as $data) {
            $templates[] = Template::firstOrCreate(
                ['agency_id' => $agency->id, 'title' => $data['title']],
                ['type' => $data['type'], 'content' => $data['content']]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Status Templates (link statuses to email templates)
        |--------------------------------------------------------------------------
        */
        $statuses = Status::where('agency_id', $agency->id)->where('type', 'client')->get();
        $emailTemplates = array_values(array_filter($templates, fn ($t) => $t->type === 'email'));

        foreach ($statuses as $index => $status) {
            if (empty($emailTemplates)) {
                break;
            }

            $template = $emailTemplates[$index % count($emailTemplates)];

            StatusTemplate::firstOrCreate(
                [
                    'status_id' => $status->id,
                    'template_id' => $template->id,
                ],
                ['template_type' => $template->type]
            );
        }

        $this->command->info('Template seeder completed successfully!');
    }
}
