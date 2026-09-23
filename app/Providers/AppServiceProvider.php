<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Support\PermissionRegistry;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('uz_Latn');
        Paginator::defaultView('vendor.pagination.edunova');
        Model::preventLazyLoading(! $this->app->isProduction());

        // sAdmin uchun barcha ruxsatlar ochiq
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Har bir ruxsat kaliti Gate sifatida ro'yxatdan o'tadi: @can('students.view'), can:students.view
        foreach (PermissionRegistry::keys() as $key) {
            Gate::define($key, fn (User $user) => $user->hasPermission($key));
        }

        // Foydalanuvchini faqat joriy filial kontekstida topish (boshqa filial -> 404)
        Route::bind('student', fn ($value) => User::visibleToContext()->ofRole(Role::Student)->findOrFail($value));
        Route::bind('user', fn ($value) => User::visibleToContext()->findOrFail($value));

        // v8 A5: mobil ilova API'si uchun umumiy chegara (bootstrap/app.php'dagi throttleApi() shuni ishlatadi)
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
