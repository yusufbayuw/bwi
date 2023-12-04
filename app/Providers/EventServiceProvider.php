<?php

namespace App\Providers;

use App\Models\Cabang;
use App\Models\Cicilan;
use App\Models\Infak;
use App\Models\Pengeluaran;
use App\Models\Pinjaman;
use App\Models\User;
use App\Observers\CabangObserver;
use App\Observers\CicilanObserver;
use App\Observers\InfakObserver;
use App\Observers\PengeluaranObserver;
use App\Observers\PinjamanObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Cabang::observe(CabangObserver::class);
        Cicilan::observe(CicilanObserver::class);
        Infak::observe(InfakObserver::class);
        Pengeluaran::observe(PengeluaranObserver::class);
        User::observe(UserObserver::class);
        Pinjaman::observe(PinjamanObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
