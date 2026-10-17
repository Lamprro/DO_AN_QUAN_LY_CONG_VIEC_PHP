<x-layout title="Sửa task"><section class="card"><h1>Sửa task</h1><form action="{{ route($routePrefix.'.update',[...$routeParams,$task]) }}" method="POST" data-form="task-edit">@csrf @method('PATCH')
@include('tasks._form')</form></section></x-layout>
