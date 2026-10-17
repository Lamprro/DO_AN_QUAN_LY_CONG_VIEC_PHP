<x-layout title="Tạo task"><section class="card"><h1>Tạo task</h1><form action="{{ route($routePrefix.'.store',$routeParams) }}" method="POST" data-form="task-create">@csrf
@include('tasks._form')</form></section></x-layout>
