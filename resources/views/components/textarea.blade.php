@props(['name','label','value'=>'','id'=>null])
@php($fieldId=$id??$name)
@php($fieldValue=old($name,$value))
<div class="field"><label for="{{ $fieldId }}">{{ $label }}</label>
<textarea id="{{ $fieldId }}" name="{{ $name }}" rows="5" {{ $attributes }} @error($name) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror>{{ is_string($fieldValue)?$fieldValue:'' }}</textarea>
@error($name)<small id="{{ $fieldId }}-error" class="error">{{ $message }}</small>@enderror</div>
