<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $logoUrl = \App\Support\TenantBranding::logoUrl($sahodaya);

        $allApprovedReceipts = $schoolFee ? $schoolFee->receipts()->where('status', 'approved')->orderBy('id')->get() : collect();
        if ($allApprovedReceipts->isEmpty() && $receipt) {
            $allApprovedReceipts = collect([$receipt]);
        }

        $hasMultipleReceipts = $allApprovedReceipts->count() > 1;
        $totalDue = (float) ($schoolFee->total_due ?? $breakdown['total'] ?? $receipt->amount);
        $totalPaid = (float) ($schoolFee->amount_paid ?? $allApprovedReceipts->sum('amount') ?? $receipt->amount);
        $outstandingBalance = $schoolFee ? (float) $schoolFee->outstandingBalance() : max(0, $totalDue - $totalPaid);

        $isConsolidated = ($isConsolidated ?? false)
            || request()->boolean('consolidated')
            || ($hasMultipleReceipts && $schoolFee && $schoolFee->isFullyPaid() && !request()->has('receipt_id') && request()->routeIs('school.kalotsav.event.receipt'));

        if ($isConsolidated) {
            $displayAmount = $totalPaid;
            $receiptNumber = $allApprovedReceipts->pluck('receipt_number')->filter()->unique()->implode(', ');
            if (empty($receiptNumber)) {
                $receiptNumber = $receipt->receipt_number ?? '—';
            }
            $paymentDate = $allApprovedReceipts->last()?->payment_date ?? $receipt->payment_date;
            $receiptTypeLabel = 'CONSOLIDATED EVENT FEE RECEIPT';
        } else {
            $displayAmount = (float) $receipt->amount;
            $receiptNumber = $receipt->receipt_number ?? '—';
            $paymentDate = $receipt->payment_date;
            $receiptTypeLabel = $hasMultipleReceipts ? 'PAYMENT RECEIPT (INSTALLMENT)' : 'FEE RECEIPT';
        }

        $amountWords = \App\Support\IndianAmountInWords::rupees($displayAmount);

        // Group breakdown extra items and charges cleanly
        $groupedBreakdown = [];
        foreach (($breakdown['items'] ?? []) as $line) {
            $label = $line['label'];
            $lineType = $line['line_type'] ?? 'item';
            $key = $lineType . ':' . $label;
            if (!isset($groupedBreakdown[$key])) {
                $groupedBreakdown[$key] = [
                    'label' => $label,
                    'line_type' => $lineType,
                    'quantity' => 0,
                    'amount' => 0.0,
                    'unit_amount' => (float) ($line['amount'] ?? 0),
                ];
            }
            $qty = (int) ($line['quantity'] ?? 1);
            $groupedBreakdown[$key]['quantity'] += $qty;
            $groupedBreakdown[$key]['amount'] += (float) ($line['amount'] ?? 0);
        }
    @endphp
    <title>{{ strtoupper($event->title) }} Fee Receipt {{ $receiptNumber }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a;
            background: #f8fafc;
            padding: 24px 16px;
            font-size: 13px;
            line-height: 1.5;
        }

        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .receipt-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 16px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .toolbar-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: #475569;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: #1e3a8a;
            color: #ffffff;
        }
        .btn-primary:hover {
            background: #172554;
        }

        .btn-outline {
            background: #ffffff;
            border-color: #cbd5e1;
            color: #334155;
        }
        .btn-outline:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .receipt {
            background: #ffffff;
            border: 1.5px solid #1e3a8a;
            border-left: 10px solid #1e3a8a;
            padding: 28px 32px 32px;
            position: relative;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }

        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 16px;
            gap: 16px;
        }

        .org-branding {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .org-branding img {
            height: 56px;
            width: auto;
            max-width: 90px;
            object-fit: contain;
        }

        .org-info h1 {
            font-size: 18px;
            font-weight: 700;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }

        .org-info p {
            font-size: 11px;
            color: #64748b;
            margin-top: 3px;
        }

        .receipt-badge-box {
            text-align: right;
            min-width: 160px;
        }

        .receipt-badge-box .badge-label {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .receipt-badge-box .badge-num {
            font-size: 18px;
            font-weight: 800;
            color: #1e3a8a;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            margin-top: 2px;
            word-break: break-all;
        }

        .receipt-badge-box .badge-date {
            font-size: 11px;
            color: #475569;
            margin-top: 3px;
        }

        .receipt-title {
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #1e3a8a;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            margin-bottom: 18px;
            background: #f8fafc;
            border-radius: 4px;
        }

        table.info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            margin-bottom: 18px;
        }

        table.info-table th, table.info-table td {
            padding: 7px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        table.info-table td.label-col {
            width: 32%;
            color: #64748b;
            font-size: 11.5px;
            font-weight: 500;
        }

        table.info-table tr.section-title-row td {
            background: #f1f5f9;
            color: #1e3a8a;
            font-weight: 700;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 10px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }

        table.info-table tr.amount-highlight-row td {
            padding: 10px 10px;
            font-size: 15px;
            font-weight: 700;
            background: #f0fdf4;
            border-top: 1.5px solid #86efac;
            border-bottom: 1.5px solid #86efac;
        }

        table.info-table tr.amount-highlight-row td.amount-val {
            color: #15803d;
            font-size: 17px;
            font-weight: 800;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            background: #dcfce7;
            color: #166534;
        }

        .badge-info {
            background: #e0f2fe;
            color: #0369a1;
        }

        .items-compact-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 6px;
            line-height: 1.3;
        }

        .item-tag {
            display: inline-block;
            font-size: 11px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 2px 7px;
            border-radius: 4px;
            color: #334155;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .qty-badge {
            display: inline-block;
            font-size: 10.5px;
            font-weight: 600;
            color: #64748b;
            margin-left: 4px;
        }

        /* Installments Breakdown Table */
        .installments-section {
            margin-top: 18px;
            margin-bottom: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .installments-header {
            background: #f8fafc;
            padding: 8px 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #334155;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        table.installments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        table.installments-table th {
            background: #ffffff;
            color: #64748b;
            font-size: 10.5px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        table.installments-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        table.installments-table tr.active-receipt-row {
            background: #f0fdf4;
            font-weight: 600;
        }

        .current-badge {
            display: inline-block;
            font-size: 9px;
            background: #16a34a;
            color: #ffffff;
            padding: 1px 5px;
            border-radius: 4px;
            text-transform: uppercase;
            margin-left: 4px;
            vertical-align: middle;
        }

        .footer {
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            page-break-inside: avoid;
            break-inside: avoid;
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
        }

        .sign-line {
            border-top: 1px solid #334155;
            width: 180px;
            margin: 0 0 6px;
        }

        .sign-label {
            font-size: 11px;
            color: #475569;
        }

        .sign-org {
            font-size: 11px;
            font-weight: 700;
            color: #1e3a8a;
        }

        /* Clean Print Stylesheet */
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                font-size: 11.5px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .receipt-container {
                max-width: 100% !important;
                margin: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .receipt {
                border: 1.5px solid #1e3a8a !important;
                border-left: 8px solid #1e3a8a !important;
                padding: 18px 20px !important;
                box-shadow: none !important;
            }

            table.info-table {
                font-size: 11.5px !important;
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            td, th {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                padding: 5px 8px !important;
            }

            .installments-section {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .footer {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                margin-top: 16px !important;
                padding-top: 12px !important;
            }

            .watermark {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Interactive Toolbar (Hidden on Print) -->
        <div class="receipt-toolbar no-print">
            <div class="toolbar-info">
                @if($isConsolidated)
                    <span>Viewing <strong>Consolidated Receipt</strong> (Total: ₹{{ number_format($totalPaid, 2) }})</span>
                @elseif($hasMultipleReceipts)
                    <span>Viewing <strong>Installment #{{ $receipt->receipt_number }}</strong> (₹{{ number_format((float) $receipt->amount, 2) }} of ₹{{ number_format($totalPaid, 2) }})</span>
                @else
                    <span>Official Fee Receipt <strong>#{{ $receiptNumber }}</strong></span>
                @endif
            </div>
            <div class="toolbar-actions">
                @if($hasMultipleReceipts)
                    @if($isConsolidated)
                        <a href="{{ request()->fullUrlWithQuery(['consolidated' => 0, 'receipt_id' => $receipt->id]) }}" class="btn btn-outline">
                            View Installment #{{ $receipt->receipt_number }}
                        </a>
                    @else
                        <a href="{{ request()->fullUrlWithQuery(['consolidated' => 1]) }}" class="btn btn-outline">
                            View Consolidated Receipt (₹{{ number_format($totalPaid, 2) }})
                        </a>
                    @endif
                @endif
                <button onclick="window.print()" class="btn btn-primary">
                    🖨️ Print Receipt
                </button>
            </div>
        </div>

        <div class="receipt">
            <!-- Header Section -->
            <div class="header">
                <div class="org-branding">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $sahodaya->name }}">
                    @endif
                    <div class="org-info">
                        <h1>{{ $sahodaya->name }}</h1>
                        <p>Sahodaya Schools Complex · Official Fee Receipt</p>
                        <p>Date: {{ $paymentDate?->format('d M Y') ?? now()->format('d M Y') }}</p>
                    </div>
                </div>
                <div class="receipt-badge-box">
                    <p class="badge-label">Receipt No.</p>
                    <p class="badge-num">{{ $receiptNumber }}</p>
                    <p class="badge-date">{{ $paymentDate?->format('d M Y') ?? '—' }}</p>
                </div>
            </div>

            <!-- Receipt Subtitle -->
            <div class="receipt-title">{{ strtoupper($event->title) }} — {{ $receiptTypeLabel }}</div>

            <!-- General Details Table -->
            <table class="info-table">
                <tr>
                    <td class="label-col">Received from</td>
                    <td><strong>{{ $school->name }}</strong></td>
                </tr>
                <tr>
                    <td class="label-col">Event Title</td>
                    <td><strong>{{ $event->title }}</strong></td>
                </tr>
                @if(! $isConsolidated)
                    @if($receipt->transaction_ref)
                    <tr>
                        <td class="label-col">Transaction Ref.</td>
                        <td><code>{{ $receipt->transaction_ref }}</code></td>
                    </tr>
                    @endif
                    @if($receipt->bank_name)
                    <tr>
                        <td class="label-col">Bank / Payment Method</td>
                        <td>{{ $receipt->bank_name }}</td>
                    </tr>
                    @endif
                @endif
                <tr>
                    <td class="label-col">Payment Date</td>
                    <td>{{ $paymentDate?->format('d M Y') ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label-col">Payment Status</td>
                    <td>
                        <span class="status-badge">
                            {{ $outstandingBalance <= 0 ? 'Approved (Fully Paid)' : 'Approved (Partial Payment)' }}
                        </span>
                        @if($isConsolidated)
                            <span class="status-badge badge-info" style="margin-left: 4px;">Consolidated</span>
                        @endif
                    </td>
                </tr>

                <!-- Event Fee Itemization Breakdown -->
                @if($groupedBreakdown !== [])
                    <tr class="section-title-row">
                        <td colspan="2">Event Fee Itemization</td>
                    </tr>
                    @foreach($groupedBreakdown as $line)
                    <tr>
                        <td>
                            {{ $line['label'] }}
                            @if($line['quantity'] > 1 && !str_contains($line['label'], '(' . $line['quantity']))
                                <span class="qty-badge">({{ $line['quantity'] }} entries)</span>
                            @endif
                        </td>
                        <td style="text-align: right; font-weight: 500;">₹ {{ number_format($line['amount'], 2) }}</td>
                    </tr>
                    @endforeach
                    <tr style="background: #f8fafc; font-weight: 600;">
                        <td>Total Event Fee Due</td>
                        <td style="text-align: right; color: #1e3a8a;">₹ {{ number_format($totalDue, 2) }}</td>
                    </tr>
                @endif

                <!-- Registered Items Compact Summary -->
                @if($registrations->isNotEmpty())
                    <tr>
                        <td class="label-col" style="vertical-align: top; padding-top: 10px;">
                            Registered Items ({{ $registrations->count() }})
                        </td>
                        <td style="padding-top: 10px;">
                            <div class="items-compact-grid">
                                @foreach($registrations as $reg)
                                    <span class="item-tag">{{ $reg->item->title ?? 'Item' }}</span>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endif

                <!-- Financial Accounting & Amount Breakdown -->
                <tr class="section-title-row">
                    <td colspan="2">Financial Accounting</td>
                </tr>

                @if(! $isConsolidated && $hasMultipleReceipts)
                    <!-- Installment-specific Financial View -->
                    <tr>
                        <td class="label-col">Amount for this Receipt (#{{ $receipt->receipt_number }})</td>
                        <td style="text-align: right; font-weight: 700; color: #15803d; font-size: 14px;">
                            ₹ {{ number_format($displayAmount, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td class="label-col">Amount in Words (This Receipt)</td>
                        <td style="text-align: right;"><strong>{{ $amountWords }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label-col">Total Event Fee Due</td>
                        <td style="text-align: right; font-weight: 600;">₹ {{ number_format($totalDue, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Total Amount Paid to Date</td>
                        <td style="text-align: right; font-weight: 700; color: #1e3a8a;">₹ {{ number_format($totalPaid, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Balance Remaining</td>
                        <td style="text-align: right; font-weight: 700; color: {{ $outstandingBalance > 0 ? '#b91c1c' : '#15803d' }};">
                            {{ $outstandingBalance > 0 ? '₹ ' . number_format($outstandingBalance, 2) : 'NIL (Fully Settled)' }}
                        </td>
                    </tr>
                @else
                    <!-- Consolidated or Single Payment Financial View -->
                    <tr>
                        <td class="label-col">Amount in Words</td>
                        <td style="text-align: right;"><strong>{{ $amountWords }}</strong></td>
                    </tr>
                    <tr class="amount-highlight-row">
                        <td>Total Amount Paid</td>
                        <td class="amount-val" style="text-align: right;">₹ {{ number_format($displayAmount, 2) }}</td>
                    </tr>
                    @if($outstandingBalance > 0)
                    <tr>
                        <td class="label-col">Balance Due</td>
                        <td style="text-align: right; font-weight: 700; color: #b91c1c;">₹ {{ number_format($outstandingBalance, 2) }}</td>
                    </tr>
                    @else
                    <tr>
                        <td class="label-col">Balance Remaining</td>
                        <td style="text-align: right; font-weight: 700; color: #15803d;">NIL (Fully Settled)</td>
                    </tr>
                    @endif
                @endif
            </table>

            <!-- Installment Proofs Table (Rendered whenever multiple installments exist) -->
            @if($hasMultipleReceipts)
            <div class="installments-section">
                <div class="installments-header">
                    <span>Approved Payment Proofs &amp; Installments ({{ $allApprovedReceipts->count() }})</span>
                    <span>Total Settled: ₹{{ number_format($totalPaid, 2) }}</span>
                </div>
                <table class="installments-table">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Payment Date</th>
                            <th>Reference / Bank</th>
                            <th style="text-align: right;">Amount</th>
                            <th style="text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($allApprovedReceipts as $idx => $appr)
                        <tr class="{{ (! $isConsolidated && $appr->id === $receipt->id) ? 'active-receipt-row' : '' }}">
                            <td>
                                <strong>#{{ $appr->receipt_number }}</strong>
                                @if(! $isConsolidated && $appr->id === $receipt->id)
                                    <span class="current-badge">This Receipt</span>
                                @endif
                            </td>
                            <td>{{ $appr->payment_date?->format('d M Y') ?? '—' }}</td>
                            <td>
                                {{ $appr->transaction_ref ?? '—' }}
                                @if($appr->bank_name)
                                    <span style="color: #64748b;">({{ $appr->bank_name }})</span>
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 600;">₹ {{ number_format((float) $appr->amount, 2) }}</td>
                            <td style="text-align: center;"><span class="status-badge">Approved</span></td>
                        </tr>
                        @endforeach
                        <tr style="background: #f8fafc; font-weight: 700; border-top: 1.5px solid #cbd5e1;">
                            <td colspan="3">Total Payments Received</td>
                            <td style="text-align: right; color: #15803d;">₹ {{ number_format($totalPaid, 2) }}</td>
                            <td style="text-align: center; color: #15803d; font-size: 11px;">
                                {{ $outstandingBalance <= 0 ? 'Fully Paid' : 'Partial' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @endif

            <!-- Footer Signatory Section -->
            <div class="footer">
                <div>
                    <div class="sign-line"></div>
                    <p class="sign-label">Authorised Signatory</p>
                    <p class="sign-org">{{ $sahodaya->name }}</p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 10px; color: #94a3b8;">This is a computer-generated receipt.</p>
                    <p style="font-size: 9.5px; color: #cbd5e1; margin-top: 2px;">Generated on {{ now()->format('d M Y, h:i A') }}</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
