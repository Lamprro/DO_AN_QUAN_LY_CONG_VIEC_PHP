@if($project)
<input type="hidden" name="expected_revision" value="{{ old('expected_revision',$project->revision) }}">
<p class="muted">Bản dữ liệu #{{ $project->revision }}. <a href="{{ request()->url() }}?fresh=1">Mở dữ liệu hiện tại (bỏ bản nhập chưa lưu)</a></p>
@endif
<x-input name="name" label="Tên project" :value="$project?->name ?? ''" required maxlength="180" />
<x-textarea name="description" label="Mô tả" :value="$project?->description ?? ''" maxlength="20000" />
<div class="field"><label for="parent_project_id">Project cha (tùy chọn)</label>
<select id="parent_project_id" name="parent_project_id"><option value="">Không có</option>
@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_project_id',$project?->parent_project_id)===$parent->id)>{{ $parent->name }}</option>@endforeach</select></div>
<div class="field"><label for="status">Trạng thái</label><select name="status" id="status">@foreach(\App\Enums\ProjectStatus::cases() as $status)<option value="{{ $status->value }}" @selected(old('status',$project?->status->value ?? 'draft')===$status->value)>{{ \App\Support\UiLabels::text($status) }}</option>@endforeach</select></div>
<x-input name="start_date" label="Ngày bắt đầu" type="date" :value="$project?->start_date?->format('Y-m-d') ?? ''" />
<x-input name="deadline" label="Hạn hoàn thành" type="date" :value="$project?->deadline?->format('Y-m-d') ?? ''" />
<div class="actions"><button type="submit">Lưu project</button><a href="{{ route('workspaces.projects.index',$workspace) }}">Về danh sách</a></div>
