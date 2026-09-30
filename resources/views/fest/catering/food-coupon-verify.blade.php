<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Food Coupon Verification</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f1f5f9; color: #1e293b; padding: 16px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { width: 100%; max-width: 440px; background: #ffffff; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05); overflow: hidden; border: 1px solid #e2e8f0; }
        .header { background: #0f172a; color: #ffffff; padding: 20px; text-align: center; }
        .header h1 { font-size: 16px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 4px; }
        .header p { font-size: 12px; color: #94a3b8; }
        .status-box { padding: 18px 20px; text-align: center; border-bottom: 1px solid #f1f5f9; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 9999px; font-size: 14px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; }
        .badge--valid { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .badge--redeemed { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .badge--invalid { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
        .body { padding: 24px 20px; }
        .code-display { text-align: center; margin-bottom: 20px; padding: 12px; background: #f8fafc; border-radius: 12px; border: 1px dashed #cbd5e1; }
        .code-title { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 4px; }
        .coupon-code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 28px; font-weight: 800; color: #0f172a; letter-spacing: 1px; }
        .token-code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; color: #64748b; margin-top: 2px; }
        .details-list { display: grid; grid-template-columns: 100px 1fr; gap: 12px; font-size: 13px; line-height: 1.5; }
        .detail-label { color: #64748b; font-weight: 500; }
        .detail-value { color: #0f172a; font-weight: 600; }
        .meal-tag { display: inline-block; padding: 3px 10px; border-radius: 6px; font-weight: 700; text-transform: uppercase; font-size: 11px; }
        .meal--breakfast { background: #fef3c7; color: #b45309; }
        .meal--lunch { background: #ecfdf5; color: #047857; }
        .meal--dinner { background: #e0e7ff; color: #4338ca; }
        .meal--other { background: #f1f5f9; color: #475569; }
        .actions { padding: 16px 20px 24px; text-align: center; }
        .btn-redeem { display: block; width: 100%; padding: 14px; background: #16a34a; color: #ffffff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; transition: background 0.15s ease; }
        .btn-redeem:hover { background: #15803d; }
        .alert { padding: 12px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 16px; text-align: center; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>{{ $sahodaya->name ?? 'Sahodaya Event' }}</h1>
            <p>{{ $event->title ?? 'Food Catering' }}</p>
        </div>

        <div class="status-box">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            @if(! $valid)
                <span class="badge badge--invalid">⚠ {{ $message ?? 'Invalid Coupon' }}</span>
            @elseif($coupon->status === 'issued')
                <span class="badge badge--valid">✓ Valid Coupon (Issued)</span>
            @elseif($coupon->status === 'redeemed')
                <span class="badge badge--redeemed">✗ Already Redeemed</span>
            @else
                <span class="badge badge--invalid">{{ ucfirst($coupon->status) }}</span>
            @endif
        </div>

        @if($valid && $coupon)
        <div class="body">
            <div class="code-display">
                <div class="code-title">Coupon Code</div>
                <div class="coupon-code">{{ $coupon->coupon_code }}</div>
                <div class="token-code">QR Decoded: <strong>{{ $coupon->qr_token }}</strong></div>
            </div>

            <div class="details-list">
                <div class="detail-label">Meal Type</div>
                <div class="detail-value">
                    <span class="meal-tag meal--{{ $coupon->meal_type }}">{{ ucfirst($coupon->meal_type) }}</span>
                </div>

                <div class="detail-label">Valid Date</div>
                <div class="detail-value">{{ $coupon->valid_date?->format('d M Y (D)') }}</div>

                <div class="detail-label">School / Guest</div>
                <div class="detail-value">{{ $schoolName }}</div>

                <div class="detail-label">Count</div>
                <div class="detail-value">{{ $coupon->head_count }} Person</div>

                @if($coupon->is_extra)
                <div class="detail-label">Type</div>
                <div class="detail-value" style="color: #b45309;">Extra / Buffer Coupon</div>
                @endif

                @if($coupon->redeemed_at)
                <div class="detail-label">Redeemed At</div>
                <div class="detail-value" style="color: #b91c1c;">{{ $coupon->redeemed_at->format('d M Y, h:i A') }}</div>
                @endif
            </div>
        </div>

        @if($canRedeem)
        <div class="actions">
            <form method="POST" action="{{ route('food-coupons.verify.redeem', ['token' => $coupon->qr_token]) }}">
                @csrf
                <button type="submit" class="btn-redeem" onclick="return confirm('Confirm redemption of this coupon?')">
                    Redeem Meal Coupon
                </button>
            </form>
        </div>
        @endif
        @else
        <div class="body" style="text-align: center; color: #64748b; font-size: 14px;">
            <p>The scanned QR code is either invalid, not yet issued, or does not belong to any active festival catering database.</p>
            <p style="margin-top: 10px; font-family: monospace; font-size: 12px;">Token: {{ $token }}</p>
        </div>
        @endif
    </div>
</body>
</html>
