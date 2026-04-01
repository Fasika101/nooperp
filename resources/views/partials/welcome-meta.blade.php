@php
    $siteName = config('app.name', 'Liba Digitals');
    $pageTitle = $siteName.' — ERP & inventory';
    $metaDescription = 'Liba Digitals ERP: unified operations for retail and optical — sales, POS, stock, finance, and inventory. Sign in to manage your business.';
    $canonicalUrl = url('/');
    // Telegram & many crawlers do not use SVG for link previews — raster only for og:image
    $ogRasterPaths = ['images/logo.png', 'images/logo.jpg', 'images/logo.jpeg', 'images/logo.webp'];
    $ogImagePath = null;
    foreach ($ogRasterPaths as $rel) {
        if (file_exists(public_path($rel))) {
            $ogImagePath = $rel;
            break;
        }
    }
    $ogImageUrl = null;
    $ogImageWidth = null;
    $ogImageHeight = null;
    $ogImageType = null;
    if ($ogImagePath !== null) {
        $ogImageUrl = asset($ogImagePath);
        if (str_starts_with((string) config('app.url'), 'https://')) {
            $ogImageUrl = preg_replace('#^http://#', 'https://', $ogImageUrl) ?? $ogImageUrl;
        }
        $ext = strtolower(pathinfo($ogImagePath, PATHINFO_EXTENSION));
        $ogImageType = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };
        $absFile = public_path($ogImagePath);
        if (is_readable($absFile) && in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            $dims = @getimagesize($absFile);
            if ($dims !== false) {
                $ogImageWidth = $dims[0];
                $ogImageHeight = $dims[1];
            }
        }
    }
@endphp

<title>{{ $pageTitle }}</title>
<meta name="title" content="{{ $pageTitle }}">
<meta name="description" content="{{ $metaDescription }}">
<meta name="robots" content="index, follow">
<link rel="canonical" href="{{ $canonicalUrl }}">

{{-- Open Graph (WhatsApp, Telegram, Facebook, LinkedIn, etc.) --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
@if ($ogImageUrl)
    <meta property="og:image" content="{{ $ogImageUrl }}">
    <meta property="og:image:alt" content="{{ $siteName }} logo">
    @if ($ogImageType)
        <meta property="og:image:type" content="{{ $ogImageType }}">
    @endif
    @if ($ogImageWidth && $ogImageHeight)
        <meta property="og:image:width" content="{{ $ogImageWidth }}">
        <meta property="og:image:height" content="{{ $ogImageHeight }}">
    @endif
    @if (str_starts_with($ogImageUrl, 'https://'))
        <meta property="og:image:secure_url" content="{{ $ogImageUrl }}">
    @endif
    <link rel="image_src" href="{{ $ogImageUrl }}">
@endif

{{-- Twitter / X --}}
<meta name="twitter:card" content="{{ $ogImageUrl ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
@if ($ogImageUrl)
    <meta name="twitter:image" content="{{ $ogImageUrl }}">
@endif

<meta name="theme-color" content="#FDFDFC">
