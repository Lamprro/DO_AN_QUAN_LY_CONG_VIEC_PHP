<div class="actions calendar-navigation" aria-label="Điều hướng thời gian"><a class="button secondary" href="{{ $navigation['previous'] }}">← Trước</a><a class="button secondary" href="{{ $navigation['today'] }}">Hôm nay</a><a class="button secondary" href="{{ $navigation['next'] }}">Sau →</a></div>
<p class="notice"><strong>{{ $range['start']->format('d/m/Y') }} → {{ $range['end']->format('d/m/Y') }}</strong> · Ngày cuối không bao gồm · Múi giờ hiển thị: <strong>{{ $range['timezone'] }}</strong></p>
<form method="GET" data-form="calendar-range" class="range-form">
@if(request('project_id'))<input type="hidden" name="project_id" value="{{ request('project_id') }}">
@endif
<label for="view">Chế độ</label><select id="view" name="view">
@foreach(['day'=>'Ngày','week'=>'Tuần','month'=>'Tháng','list'=>'Danh sách'] as $key=>$label)<option value="{{ $key }}"
@selected($range['mode']===$key)>{{ $label }}</option>
@endforeach</select>
<x-input name="date" label="Ngày tham chiếu" type="date" :value="$range['start']->format('Y-m-d')"/>
<details><summary>Phạm vi tùy chọn và sự kiện đã hủy</summary><p>Nhập đủ từ/đến sẽ thay phạm vi của chế độ, tối đa93 ngày. Xóa hai ngày để dùng ngày/tuần/tháng.</p><div class="form-grid"><x-input name="from" label="Từ ngày" type="date" :value="request('from','')"/><x-input name="to" label="Đến ngày (không gồm)" type="date" :value="request('to','')"/></div><label><input type="checkbox" name="include_cancelled" value="1"
@checked($range['includeCancelled'])> Hiện sự kiện đã hủy</label></details><button type="submit">Xem lịch</button></form>
<div class="calendar-legend" aria-label="Chú giải lịch">
@foreach(['TASK_BLOCK','MEETING','BUSY','NOTE','EXTRA_AVAILABLE'] as $type)<span class="event-kind kind-{{ strtolower($type) }}">{{ \App\Support\UiLabels::text($type) }}</span>
@endforeach<span class="event-kind">Bận riêng tư: chỉ khoảng giờ, không có chi tiết</span></div>
