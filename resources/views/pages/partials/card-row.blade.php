@if ($items->isEmpty())
    <div class="ex-empty"><i class="bi bi-hourglass"></i>{{ $empty }}</div>
@else
    <div class="row g-4 ex-row-scroll">
        @foreach ($items as $listing)
            <div class="col-md-6 col-lg-3"><x-listing-card :listing="$listing" :saved="isset($savedIds) ? in_array($listing->id, $savedIds) : null" /></div>
        @endforeach
    </div>
@endif
