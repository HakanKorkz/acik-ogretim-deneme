<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Questions\AiQuestionGenerator;
use App\Services\Questions\LocalQuestionGenerator;
use App\Services\Questions\QuestionGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Soru üreticisi `config('exam.generator')` değerine göre seçilir.
        // Yapay zekâ seçili olsa bile panelde anahtar/model yoksa üretici
        // kendi içinde yerel üreticiye düşer.
        $this->app->bind(QuestionGenerator::class, function ($app): QuestionGenerator {
            return config('exam.generator') === 'local'
                ? $app->make(LocalQuestionGenerator::class)
                : $app->make(AiQuestionGenerator::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        Gate::define('access-admin', fn (User $user): bool => $user->is_admin);
    }

    /**
     * Kimlik doğrulama olmadığından pahalı ya da kayıt oluşturan uç noktalar
     * IP başına sınırlandırılır.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('question-generation', fn (Request $request): Limit => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('exam-start', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('exam-submit', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
