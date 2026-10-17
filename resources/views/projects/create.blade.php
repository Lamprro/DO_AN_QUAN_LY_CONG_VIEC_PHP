<x-layout title="Tạo project"><section class="card"><h1>Tạo project</h1>
<form action="{{ route('workspaces.projects.store',$workspace) }}" method="POST" data-form="project-create">@csrf
@include('projects._form')</form></section></x-layout>
