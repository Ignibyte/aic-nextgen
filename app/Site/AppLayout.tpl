<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $appLayout->name }}</title><link rel="stylesheet" href="/assets/aic.css"></head>
<body><h1>{{ $appLayout->name }}</h1>
{!! $appLayout->regions()->html('content') !!}
{!! $appLayout->regions()->html('nav') !!}
</body></html>
