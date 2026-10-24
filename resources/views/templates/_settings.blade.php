@php($t=$template??null)
<x-input name="name" label="Tên mẫu" :value="$t?->name??''" required maxlength="160"/>
<label for="frequency">Lịch lặp</label><select id="frequency" name="frequency">@foreach([''=>'Chỉ tạo thủ công','daily'=>'Hàng ngày','weekly'=>'Hàng tuần','monthly'=>'Hàng tháng'] as $key=>$label)<option value="{{ $key }}" @selected(old('frequency',$t?->frequency??'')===$key)>{{ $label }}</option>@endforeach</select>
<x-input name="interval" label="Mỗi bao nhiêu ngày/tuần/tháng (1–12)" type="number" :value="$t?->interval??1" required min="1" max="12"/>
<div class="form-grid"><x-input name="starts_on" type="date" label="Ngày bắt đầu lặp" :value="$t?->starts_on?->format('Y-m-d')??now($workspace->timezone)->format('Y-m-d')"/><x-input name="ends_on" type="date" label="Ngày kết thúc (tùy chọn)" :value="$t?->ends_on?->format('Y-m-d')??''"/></div>
<x-input name="local_time" type="time" label="Giờ tạo trong ngày" :value="$t?->local_time??'09:00'"/>
<x-input name="timezone" label="Múi giờ lịch lặp" :value="$t?->timezone??$workspace->timezone" required/>
<x-input name="deadline_offset_days" type="number" label="Hạn task mới sau ngày tạo bao nhiêu ngày (để trống nếu không đặt hạn)" :value="$t?->deadline_offset_days??''" min="0" max="365"/>
