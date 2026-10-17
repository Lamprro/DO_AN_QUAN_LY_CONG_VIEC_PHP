<x-input name="name" label="Tên vai trò" :value="isset($role) ? $role->name : ''" required maxlength="100" />
@php($selectedPermissions = old('permissions', isset($role) ? $role->permissions : []))
@php($selectedPermissions = is_array($selectedPermissions) ? $selectedPermissions : [])
<fieldset><legend>Quyền được phép cấp</legend><p class="muted">Cần workspace.view để xem workspace. Bạn chỉ cấp được các quyền mình đang có.</p><div class="permissions">@foreach($permissionCatalog as $permission)<label><input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission,$selectedPermissions,true))> {{ $permission }}</label>@endforeach</div></fieldset>
<p class="actions"><button type="submit">Lưu vai trò</button><a href="{{ route('workspaces.roles.index',$workspace) }}">Hủy</a></p>
