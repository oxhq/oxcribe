<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="{{ $viewerCssUrl }}">
</head>
<body>
<div
    id="oxcribe-docs-app"
    data-title="{{ $title }}"
    data-payload-url="{{ $payloadUrl }}"
    data-openapi-url="{{ $openApiUrl }}"
></div>

<script src="{{ $viewerJsUrl }}" defer></script>
</body>
</html>
