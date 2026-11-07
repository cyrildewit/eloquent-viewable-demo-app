@props(['user'])
<span {{ $attributes->merge(['class' => 'inline-grid shrink-0 place-items-center rounded-full font-semibold text-white']) }}
      style="background: linear-gradient(135deg, oklch(0.68 0.15 {{ $user->hue }}), oklch(0.5 0.16 {{ ($user->hue + 40) % 360 }}))">{{ $user->initials() }}</span>
