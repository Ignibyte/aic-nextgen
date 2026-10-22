<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $appLayout->title() }}</title><meta name="tessera-layout" content="{{ $appLayout->tesseraLayout() }}">
<meta name="tessera-csrf-token" content="{{ $appLayout->csrfToken() }}"><meta name="tessera-csrf-header" content="{{ $appLayout->csrfHeader() }}">
{!! $appLayout->trackedAssets() !!}</head>
<body><h1>{{ $appLayout->name }}</h1>
{!! $appLayout->regions()->html('content') !!}
{!! $appLayout->regions()->html('nav') !!}
</body></html>
