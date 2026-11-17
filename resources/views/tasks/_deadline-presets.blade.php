<div class="field-presets deadline-presets" aria-label="Gợi ý hạn chót">
@foreach($deadlinePresets as $preset)
<button type="button" class="preset-chip" data-preset="{{ $preset['key'] }}" data-value="{{ $preset['value'] }}">{{ $preset['label'] }}</button>
@endforeach
<button type="button" class="preset-chip" data-preset="clear" data-value="" style="color: var(--danger, #dc2626) !important;">✕ Bỏ hạn</button>
</div><small class="muted" style="display: block; margin-top: 4px; font-size: 0.725rem; line-height: 1.35;">Theo {{ $workspace->timezone }} lúc mở trang. 17:30 là gợi ý hạn chót tham khảo.</small>
