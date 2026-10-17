@props(['timezones','value'=>'Asia/Ho_Chi_Minh'])
<div class="field"><label for="timezone">Múi giờ</label><select name="timezone" id="timezone" required>@foreach($timezones as $tz)<option value="{{ $tz }}" @selected(old('timezone',$value) === $tz)>{{ $tz }}</option>@endforeach</select>@error('timezone')<small class="error">{{ $message }}</small>@enderror</div>
