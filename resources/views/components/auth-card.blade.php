@props(['title', 'lead' => null])
<section class="ex-auth">
    <div class="ex-auth-media d-none d-lg-block">
        @php($img = seed_image('images/listings/destination-tuscany.webp'))
        @if ($img)<img src="{{ $img }}" alt="" loading="eager">@endif
        <div class="ex-auth-quote">
            <div class="ex-serif">Stay somewhere special.<br>Experience something extraordinary.</div>
        </div>
    </div>
    <div class="ex-auth-body">
        <div class="ex-auth-inner">
            <h1 class="mb-2" style="font-size: 2.6rem">{{ $title }}</h1>
            @if ($lead)<p class="text-muted mb-4">{{ $lead }}</p>@endif
            @if (session('status'))<div class="alert alert-success small" role="status">{{ session('status') }}</div>@endif
            {{ $slot }}
        </div>
    </div>
</section>
