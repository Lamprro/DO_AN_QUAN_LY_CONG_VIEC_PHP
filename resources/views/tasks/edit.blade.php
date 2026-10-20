<x-layout title="Sửa công việc: {{ $task->code }}">
<div style="max-width: 860px; margin: 0 auto 2rem;">
    <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.5rem;">
        <a href="{{ route('workspaces.show', $workspace) }}" style="color: var(--color-primary);">{{ $workspace->name }}</a>
        <span style="margin: 0 0.375rem;">/</span>
        <a href="{{ route($routePrefix.'.index', $routeParams) }}" style="color: var(--color-primary);">Công việc</a>
        <span style="margin: 0 0.375rem;">/</span>
        <a href="{{ route($routePrefix.'.show', [...$routeParams, $task]) }}" style="color: var(--color-primary);">{{ $task->code }}</a>
        <span style="margin: 0 0.375rem;">/</span>
        <span>Chỉnh sửa</span>
    </div>

    <section class="card" style="padding: 1.75rem;">
        <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 1.5rem; color: var(--color-text); letter-spacing: -0.01em;">
            Chỉnh sửa công việc: {{ $task->code }}
        </h1>
        <form action="{{ route($routePrefix.'.update', [...$routeParams, $task]) }}" method="POST" data-form="task-edit">
            @csrf
            @method('PATCH')
            @include('tasks._form')
        </form>
    </section>
</div>
</x-layout>
