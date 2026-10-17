<x-input name="name" label="Tên vai trò" :value="isset($role) ? $role->name : ''" required maxlength="100" />
@php($selectedPermissions = old('permissions', isset($role) ? $role->permissions : []))
@php($selectedPermissions = is_array($selectedPermissions) ? $selectedPermissions : [])
<p class="notice">Quyền xem workspace là điều kiện để truy cập. Chỉ các quyền bạn được phép cấp mới xuất hiện. Quyền project còn phụ thuộc membership riêng của project.</p>
@foreach($permissionGroups as $group=>$permissions)<fieldset class="card"><legend>{{ $group }}</legend><div class="permissions">@foreach($permissions as $permission)<label for="permission-{{ str_replace('.','-',$permission['value']) }}"><input id="permission-{{ str_replace('.','-',$permission['value']) }}" type="checkbox" name="permissions[]" value="{{ $permission['value'] }}" @checked(in_array($permission['value'],$selectedPermissions,true))> {{ $permission['label'] }}</label>@endforeach</div></fieldset>@endforeach
<p class="actions"><button type="submit">Lưu vai trò</button><a href="{{ route('workspaces.roles.index',$workspace) }}">Hủy</a></p>
