<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Identity\IdentityProvider;
use App\Contracts\Identity\ProvisionsUsers;
use App\Contracts\Initiation\SuggestsContent;
use App\Models\OrgUnit;
use App\Models\Project;
use App\Models\ProjectCharter;
use App\Models\Risk;
use App\Models\Stakeholder;
use App\Models\User;
use App\Policies\OrgUnitPolicy;
use App\Policies\ProjectCharterPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\RiskPolicy;
use App\Policies\StakeholderPolicy;
use App\Policies\UserPolicy;
use App\Services\Identity\LocalIdentityProvider;
use App\Services\Identity\LocalUserProvisioner;
use App\Services\Initiation\OpenAiSuggestionProvider;
use App\Services\Initiation\TemplateSuggestionProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Un solo punto de cambio el día que llegue el SSO: se sustituyen estas
        // dos ligaduras y nada más en toda la aplicación.
        $this->app->bind(IdentityProvider::class, LocalIdentityProvider::class);
        $this->app->bind(ProvisionsUsers::class, LocalUserProvisioner::class);

        // Mismo patrón: quien pide sugerencias no sabe de dónde salen. Con el
        // interruptor apagado el recorrido funciona igual, solo con plantillas.
        $this->app->bind(SuggestsContent::class, function ($app): SuggestsContent {
            $templates = $app->make(TemplateSuggestionProvider::class);

            return config('initiation.ai.enabled') && filled(config('initiation.ai.key'))
                ? new OpenAiSuggestionProvider($templates)
                : $templates;
        });
    }

    public function boot(): void
    {
        Gate::policy(OrgUnit::class, OrgUnitPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(ProjectCharter::class, ProjectCharterPolicy::class);
        Gate::policy(Risk::class, RiskPolicy::class);
        Gate::policy(Stakeholder::class, StakeholderPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // Un acceso perezoso no detectado es una consulta N+1 esperando a
        // producción. En local revienta de inmediato; en producción no, para no
        // tumbar una pantalla por un descuido de rendimiento.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->registerDisplayModeDirectives();
    }

    /**
     * `@expert` y `@simple` en las vistas. Existen desde ya para que las
     * pantallas de las etapas siguientes no inventen cada una su forma de
     * preguntar lo mismo, ni consulten al usuario a mano en cada Blade.
     *
     * Un invitado no tiene preferencia, y lo prudente ahí es lo simple.
     */
    private function registerDisplayModeDirectives(): void
    {
        Blade::if('expert', function (): bool {
            $user = Auth::user();

            return $user instanceof User && $user->expert_mode;
        });

        Blade::if('simple', function (): bool {
            $user = Auth::user();

            return ! ($user instanceof User && $user->expert_mode);
        });
    }
}
