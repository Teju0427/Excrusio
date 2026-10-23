<?php

namespace App\Providers;

use App\Payments\DemoGateway;
use App\Payments\PaymentGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap in a real gateway here later (config excursio.payment_gateway); demo by default.
        $this->app->bind(PaymentGateway::class, DemoGateway::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Catch N+1 queries and typos in attribute names while developing.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Behind Render/Azure load balancers, force https links in production.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
