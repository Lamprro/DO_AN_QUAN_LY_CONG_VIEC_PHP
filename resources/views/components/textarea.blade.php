@props(['name','label','value'=>'','id'=>null])
@php($fieldId=$id??$name)
@php($fieldValue=old($name,$value))
<div class="field"><label for="{{ $fieldId }}">{{ $label }}</label>
<textarea id="{{ $fieldId }}" name="{{ $name }}" rows="5" {{ $attributes }}>{{ is_string($fieldValue)?$fieldValue:'' }}</textarea>
@error($name)<small class="error">{{ $message }}</small>@enderror</div>
