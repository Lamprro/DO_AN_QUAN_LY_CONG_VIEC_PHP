<x-layout title="Sửa project"><section class="card"><h1>Sửa project</h1>
<form action="{{ route('workspaces.projects.update',[$workspace,$project]) }}" method="POST" data-form="project-edit">@csrf @method('PATCH')
@include('projects._form')</form></section></x-layout>
