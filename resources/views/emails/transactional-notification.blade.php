<!doctype html>
<html lang="en">
<body style="margin:0;background:#f5f6f8;color:#20242a;font-family:Arial,sans-serif">
<div style="max-width:640px;margin:0 auto;padding:24px 12px">
    <div style="background:#fff;border:1px solid #e6e8eb;border-radius:10px;padding:28px">
        @if($brandLogoUrl)
            <img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}" style="display:block;max-width:160px;max-height:64px;margin-bottom:20px">
        @else
            <div style="font-size:20px;font-weight:700;margin-bottom:20px">{{ $brandName }}</div>
        @endif
        <h1 style="font-size:24px;line-height:1.3;margin:0 0 18px">{{ $notificationSubject }}</h1>
        <div style="font-size:15px;line-height:1.65">{!! nl2br(e($notificationBody)) !!}</div>
    </div>
    <div style="font-size:10px;color:#777;text-align:center;padding:14px">Powered by {{ $marketplaceName }}</div>
</div>
</body>
</html>
