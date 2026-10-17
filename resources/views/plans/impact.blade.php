<x-layout title="Phân tích ảnh hưởng"><section class="card"><h1>Quá hạn, blocker và dependency</h1><a href="{{ route('workspaces.plans.index',[$workspace,'project_id'=>$project?->id]) }}">Kế hoạch</a></section>
@include('plans._impact')</x-layout>
