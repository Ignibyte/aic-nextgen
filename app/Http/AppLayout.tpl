<!doctype html><html lang="en"><head><meta charset="utf-8"><title>{{ $appLayout->name }}</title></head>
<body><h1>{{ $appLayout->name }}</h1>
{!! $appLayout->regions()->html('content') !!}
{!! $appLayout->regions()->html('nav') !!}
</body></html>
