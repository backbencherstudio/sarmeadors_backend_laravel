@servers(['web' => 'backend@89.116.170.60'])

@task('deploy', ['on' => 'web'])
    cd htdocs/api.staffhaus.cloud/
    git pull origin main
    php artisan migrate --force
    php artisan optimize:clear
@endtask
