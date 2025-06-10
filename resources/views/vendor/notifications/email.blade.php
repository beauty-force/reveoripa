<x-mail::message>

<div style="text-align: center; margin-bottom: 20px;">
    <img src='https://reve-oripa.jp/images/logo.png' style='width:100%; max-width:300px;'>
</div>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Social Media Links --}}
<div style="text-align: center; margin: 20px 0;">
    <a href="https://liff.line.me/2006919906-RVxZmy1Y/landing?follow=%40775loedl&lp=6KyRyz&liff_id=2006919906-RVxZmy1Y" style="display: inline-block; margin: 0 10px; text-decoration: none;">
        <img src="https://reve-oripa.jp/images/line.png" alt="LINE" style="width: 50px; height: 50px; border: none;">
    </a>
    <a href="https://x.com/Cardshop_eve" style="display: inline-block; margin: 0 10px; text-decoration: none;">
        <img src="https://reve-oripa.jp/images/twitter.jpg" alt="X" style="width: 50px; height: 50px; border: none;">
    </a>
</div>

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards'),<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang(
    "「:actionText」ボタンをクリックできない場合は、以下のURLをコピーして\n".
    'ウェブブラウザに貼り付けてアクセスしてください。',
    [
        'actionText' => $actionText,
    ]
) <br/><span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
