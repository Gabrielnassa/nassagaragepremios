<span class="placa num @foreach (explode(' ', $tipo ?? '') as $t)@if ($t) placa--{{ $t }}@endif @endforeach">{{ $numero }}</span>
