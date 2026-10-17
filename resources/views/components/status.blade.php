@props(['value'])
@php($raw=$value instanceof \BackedEnum?$value->value:(string)$value)
<span class="status-pill status-{{ strtolower(str_replace('_','-',$raw)) }}">{{ \App\Support\UiLabels::text($value) }}</span>
