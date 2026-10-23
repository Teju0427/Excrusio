@extends('layouts.panel', ['title' => 'Categories'])
@section('panel')
<h1 class="mb-2" style="font-size: 2.4rem">Categories</h1>
<p class="text-muted mb-4">Used for filters and on every card. Categories in use can be switched off but not deleted.</p>
<div class="ex-panel mb-4">
    <h2 class="h6 mb-3">Add a category</h2>
    <form method="POST" action="{{ route('admin.categories.store') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-3"><label class="form-label small" for="nc-type">Type</label><select id="nc-type" name="listing_type" class="form-select form-select-sm">@foreach ($types as $t)<option value="{{ $t->value }}">{{ $t->plural() }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label small" for="nc-name">Name</label><input id="nc-name" name="name" required maxlength="80" value="{{ old('name') }}" class="form-control form-control-sm @error('name') is-invalid @enderror">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-3"><label class="form-label small" for="nc-icon">Icon <a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener" class="text-muted">(Bootstrap Icons)</a></label><input id="nc-icon" name="icon" maxlength="60" placeholder="bi-tree" value="{{ old('icon') }}" class="form-control form-control-sm @error('icon') is-invalid @enderror"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-ex-primary w-100">Add</button></div>
    </form>
</div>
@foreach ($types as $t)
    <div class="ex-panel mb-4 p-0 overflow-hidden">
        <h2 class="h6 px-3 pt-3 mb-2"><i class="bi {{ $t->icon() }} me-1"></i>{{ $t->plural() }}</h2>
        <div class="table-responsive"><table class="table table-sm align-middle small mb-0">
            <thead><tr><th class="ps-3">Name</th><th>Icon</th><th>Order</th><th>Shown</th><th class="text-end">Listings</th><th class="pe-3"></th></tr></thead>
            <tbody>
                @foreach ($groups[$t->value] ?? [] as $c)
                    <tr>
                        <td class="ps-3"><input form="cat{{ $c->id }}" name="name" value="{{ $c->name }}" class="form-control form-control-sm" required maxlength="80" aria-label="Name"></td>
                        <td><div class="input-group input-group-sm"><span class="input-group-text"><i class="bi {{ $c->icon ?? 'bi-tag' }}"></i></span><input form="cat{{ $c->id }}" name="icon" value="{{ $c->icon }}" class="form-control" maxlength="60" aria-label="Icon"></div></td>
                        <td style="width: 80px"><input form="cat{{ $c->id }}" name="sort_order" type="number" min="0" max="999" value="{{ $c->sort_order }}" class="form-control form-control-sm" aria-label="Order"></td>
                        <td><div class="form-check form-switch"><input form="cat{{ $c->id }}" class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" @checked($c->is_active) aria-label="Shown"></div></td>
                        <td class="text-end">{{ $c->listings_count }}</td>
                        <td class="pe-3 text-end text-nowrap">
                            <form id="cat{{ $c->id }}" method="POST" action="{{ route('admin.categories.update', $c) }}" class="d-inline">@csrf @method('PUT')<button class="btn btn-sm btn-ex-outline">Save</button></form>
                            @unless ($c->listings_count)
                                <form method="POST" action="{{ route('admin.categories.destroy', $c) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-muted" aria-label="Delete {{ $c->name }}"><i class="bi bi-trash"></i></button></form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </div>
@endforeach
@endsection
