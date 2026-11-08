@props([
    'type' => 'text',
    'value' => '',
    'name',
    'label'=>'',
    're' => false,
    // SETTINGS-FIX-02 (SET-05): the validation key when it differs from the field name (footer[tagline] is validated
    // as footer.tagline); without it, the name (as before).
    'errorKey' => null,
])
@php($errorKey = $errorKey ?? $name)
@if ($label)
    <label  class="form-label" for="{{$name}}">
        {{ $label }}
        @if($re)
            <span style="color: red">*</span>
        @endif
    </label>
@endif

<input
    type="{{$type}}"
    id="{{$name}}"
    name="{{$name}}"
    value="{{old($errorKey, $value)}}"
    {{$attributes->class([
        'form-control',
        'is-invalid' => $errors->has($errorKey)
    ])}}
/>

{{-- Validation --}}
@error($errorKey)
    <div class="invalid-feedback">
        {{$message}}
    </div>
@enderror
