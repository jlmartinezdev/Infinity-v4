@props(['asunto' => null, 'size' => 'md'])

@php
    $sizes = [
        'xs' => ['tile' => 'w-6 h-6 rounded-md', 'icon' => 'w-3.5 h-3.5', 'stroke' => 2],
        'sm' => ['tile' => 'w-8 h-8 rounded-lg', 'icon' => 'w-[18px] h-[18px]', 'stroke' => 1.9],
        'md' => ['tile' => 'w-10 h-10 rounded-xl', 'icon' => 'w-6 h-6', 'stroke' => 1.75],
        'lg' => ['tile' => 'w-14 h-14 rounded-2xl', 'icon' => 'w-8 h-8', 'stroke' => 1.6],
        'xl' => ['tile' => 'w-24 h-24 rounded-3xl', 'icon' => 'w-14 h-14', 'stroke' => 1.4],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
    $style = $asunto ? $asunto->tileStyle() : 'background-color:#8b5cf61f;color:#8b5cf6;border:1px solid #8b5cf64d;';
    $svg = $asunto ? $asunto->iconoSvg($s['icon'], $s['stroke']) : \App\Support\TicketAsuntoIconos::svg(null, $s['icon'], $s['stroke']);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center shrink-0 '.$s['tile']]) }}
      style="{{ $style }}"
      @if($asunto) title="{{ $asunto->nombre }}" @endif>
    {!! $svg !!}
</span>
