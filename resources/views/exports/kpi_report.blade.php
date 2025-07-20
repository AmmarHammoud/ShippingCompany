<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Performance KPI Report</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #000;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .meta {
            margin-bottom: 30px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .meta-table td {
            padding: 6px 10px;
            border: 1px solid #ccc;
        }

        .meta-table td.label {
            background-color: #f0f0f0;
            font-weight: bold;
            width: 25%;
        }

        table.kpi-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.kpi-table th,
        table.kpi-table td {
            border: 1px solid #333;
            padding: 10px;
            text-align: center;
        }

        table.kpi-table th {
            background-color: #e8e8e8;
        }

        .section-title {
            margin-top: 35px;
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 15px;
        }
    </style>
</head>
<body>

<h2>Performance KPI Report</h2>

{{-- معلومات التقرير --}}
<table class="meta-table">
    <tr>
        <td class="label">Center Name</td>
        <td>{{ $centerName ?? 'All Centers' }}</td>
        <td class="label">Manager Name</td>
        <td>{{ $managerName ?? 'N/A' }}</td>
    </tr>
    <tr>
        <td class="label">Report Period</td>
        <td>{{ $startDate ?? 'N/A' }} to {{ $endDate ?? 'N/A' }}</td>
        <td class="label">Generated At</td>
        <td>{{ $generatedAt ?? now()->format('Y-m-d H:i') }}</td>
    </tr>
</table>

{{-- ملخص الأداء --}}
<div class="section-title">Summary Metrics</div>
<table class="kpi-table">
    <thead>
        <tr>
            <th>Total Shipments</th>
            <th>Delivered Shipments</th>
            <th>Cancelled Shipments</th>
            <th>Average Delivery Time (minutes)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $data['summary']['total_shipments'] ?? 0 }}</td>
            <td>{{ $data['summary']['delivered_shipments'] ?? 0 }}</td>
            <td>{{ $data['summary']['cancelled_shipments'] ?? 0 }}</td>
            <td>{{ $data['summary']['avg_delivery_time'] ?? 0 }}</td>
        </tr>
    </tbody>
</table>

{{-- ترند الشحنات حسب التاريخ --}}
@if(!empty($data['trend']))
    <div class="section-title">Shipment Trend (by Day)</div>
    <table class="kpi-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Number of Shipments</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['trend'] as $trend)
                <tr>
                    <td>{{ $trend['date'] }}</td>
                    <td>{{ $trend['count'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

</body>
</html>
