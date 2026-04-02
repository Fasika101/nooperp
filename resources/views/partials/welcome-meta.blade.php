@php
    use App\Support\PublicLanding;

    $pageTitle = PublicLanding::metaTitle();
    $metaDescription = PublicLanding::metaDescription();
    $siteName = PublicLanding::brandName();
    $canonicalUrl = url('/');

    $og = PublicLanding::ogImageInfo();
    $ogImageUrl = $og['url'] ?? null;
    $ogImageWidth = $og['width'] ?? null;
    $ogImageHeight = $og['height'] ?? null;
    $ogImageType = $og['type'] ?? null;

    $faviconUrl = PublicLanding::faviconUrl();
    $faviconExt = $faviconUrl ? strtolower(pathinfo(parse_url($faviconUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) : '';
    $faviconType = match ($faviconExt) {
        'ico' => 'image/x-icon',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        default => null,
    };

    $themeColor = PublicLanding::themeColor();
@endphp

<title>{{ $pageTitle }}</title>
<meta name="title" content="{{ $pageTitle }}">
<meta name="description" content="{{ $metaDescription }}">
<meta name="robots" content="index, follow">
<link rel="canonical" href="{{ $canonicalUrl }}">
@if ($faviconUrl)
    <link rel="icon" href="{{ $faviconUrl }}"@if ($faviconType) type="{{ $faviconType }}"@endif>
@endif

{{-- Open Graph (WhatsApp, Telegram, Facebook, LinkedIn, etc.) --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
@if ($ogImageUrl)
    <meta property="og:image" content="{{ $ogImageUrl }}">
    <meta property="og:image:alt" content="{{ $siteName }}">
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

<meta name="theme-color" content="{{ $themeColor }}">
