<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class AgencyController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:agencies,name',
            'subdomain' => 'required|string|max:255|unique:agencies,subdomain',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
        ]);

        $agency = Agency::create([
            'name' => $request->name,
            'subdomain' => $request->subdomain, // full subdomain like a.softvencedelta.com
            'email' => $request->email,
            'phone' => $request->phone,
            'status' => 'active',
        ]);

        $this->setupApacheSubdomain($request->subdomain);

        //Optional: Trigger SSL (if Certbot installed)
        $this->setupSSL($request->subdomain);

        return response()->json([
            'status' => true,
            'message' => 'Agency created successfully',
            'data' => $agency
        ]);
    }

    protected function setupApacheSubdomain($subdomain)
    {
        $documentRoot = "/var/www/multi-agency/public";
        $siteName = str_replace('.', '-', $subdomain); // e.g., a-softvencedelta-com
        $configPath = "/etc/apache2/sites-available/{$siteName}.conf";

        $configContent = "
            <VirtualHost *:80>
                ServerName {$subdomain}

                DocumentRoot {$documentRoot}

                <Directory {$documentRoot}>
                    AllowOverride All
                    Require all granted
                </Directory>

                ErrorLog \${APACHE_LOG_DIR}/{$siteName}_error.log
                CustomLog \${APACHE_LOG_DIR}/{$siteName}_access.log combined
            </VirtualHost>
        ";

        File::put($configPath, $configContent);

        exec("sudo a2ensite {$siteName}.conf");
        exec("sudo systemctl reload apache2");
    }

    protected function setupSSL($subdomain)
    {
        exec("sudo certbot --apache -d {$subdomain} --non-interactive --agree-tos -m admin@softvencedelta.com");
    }
}
