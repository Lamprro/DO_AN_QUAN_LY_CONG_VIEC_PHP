@props(['name','label','type'=>'text','value'=>'','required'=>false,'id'=>null])
@php($fieldValue = old($name, $value))
@php($fieldId = $id ?? $name)
<div class="field"><label for="{{ $fieldId }}">{{ $label }} @if($required)<span aria-hidden="true">*</span>@endif</label>
<input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" @if($type !== 'password') value="{{ is_scalar($fieldValue) ? $fieldValue : '' }}" @endif @required($required) {{ $attributes }} @error($name) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror>
@error($name)<small id="{{ $fieldId }}-error" class="error">{{ $message }}</small>@enderror</div>
