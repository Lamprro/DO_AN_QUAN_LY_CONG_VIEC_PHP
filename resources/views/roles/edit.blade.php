<x-layout title="Sửa vai trò: {{ $role->name }} — {{ $workspace->name }}">
<div style="max-width: 960px; margin: 0 auto 1.5rem;">
    <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.5rem;">
        <a href="{{ route('workspaces.show', $workspace) }}" style="color: var(--color-primary);">{{ $workspace->name }}</a>
        <span style="margin: 0 0.375rem;">/</span>
        <a href="{{ route('workspaces.roles.index', $workspace) }}" style="color: var(--color-primary);">Vai trò</a>
        <span style="margin: 0 0.375rem;">/</span>
        <span>Chỉnh sửa: {{ $role->name }}</span>
    </div>
    <section class="card" style="padding: 1.75rem;">
        <h1 style="font-size: 1.5rem; font-weight: 700; margin-top: 0; margin-bottom: 1.25rem; color: var(--color-text);">
            Sửa vai trò: {{ $role->name }}
        </h1>
        <form action="{{ route('workspaces.roles.update', [$workspace, $role]) }}" method="POST" data-form="role-edit">
            @csrf
            @method('PATCH')
            @include('roles._form')
        </form>
    </section>
</div>
</x-layout>
