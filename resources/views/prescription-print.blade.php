<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription - {{ $prescription->customer->name }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #fff;
        }
        .prescription-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #eee;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0f766e;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #0f766e;
            font-size: 28px;
            text-transform: uppercase;
        }
        .business-info {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .prescription-title {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 30px;
            text-decoration: underline;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-item {
            font-size: 15px;
        }
        .info-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            width: 100px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }
        th {
            background-color: #f9fafb;
            font-weight: bold;
            color: #374151;
        }
        .eye-label {
            text-align: left;
            font-weight: bold;
            background-color: #f3f4f6;
        }
        .pd-section {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f9fafb;
            border-radius: 4px;
        }
        .notes-section {
            margin-bottom: 40px;
        }
        .notes-title {
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .footer {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .signature-box {
            border-top: 1px solid #333;
            width: 200px;
            text-align: center;
            padding-top: 5px;
            font-size: 14px;
        }
        .date-box {
            font-size: 14px;
        }
        @media print {
            body { padding: 0; background: none; }
            .prescription-container { border: none; box-shadow: none; width: 100%; max-width: none; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #0f766e; color: white; border: none; border-radius: 4px; cursor: pointer;">Print Now</button>
    </div>

    <div class="prescription-container">
        <div class="header">
            <h1>{{ $businessName }}</h1>
            <div class="business-info">
                @if($businessAddress) {{ $businessAddress }}<br> @endif
                @if($businessPhone) Phone: {{ $businessPhone }} @endif
                @if($businessEmail) | Email: {{ $businessEmail }} @endif
            </div>
        </div>

        <div class="prescription-title">OPHTHALMIC PRESCRIPTION</div>

        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Patient:</span> <strong>{{ $prescription->customer->name }}</strong>
            </div>
            <div class="info-item" style="text-align: right;">
                <span class="info-label">Date:</span> <strong>{{ $prescription->created_at->format('M d, Y') }}</strong>
            </div>
            <div class="info-item">
                <span class="info-label">Vision:</span> <strong>{{ ucfirst($prescription->vision ?? 'Single Vision') }}</strong>
            </div>
            @if($prescription->orderItem?->order_id)
            <div class="info-item" style="text-align: right;">
                <span class="info-label">Order #:</span> <strong>{{ $prescription->orderItem->order_id }}</strong>
            </div>
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th>Eye</th>
                    <th>Sphere (SPH)</th>
                    <th>Cylinder (CYL)</th>
                    <th>Axis</th>
                    @if($prescription->vision === 'progressive')
                    <th>Add</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="eye-label">Right (OD)</td>
                    <td>{{ $prescription->right_eye_sphere ?? '0.00' }}</td>
                    <td>{{ $prescription->right_eye_cylinder ?? '0.00' }}</td>
                    <td>{{ $prescription->right_eye_axis ?? '—' }}</td>
                    @if($prescription->vision === 'progressive')
                    <td>{{ $prescription->right_eye_add ?? '—' }}</td>
                    @endif
                </tr>
                <tr>
                    <td class="eye-label">Left (OS)</td>
                    <td>{{ $prescription->left_eye_sphere ?? '0.00' }}</td>
                    <td>{{ $prescription->left_eye_cylinder ?? '0.00' }}</td>
                    <td>{{ $prescription->left_eye_axis ?? '—' }}</td>
                    @if($prescription->vision === 'progressive')
                    <td>{{ $prescription->left_eye_add ?? '—' }}</td>
                    @endif
                </tr>
            </tbody>
        </table>

        <div class="pd-section">
            <strong>Pupillary Distance (PD):</strong>
            @if($prescription->pd_mode === 'two')
                OD: {{ $prescription->pd_right ?? '—' }} mm / OS: {{ $prescription->pd_left ?? '—' }} mm
            @else
                {{ $prescription->pd_single ?? '—' }} mm
            @endif
        </div>

        @if($prescription->notes)
        <div class="notes-section">
            <div class="notes-title">Notes / Recommendations</div>
            <div style="font-size: 14px; white-space: pre-line;">{{ $prescription->notes }}</div>
        </div>
        @endif

        <div class="footer">
            <div class="date-box">
                Printed on: {{ now()->format('M d, Y H:i') }}
            </div>
            <div class="signature-box">
                Optometrist Signature
            </div>
        </div>
    </div>
</body>
</html>
