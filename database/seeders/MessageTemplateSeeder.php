<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::where('subdomain_prefix', 'coasttocoast')->first();

        if (! $agency) {
            $this->command->error('Agency not found. Run AgencySeeder first.');

            return;
        }

        $templates = [
            [
                'name' => 'Client Onboarding',
                'user_type' => 1,
                'type' => 'email',
                'subject' => 'Welcome to '.$agency->name,
                'body' => 'Dear {{client_name}}, thank you for choosing us. Your dedicated coordinator will reach out within 24 hours.',
                'sender_email' => $agency->email,
                'receiver_email' => null,
                'location' => 'onboarding',
            ],
            [
                'name' => 'Candidate Welcome',
                'user_type' => 2,
                'type' => 'email',
                'subject' => 'Your application with '.$agency->name,
                'body' => 'Hi {{candidate_name}}, welcome aboard! Please complete your profile and upload the required documents.',
                'sender_email' => $agency->email,
                'receiver_email' => null,
                'location' => 'onboarding',
            ],
            [
                'name' => 'Interview Reminder',
                'user_type' => 2,
                'type' => 'email',
                'subject' => 'Reminder: Upcoming Interview',
                'body' => 'Hi {{candidate_name}}, this is a reminder for your interview scheduled on {{date}} at {{time}}.',
                'sender_email' => $agency->email,
                'receiver_email' => null,
                'cc' => 'coordinator@coasttocoastnannies.com',
                'location' => 'interview',
            ],
            [
                'name' => 'Payment Confirmation',
                'user_type' => 1,
                'type' => 'email',
                'subject' => 'Payment Received',
                'body' => 'Dear {{client_name}}, we have received your payment of {{amount}}. Thank you!',
                'sender_email' => $agency->email,
                'receiver_email' => null,
                'location' => 'billing',
            ],
        ];

        foreach ($templates as $template) {
            MessageTemplate::firstOrCreate(
                ['agency_id' => $agency->id, 'name' => $template['name']],
                array_merge($template, [
                    'status' => 1,
                    'attachments' => [],
                ])
            );
        }

        $this->command->info('Message template seeder completed successfully!');
    }
}
