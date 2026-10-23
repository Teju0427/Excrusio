<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\ReviewController;
use App\Http\Controllers\Account\WishlistController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BookingAdminController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ListingModerationController;
use App\Http\Controllers\Admin\ReviewModerationController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Booking\TripController;
use App\Http\Controllers\Booking\WaitlistController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Host\AvailabilityManageController;
use App\Http\Controllers\Host\BookingRequestController;
use App\Http\Controllers\Host\CheckInController;
use App\Http\Controllers\Host\HostController;
use App\Http\Controllers\Host\ListingManageController;
use App\Http\Controllers\Host\ReviewController as HostReviewController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/search', SearchController::class)->name('search');

// Live availability for listing pages (read-only JSON, rate limited).
Route::middleware('throttle:60,1')->prefix('api/listings/{listing}')->name('availability.')->group(function () {
    Route::get('/stay', [AvailabilityController::class, 'stay'])->name('stay');
    Route::get('/slots', [AvailabilityController::class, 'slots'])->name('slots');
});

Route::get('/destinations', [DestinationController::class, 'index'])->name('destinations.index');
Route::get('/destinations/{destination}', [DestinationController::class, 'show'])->name('destinations.show');

Route::get('/about', [PageController::class, 'about'])->name('pages.about');
Route::get('/terms', [PageController::class, 'terms'])->name('pages.terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('pages.privacy');
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1');
Route::get('/image-credits', [PageController::class, 'credits'])->name('pages.credits');

// ---------------------------------------------------------------- Authentication
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/demo-login/{role}', [LoginController::class, 'demo'])
        ->whereIn('role', ['customer', 'provider', 'admin'])->middleware('throttle:20,1')->name('login.demo');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/host/pending', [HostController::class, 'pending'])->name('host.pending');

    Route::middleware('active')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/account/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/account/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{id}', [NotificationController::class, 'open'])->whereUuid('id')->name('notifications.open');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

        Route::put('/account/password', [ProfileController::class, 'password'])->middleware('throttle:6,1')->name('profile.password');

        Route::post('/listings/{listing}/report', [ReportController::class, 'store'])->middleware('throttle:10,1')->name('listings.report');

        // Any signed-in role may open a booking page; BookingPolicy decides who actually sees it.
        Route::get('/bookings/{booking}', [AccountController::class, 'showBooking'])->name('bookings.show');

        Route::middleware('role:customer')->group(function () {
            Route::get('/account', [AccountController::class, 'dashboard'])->name('account.dashboard');
            Route::get('/bookings', [AccountController::class, 'bookings'])->name('bookings.index');
            Route::get('/account/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
            Route::post('/wishlist/{listing}', [WishlistController::class, 'toggle'])->middleware('throttle:60,1')->name('wishlist.toggle');

            // Booking flow (prices are always recalculated on the server)
            Route::middleware('throttle:30,1')->group(function () {
                Route::post('/book/stay/{listing}', [BookingController::class, 'storeStay'])->name('book.stay');
                Route::post('/book/slot/{listing}', [BookingController::class, 'storeSlot'])->name('book.slot');
                Route::post('/checkout/{booking}', [BookingController::class, 'pay'])->name('checkout.pay');
                Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
                Route::post('/bookings/{booking}/review', [ReviewController::class, 'store'])->name('reviews.store');
            });
            Route::get('/checkout/{booking}', [BookingController::class, 'checkout'])->name('checkout.show');

            // Trip cart (F1)
            Route::get('/trip', [TripController::class, 'index'])->name('trip.index');
            Route::post('/trip/stay/{listing}', [TripController::class, 'addStay'])->name('trip.add-stay');
            Route::post('/trip/slot/{listing}', [TripController::class, 'addSlot'])->name('trip.add-slot');
            Route::delete('/trip/{index}', [TripController::class, 'remove'])->whereNumber('index')->name('trip.remove');
            Route::post('/trip/hold', [TripController::class, 'hold'])->middleware('throttle:20,1')->name('trip.hold');
            Route::get('/trip/{group}/checkout', [TripController::class, 'checkout'])->name('trip.checkout');
            Route::post('/trip/{group}/checkout', [TripController::class, 'pay'])->middleware('throttle:20,1')->name('trip.pay');
            Route::get('/trip/{group}', [TripController::class, 'show'])->name('trip.show');

            // Waitlist (F2)
            Route::post('/waitlist/{slot}', [WaitlistController::class, 'join'])->middleware('throttle:20,1')->name('waitlist.join');
            Route::get('/waitlist/{entry}/claim', [WaitlistController::class, 'claim'])->name('waitlist.claim');
            Route::delete('/waitlist/{entry}', [WaitlistController::class, 'leave'])->name('waitlist.leave');
        });

        Route::middleware('role:provider')->prefix('host')->name('host.')->group(function () {
            Route::get('/', [HostController::class, 'dashboard'])->name('dashboard');
            Route::get('/bookings', [BookingRequestController::class, 'index'])->name('bookings');
            Route::post('/bookings/{booking}/confirm', [BookingRequestController::class, 'confirm'])->name('bookings.confirm');
            Route::post('/bookings/{booking}/reject', [BookingRequestController::class, 'reject'])->name('bookings.reject');
            Route::get('/listings/{listing}/offline-booking', [BookingRequestController::class, 'createOffline'])->name('offline.create');
            Route::post('/listings/{listing}/offline-booking', [BookingRequestController::class, 'storeOffline'])->name('offline.store');
            Route::get('/calendar', [HostController::class, 'calendar'])->name('calendar');
            Route::get('/reviews', [HostReviewController::class, 'index'])->name('reviews');
            Route::put('/reviews/{review}/reply', [HostReviewController::class, 'reply'])->middleware('throttle:20,1')->name('reviews.reply');

            Route::get('/listings/new', [ListingManageController::class, 'create'])->name('listings.create');
            Route::post('/listings', [ListingManageController::class, 'store'])->middleware('throttle:10,1')->name('listings.store');
            Route::get('/listings/{listing}/edit', [ListingManageController::class, 'edit'])->name('listings.edit');
            Route::put('/listings/{listing}', [ListingManageController::class, 'update'])->name('listings.update');

            Route::get('/listings/{listing}/availability', [AvailabilityManageController::class, 'show'])->name('availability');
            Route::post('/listings/{listing}/units', [AvailabilityManageController::class, 'storeUnit'])->name('units.store');
            Route::put('/listings/{listing}/units/{unit}', [AvailabilityManageController::class, 'updateUnit'])->name('units.update');
            Route::post('/listings/{listing}/blocks', [AvailabilityManageController::class, 'storeBlock'])->name('blocks.store');
            Route::delete('/listings/{listing}/blocks/{block}', [AvailabilityManageController::class, 'destroyBlock'])->name('blocks.destroy');
            Route::put('/listings/{listing}/schedules', [AvailabilityManageController::class, 'saveSchedules'])->name('schedules.save');
            Route::patch('/slots/{slot}/status', [AvailabilityManageController::class, 'slotStatus'])->name('slots.status');

            Route::get('/check-in', [CheckInController::class, 'index'])->name('check-in');
            Route::post('/check-in', [CheckInController::class, 'find'])->middleware('throttle:60,1')->name('check-in.find');
            Route::get('/check-in/{token}', [CheckInController::class, 'show'])->name('check-in.show');
            Route::post('/check-in/{token}', [CheckInController::class, 'store'])->name('check-in.store');
            Route::post('/slots/{slot}/weather-cancel', [BookingRequestController::class, 'weatherCancel'])->name('slots.weather-cancel');
        });

        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/users', [AdminController::class, 'users'])->name('users');
            Route::patch('/users/{user}/status', [AdminController::class, 'updateStatus'])->name('users.status');
            Route::get('/listings', [ListingModerationController::class, 'index'])->name('listings');
            Route::patch('/listings/{listing}', [ListingModerationController::class, 'update'])->name('listings.moderate');
            Route::get('/bookings', [BookingAdminController::class, 'index'])->name('bookings');
            Route::post('/bookings/{booking}/cancel', [BookingAdminController::class, 'cancel'])->name('bookings.cancel');
            Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
            Route::get('/reports', [SupportController::class, 'reports'])->name('reports');
            Route::patch('/reports/{report}', [SupportController::class, 'resolve'])->name('reports.resolve');
            Route::get('/messages', [SupportController::class, 'messages'])->name('messages');
            Route::patch('/messages/{message}', [SupportController::class, 'readMessage'])->name('messages.read');
            Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
            Route::get('/reviews', [ReviewModerationController::class, 'index'])->name('reviews');
            Route::patch('/reviews/{review}', [ReviewModerationController::class, 'update'])->name('reviews.moderate');
        });
    });
});

// /stays, /dining, /experiences and their detail pages
Route::get('/{segment}', [ListingController::class, 'index'])
    ->whereIn('segment', ['stays', 'dining', 'experiences'])->name('listings.index');
Route::get('/{segment}/{listing}', [ListingController::class, 'show'])
    ->whereIn('segment', ['stays', 'dining', 'experiences'])->name('listings.show');
