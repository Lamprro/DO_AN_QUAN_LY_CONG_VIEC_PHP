@props(['timezones', 'value' => 'Asia/Ho_Chi_Minh', 'label' => 'Múi giờ'])
@php($selectedZone = old('timezone', $value ?: 'Asia/Ho_Chi_Minh'))
<div class="field timezone-combobox" data-timezone-picker>
    <label for="timezone-search">{{ $label }}</label>
    <div class="timezone-picker-wrap">
        <input 
            type="text" 
            id="timezone-search" 
            class="timezone-search-input" 
            autocomplete="off" 
            placeholder="Gõ để tìm múi giờ (vd: Asia/Ho_Chi_Minh, Tokyo, London...)" 
            value="{{ $selectedZone }}"
            aria-autocomplete="list"
            aria-expanded="false"
        >
        <span class="timezone-toggle-btn" role="button" tabindex="0" aria-label="Mở danh sách múi giờ">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </span>
        <ul id="timezone-results" class="timezone-results" role="listbox" hidden></ul>
    </div>
    <select name="timezone" id="timezone" class="timezone-native-select" required tabindex="-1" aria-hidden="true">
        @foreach($timezones as $tz)
            <option value="{{ $tz }}" @selected($selectedZone === $tz)>{{ $tz }}</option>
        @endforeach
    </select>
    <output id="timezone-example" class="muted timezone-live-clock" aria-live="polite"></output>
    @error('timezone')<small class="error">{{ $message }}</small>@enderror
</div>
