@props([
    'value' => '',
    'name',
    'label'=>'',
    // SETTINGS-FIX-02 (SET-05): the validation key when it differs from the field name (footer[description] is
    // validated as footer.description); without it, the name (as before).
    'errorKey' => null,
])
@php($errorKey = $errorKey ?? $name)
{{-- SETTINGS-FIX-02 (SET-06): the textarea has an id (the given one, else its name, as the input component) and its
     label points to it. --}}
@php($id = $attributes->get('id', $name))
@if ($label)
    <label for="{{$id}}">
        {{ $label }}
    </label>
@endif

<textarea
    id="{{$id}}"
    name="{{$name}}"
    rows="4"
    {{$attributes->except('id')->class([
        'form-control',
        'is-invalid' => $errors->has($errorKey)
    ])}}
>{{old($errorKey,$value)}}</textarea>

{{-- Validation --}}
@error($errorKey)
    <div class="invalid-feedback">
        {{$message}}
    </div>
@enderror
