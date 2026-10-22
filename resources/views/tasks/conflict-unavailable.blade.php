<x-layout title="Không thể lưu bản đối chiếu">
<section class="card">
 <h1>Bản đối chiếu chưa thể lưu</h1>
 <ul>
 @foreach($messages as $items)
  @foreach($items as $message)
   <li>{{ $message }}</li>
  @endforeach
 @endforeach
 </ul>
 <p>Dữ liệu công việc chưa bị thay đổi. Mở dữ liệu hiện tại để bắt đầu lại.</p>
 <a class="button" href="{{ route($routePrefix.'.edit',[...$routeParams,$task,'fresh'=>1]) }}">Mở bản hiện tại</a>
</section>
</x-layout>
