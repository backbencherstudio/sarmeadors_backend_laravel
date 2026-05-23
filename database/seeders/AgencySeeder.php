<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::create([
            'name'             => 'Coast To Coast Nannies',
            'subdomain'        => 'coasttocoastnannies.example.com',
            'subdomain_prefix' => 'coasttocoast',
            'email'            => 'info@coasttocoastnannies.com',
            'mobile'           => '+1 305 000 0000',
            'address'          => '123 Ocean Drive, Miami Beach, FL 33139',
            'website'          => 'https://coasttocoastnannies.com',
            'language'         => 'en',
            'status'           => 'active',
            'max_users'        => 20,
            'max_clients'      => 100,
            'max_candidates'   => 1000,

            // Stripe — fill in real keys to enable payments
            'stripe_publishable_key' => null,
            'stripe_secret_key'      => null,
            'stripe_webhook_secret'  => null,

            // Short-term job fee — off by default
            'short_term_payment_required' => false,
            'short_term_job_fee'          => 40.00,
            'short_term_job_fee_currency' => 'usd',
            'short_term_auto_approve'     => true,
        ]);

        // Agency admin user
        $admin = User::create([
            'first_name' => 'Agency',
            'last_name'  => 'Admin',
            'email'      => 'agency@gmail.com',
            'mobile'     => '+1 305 000 0001',
            'agency_id'  => $agency->id,
            'is_owner'   => 1,
            'password'   => bcrypt('111111'),
        ]);

        $admin->assignRole(
            Role::where('name', 'agency_admin')->where('guard_name', 'api')->first()
        );
    }
}
