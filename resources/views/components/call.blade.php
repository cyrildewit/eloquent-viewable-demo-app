@props(['code'])
<code {{ $attributes->merge(['class' => 'call']) }} title="{{ $code }}">{!! preg_replace('/(-&gt;|::)([a-zA-Z_]+)(?=\()/', '$1<span class="fn">$2</span>', e($code)) !!}</code>
