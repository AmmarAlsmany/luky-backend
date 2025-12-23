<?php

namespace App\Providers;

use App\Services\MyFatoorahService;
use App\Services\SmsService;
use App\Services\PhoneNumberService;
use App\View\Composers\AdminNotificationComposer;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Models\Payment;
use App\Models\ServiceProvider as ServiceProviderModel;
use App\Models\ServiceCategory;
use App\Models\City;
use App\Observers\BookingObserver;
use App\Observers\ReviewObserver;
use App\Observers\UserObserver;
use App\Observers\PaymentObserver;
use App\Observers\ServiceProviderObserver;
use App\Observers\ServiceCategoryObserver;
use App\Observers\CityObserver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register PhoneNumberService as singleton
        $this->app->singleton(PhoneNumberService::class, function ($app) {
            return new PhoneNumberService();
        });

        // Register SMS Service as singleton with dependency injection
        $this->app->singleton(SmsService::class, function ($app) {
            return new SmsService($app->make(PhoneNumberService::class));
        });

        // Register MyFatoorahService as singleton
        $this->app->singleton(MyFatoorahService::class, function ($app) {
            return new MyFatoorahService();
        });

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share admin notifications with topbar
        View::composer('layouts.partials.topbar', AdminNotificationComposer::class);

        // Register model observers
        Booking::observe(BookingObserver::class);
        Review::observe(ReviewObserver::class);
        User::observe(UserObserver::class);
        Payment::observe(PaymentObserver::class);
        ServiceProviderModel::observe(ServiceProviderObserver::class);
        ServiceCategory::observe(ServiceCategoryObserver::class);
        City::observe(CityObserver::class);

        // Register Blade directive for localized names
        Blade::directive('localizedName', function ($expression) {
            return "<?php echo app()->getLocale() === 'ar' ? ({$expression}->name_ar ?? {$expression}->name_en) : ({$expression}->name_en ?? {$expression}->name_ar); ?>";
        });
    }
}
