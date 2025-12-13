<?php
/**
 * @var bool $enabled
 * @var string $id
 * @var string $domain
 * @var \Spatie\GoogleTagManager\DataLayer $dataLayer
 * @var iterable<\Spatie\GoogleTagManager\DataLayer> $pushData
 */
?>
@if($enabled)
    <script>
        function gtmPush() {
            @foreach($pushData as $item)
            window.dataLayer.push({!! json_encode($item->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!});
            @endforeach
        }
        addEventListener("load", gtmPush);
    </script>
    <noscript>
        <iframe
            src="https://{{ $domain }}/ns.html?id={{ $id }}"
            height="0"
            width="0"
            style="display:none;visibility:hidden"
        ></iframe>
    </noscript>
@endif

