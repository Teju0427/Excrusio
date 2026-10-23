@props(['listing', 'saved' => false])
{{-- Works without JavaScript (plain form); excursio.js upgrades it to an instant toggle. --}}
<form method="POST" action="{{ route('wishlist.toggle', $listing) }}" {{ $attributes->merge(['class' => 'ex-heart-form']) }} data-ex-wishlist>
    @csrf
    <button type="submit" class="ex-heart {{ $saved ? 'is-saved' : '' }}" aria-pressed="{{ $saved ? 'true' : 'false' }}"
        aria-label="{{ $saved ? 'Remove '.$listing->title.' from saved' : 'Save '.$listing->title }}">
        <i class="bi {{ $saved ? 'bi-heart-fill' : 'bi-heart' }}"></i>
    </button>
</form>
