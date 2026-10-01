@php
    $merchant = $merchantAccessStatus ?? null;
    $accountStatus = $merchant?->status;
    $verificationStatus = $merchant?->verification_status;
    $isSuspended = $accountStatus === 'suspended';
    $isPublic = $accountStatus === 'active' && $verificationStatus === 'approved';
@endphp

@if ($merchant && ! $isPublic)
    <div class="alert {{ $isSuspended ? 'alert-danger' : ($verificationStatus === 'rejected' ? 'alert-warning' : 'alert-warning') }} mx-3 mt-3 mb-0" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="ph-warning-circle ph-lg mt-1"></i>
            <div>
                <strong>
                    @if ($isSuspended)
                        Merchant account suspended
                    @elseif ($accountStatus === 'inactive')
                        Merchant account inactive
                    @else
                        Verification {{ ucfirst((string) $verificationStatus) }}
                    @endif
                </strong>
                <div class="mt-1">
                    @if ($isSuspended)
                        Merchant operations are restricted and your shop/products are not public. Please contact support or an administrator.
                    @elseif ($accountStatus === 'inactive')
                        Your shop/products are not public while the merchant account is inactive. You may continue preparing your business.
                    @elseif ($verificationStatus === 'rejected')
                        Your shop/products are not public. You may continue preparing your business and review the verification feedback below.
                    @else
                        Your shop/products are not public until verification is approved. You may continue preparing your business.
                    @endif
                </div>
                @if ($verificationStatus === 'rejected' && filled($merchant->rejection_reason))
                    <div class="mt-2"><strong>Reason:</strong> {{ $merchant->rejection_reason }}</div>
                @endif
            </div>
        </div>
    </div>
@endif
