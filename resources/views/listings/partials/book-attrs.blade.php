@php($viewer = auth()->user())
data-auth="{{ ! $viewer ? 'guest' : ($viewer->isCustomer() ? 'customer' : 'other') }}"
data-login="{{ route('login', ['next' => '/'.request()->path()]) }}"
data-book="{{ $listing->listing_type->value === 'stay' ? route('book.stay', $listing) : route('book.slot', $listing) }}"
data-trip="{{ $listing->listing_type->value === 'stay' ? route('trip.add-stay', $listing) : route('trip.add-slot', $listing) }}"
data-waitlist="{{ url('/waitlist/__ID__') }}"
data-key="{{ \Illuminate\Support\Str::uuid() }}"
