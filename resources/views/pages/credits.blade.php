@extends('layouts.app', ['title' => 'Image credits'])
@section('content')
<section class="ex-section"><div class="container" style="max-width: 900px">
    <h1 style="font-size: 2.6rem">Image credits</h1>
    <hr class="ex-rule">
    <p class="text-muted">All photos are from Wikimedia Commons under free licences. They have been cropped and resized; derivatives of share-alike (BY-SA) photos are shared under the same licence.</p>
    @if (empty($credits))
        <p class="text-muted">No photo credits recorded.</p>
    @else
        <div class="table-responsive"><table class="table small align-middle">
            <thead><tr><th>Photo</th><th>Author</th><th>Licence</th><th>Source</th></tr></thead>
            <tbody>
                @foreach ($credits as $c)
                    <tr><td>{{ $c['description'] }}</td><td>{{ $c['author'] }}</td><td>{{ $c['license'] }}</td><td><a href="{{ $c['source'] }}" rel="noopener" target="_blank">View original</a></td></tr>
                @endforeach
            </tbody>
        </table></div>
    @endif
</div></section>
@endsection
