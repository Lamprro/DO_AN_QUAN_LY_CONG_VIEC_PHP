<x-layout title="Tạo công việc">
<div style="max-width: 860px; margin: 0 auto 2rem;">
    <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.5rem;">
        <a href="{{ route('workspaces.show', $workspace) }}" style="color: var(--color-primary);">{{ $workspace->name }}</a>
        <span style="margin: 0 0.375rem;">/</span>
        <a href="{{ route($routePrefix.'.index', $routeParams) }}" style="color: var(--color-primary);">Công việc</a>
        <span style="margin: 0 0.375rem;">/</span>
        <span>Tạo mới</span>
    </div>

    <section class="card" style="padding: 1.75rem;">
        <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 1.5rem; color: var(--color-text); letter-spacing: -0.01em;">
            Tạo công việc mới
        </h1>
        <form action="{{ route($routePrefix.'.store', $routeParams) }}" method="POST" data-form="task-create" data-dirty-form>
            @csrf
            @include('tasks._form')
        </form>
    </section>
</div>
</x-layout>
