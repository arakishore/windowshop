<!doctype html>
<html lang="en">
<body style="margin:0;background:#f5f6f8;color:#20242a;font-family:Arial,sans-serif">
@php
    $presentation = $emailPresentation ?? [];
    $showFooter = (bool) ($presentation['show_footer'] ?? true);
    $benefits = $presentation['benefits'] ?? [];
    $socialLinks = $presentation['social_links'] ?? [];
    $appLinks = $presentation['app_links'] ?? [];
    $legalLinks = $presentation['legal_links'] ?? [];
    // Older seeded templates may contain this exact common footer line. Keep
    // stored template content unchanged while letting the shared footer own it.
    $displayBody = preg_replace('/(?:\r?\n){1,2}Powered by\s+'.preg_quote($marketplaceName, '/').'\s*$/iu', '', $notificationBody) ?? $notificationBody;
@endphp
<div style="max-width:640px;margin:0 auto;padding:24px 12px">
    <div style="background:#fff;border:1px solid #e6e8eb;border-radius:10px;padding:28px">
        @if($brandLogoUrl)
            <img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}" style="display:block;max-width:160px;max-height:64px;margin-bottom:20px">
            @if($presentation['show_brand_name'] ?? true)
                <div style="font-size:13px;font-weight:600;margin:-12px 0 20px">{{ $brandName }}</div>
            @endif
        @elseif($presentation['show_brand_name'] ?? true)
            <div style="font-size:20px;font-weight:700;margin-bottom:20px">{{ $brandName }}</div>
        @endif
        <h1 style="font-size:24px;line-height:1.3;margin:0 0 18px">{{ $notificationSubject }}</h1>
        <div style="font-size:15px;line-height:1.65">{!! nl2br(e($displayBody)) !!}</div>
    </div>
    @if($showFooter)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;font-size:12px;line-height:1.5;color:#68707b;text-align:center">
            @if($benefits)
                <tr>
                    <td align="center" style="padding:20px 12px 0;color:#343a40;font-size:12px;font-weight:600">
                        @foreach($benefits as $benefit)
                            @if(! $loop->first)<span style="padding:0 8px;color:#b4bac2">&bull;</span>@endif<span>{{ $benefit }}</span>
                        @endforeach
                    </td>
                </tr>
            @endif
            @if($socialLinks)
                <tr>
                    <td align="center" style="padding:14px 8px 0">
                        @foreach($socialLinks as $label => $url)
                            <a href="{{ $url }}" style="display:inline-block;margin:2px 3px;padding:5px 9px;border:1px solid #d8dce1;border-radius:12px;color:#4f5965;font-size:11px;font-weight:600;text-decoration:none">{{ $label }}</a>
                        @endforeach
                    </td>
                </tr>
            @endif
            @if($appLinks)
                <tr>
                    <td align="center" style="padding:14px 8px 0">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto">
                            <tr>
                                @foreach($appLinks as $label => $url)
                                    <td style="padding:0 4px">
                                        <a href="{{ $url }}" style="display:inline-block;min-width:104px;padding:7px 11px;border:1px solid #8d96a1;border-radius:5px;background:#ffffff;color:#2f353c;font-size:11px;font-weight:600;line-height:1.25;text-align:center;text-decoration:none">Get it on {{ $label }}</a>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                    </td>
                </tr>
            @endif
            @if($legalLinks)
                <tr>
                    <td align="center" style="padding:16px 8px 0;font-size:11px">
                        @foreach($legalLinks as $label => $url)
                            @if(! $loop->first)<span style="padding:0 6px;color:#b4bac2">|</span>@endif<a href="{{ $url }}" style="color:#626b76;text-decoration:underline">{{ $label }}</a>
                        @endforeach
                    </td>
                </tr>
            @endif
            <tr>
                <td align="center" style="padding:12px 8px 0;color:#737b85;font-size:11px">{{ $presentation['copyright'] ?? ('© '.now()->year.' '.$marketplaceName.'. All rights reserved.') }}</td>
            </tr>
            @if($presentation['show_powered_by'] ?? true)
                <tr>
                    <td align="center" style="padding:4px 8px 18px;color:#969da6;font-size:9px">{{ $presentation['powered_by_text'] ?? ('Powered by '.$marketplaceName) }}</td>
                </tr>
            @else
                <tr><td style="font-size:1px;line-height:1px;padding-bottom:18px">&nbsp;</td></tr>
            @endif
        </table>
    @endif
</div>
</body>
</html>
