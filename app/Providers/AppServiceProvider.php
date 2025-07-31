<?php

namespace App\Providers;

use App\Repositories\RoleRepository;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // ✅ Bind Repository (sin interface para simplicidad)
        $this->app->singleton(RoleRepository::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // ✅ CONFIGURAR: Sanctum para no usar rutas web
        Sanctum::ignoreMigrations();
        
        // ✅ PERSONALIZAR: Comportamiento de autenticación
        $this->configureAuthentication();
    }

    /**
     * Configure authentication behavior for API
     */
    private function configureAuthentication(): void
    {
        // ✅ PERSONALIZAR: Guard para API
        config([
            'auth.defaults.guard' => 'sanctum'
        ]);
    }
}
