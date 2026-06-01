<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .title { text-align: center; margin-bottom: 10px; }
        .subtitle { text-align: center; margin-bottom: 20px; }
        .summary { margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f2f2f2; border: 1px solid #ccc; padding: 6px; text-align: center; }
        td { border: 1px solid #ccc; padding: 6px; }
        .total { text-align: right; }
    </style>
</head>
<body>
    <div class="title">
        <h2>PLAYZONE</h2>
        <h3>Laporan Transaksi</h3>
    </div>

    <div class="subtitle">Periode : {{ $from }} s/d {{ $to }}</div>

    @php
        $totalRevenue = $transactions->sum('total_harga');
        $totalData = $transactions->count();
    @endphp

    <div class="summary">
        <strong>Total Revenue :</strong> Rp {{ number_format($totalRevenue, 0, ',', '.') }}<br>
        <strong>Total Transaksi :</strong> {{ $totalData }}
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Nama</th>
                <th>Paket</th>
                <th>Total</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $i => $tx)
                <tr>
                    <td align="center">{{ $i + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($tx['created_at'])->format('d-m-Y') }}</td>
                    <td>{{ $tx['nama_customer'] }}</td>
                    <td>{{ $tx['details'][0]['nama_paket'] ?? '-' }}</td>
                    <td class="total">Rp {{ number_format($tx['total_harga'], 0, ',', '.') }}</td>
                    <td align="center">{{ ucfirst($tx['status_pembayaran']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>