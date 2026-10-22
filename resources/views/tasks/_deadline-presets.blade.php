<div class="field-presets deadline-presets" aria-label="Gợi ý hạn chót">
@foreach($deadlinePresets as $preset)
<button type="button" class="preset-chip" data-preset="{{ $preset['key'] }}" data-value="{{ $preset['value'] }}">{{ $preset['label'] }}</button>
@endforeach
<button type="button" class="preset-chip" data-preset="clear" data-value="">Bỏ hạn</button>
</div><small class="muted">Theo {{ $workspace->timezone }} tại lúc mở trang. Tải lại nếu đã sang ngày mới. 17:30 là gợi ý, không phải lịch làm việc.</small>
