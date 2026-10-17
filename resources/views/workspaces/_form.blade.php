<x-input name="name" label="Tên workspace" :value="isset($workspace) ? $workspace->name : ''" required maxlength="160" />
<x-timezone-select :timezones="$timezones" :value="isset($workspace) ? $workspace->timezone : auth()->user()->timezone" />
<div class="actions"><button type="submit">Lưu workspace</button><a href="{{ route('workspaces.index') }}">Hủy</a></div>
