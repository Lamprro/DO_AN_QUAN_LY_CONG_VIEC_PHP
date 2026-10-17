<form method="GET" data-form="calendar-range"><label for="view">Chế độ</label><select id="view" name="view">@foreach(['month'=>'Tháng','week'=>'Tuần','list'=>'Danh sách'] as $key=>$label)<option value="{{ $key }}" @selected($range['mode']===$key)>{{ $label }}</option>@endforeach</select>
<x-input name="date" label="Ngày tham chiếu" type="date" :value="$range['start']->format('Y-m-d')"/>
<div class="grid"><x-input name="from" label="Từ ngày (tùy chọn)" type="date" :value="request('from','')"/><x-input name="to" label="Đến ngày (không gồm)" type="date" :value="request('to','')"/></div>
<label><input type="checkbox" name="include_cancelled" value="1" @checked($range['includeCancelled'])> Hiện sự kiện đã hủy</label><button type="submit">Xem lịch</button></form>
<p>{{ $range['start']->format('d/m/Y') }} → {{ $range['end']->format('d/m/Y') }} (không gồm ngày cuối) · {{ $range['timezone'] }}</p>
