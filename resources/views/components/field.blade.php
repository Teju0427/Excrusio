@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'autocomplete' => null, 'required' => false])
<div class="mb-3">
    <label for="f-{{ $name }}" class="form-label">{{ $label }}@if (! $required)<span class="text-muted fw-normal"> (optional)</span>@endif</label>
    <input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        value="{{ $type === 'password' ? '' : old($name, $value) }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($required) required @endif
        @error($name) aria-invalid="true" aria-describedby="e-{{ $name }}" @enderror
        {{ $attributes->merge(['class' => 'form-control'.($errors->has($name) ? ' is-invalid' : '')]) }}>
    @error($name)<div id="e-{{ $name }}" class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help && ! $errors->has($name))<div class="form-text">{{ $help }}</div>@endif
</div>
