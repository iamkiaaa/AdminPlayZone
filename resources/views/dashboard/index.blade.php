@extends('layouts.app')
@section('title', 'Dashboard')
@section('page_title', 'Dashboard')
@section('page_subtitle', 'Selamat Datang, ' . session('admin_name', 'Admin'))

@section('content')
  @if($occupancyPct >= 100)
    <div class="alert ad">🚨 <strong>Kapasitas Penuh!</strong> Tingkat keramaian sudah <span id="occupancyPctBanner">{{ $occupancyPct }}%</span></div>
  @elseif($occupancyPct >= 70)
    <div class="alert aw">⚠️ <strong>Kapasitas Hampir Penuh!</strong> Tingkat keramaian sudah <span id="occupancyPctBanner">{{ $occupancyPct }}%</span></div>
  @endif

  {{-- STAT CARDS --}}
  <div class="sg">
    <div class="sc s-or" style="border-top:3px solid #F5A34A;text-align:center;"><div class="si" style="margin:0 auto 11px;"><i class="fas fa-money-bill-wave"></i></div><div class="sl">Revenue Hari Ini</div><div class="sv">Rp {{ number_format($revenueToday, 0, ',', '.') }}</div></div>
    <div class="sc s-pk" style="border-top:3px solid #F4777A;text-align:center;"><div class="si" style="margin:0 auto 11px;"><i class="fas fa-child-reaching"></i></div><div class="sl">Transaksi Hari Ini</div><div class="sv" id="visitorTodayValue">{{ $visitorToday }}</div></div>
    <div class="sc s-tl" style="border-top:3px solid #6DC8C0;text-align:center;"><div class="si" style="margin:0 auto 11px;"><i class="fas fa-chart-line"></i></div><div class="sl">Tingkat Keramaian</div><div class="sv"><span id="occupancyPctValue">{{ $occupancyPct }}%</span></div></div>
    <div class="sc s-pu" style="border-top:3px solid #B07DD4;text-align:center;"><div class="si" style="margin:0 auto 11px;"><i class="fas fa-ticket"></i></div><div class="sl">Tiket Terjual Hari Ini</div><div class="sv">{{ $ticketSold }}</div></div>
  </div>

  {{-- GRAFIK + KAPASITAS --}}
  <div class="three-col">
    <div class="card">
      <div class="ch"><div><div class="ct">📈 Grafik Penjualan</div><div class="cs">Revenue 7 hari terakhir</div></div></div>
      <canvas id="salesChart" style="max-height:185px;"></canvas>
    </div>

    <div class="card" style="display:flex;flex-direction:column;">
      <div class="ch"><div><div class="ct">🎯 Kapasitas</div><div class="cs">Maks pengunjung perhari</div></div></div>
      <div style="flex:0.5;"></div>
      <div style="text-align:center;padding:18px 0 10px;">
        <div id="capDisplay" style="font-size:60px;font-weight:900;font-family:'Poppins',sans-serif;color:var(--or);line-height:1;">{{ $capacity }}</div>
        <div style="font-size:15px;color:var(--muted);font-weight:700;margin-top:6px;">Orang / Hari</div>
      </div>
      <div id="capViewMode"><button class="btn btn-ou" style="width:100%;justify-content:center;" onclick="enableEditCap()">Edit Kapasitas</button></div>
      <div id="capEditMode" style="display:none;">
        <div class="fg" style="margin-bottom:10px;"><label class="fl">Jumlah Kapasitas</label><input class="fi" type="number" id="capInput" value="{{ $capacity }}" min="1" style="font-size:16px;font-weight:800;text-align:center;" /></div>
        <div style="display:flex;gap:8px;"><button class="btn btn-ou" style="flex:1;justify-content:center;" onclick="saveCapVal()">Simpan</button><button class="btn btn-dg" style="flex:1;justify-content:center;" onclick="cancelEditCap()">Batal</button></div>
      </div>
    </div>
  </div>

  {{-- TRANSAKSI TERBARU --}}
  <div class="two-col">
    <div class="card">
      <div class="ch"><div class="ct">💳 Transaksi Terbaru</div><a href="{{ route('admin.transactions.index') }}" class="btn btn-ou btn-sm">Lihat Semua</a></div>
      @if($recentTransactions->isEmpty())
        <div style="padding:30px;text-align:center;color:var(--muted);">Tidak ada transaksi terbaru.</div>
      @else
        @foreach($recentTransactions as $tx)
          <div class="urow">
            <div class="uava" style="background:var(--pk-pale);color:var(--pk);">{{ strtoupper(substr($tx->user_name, 0, 1)) }}</div>
            <div class="udet"><div class="unm">{{ $tx->user_name }}</div><div class="uml">{{ $tx->package_name }}</div></div>
            <div class="ust">
              <div class="utl">Rp {{ number_format($tx->total, 0, ',', '.') }}</div>
              <span class="badge {{ $tx->status === 'refunded' ? 'refund' : $tx->status }}">{{ $tx->status === 'paid' ? 'Paid' : ($tx->status === 'refunded' ? 'Refunded' : ($tx->status === 'digunakan' ? 'Digunakan' : ucfirst($tx->status))) }}</span>
            </div>
          </div>
        @endforeach
      @endif
    </div>

    <div class="card">
      <div class="ch"><div style="display:flex;align-items:center;gap:9px;"><div style="font-size:20px;">📄</div><div class="ct">Export Laporan</div></div></div>
      <div class="fg"><label class="fl">Dari Tanggal</label><input class="fi" type="date" id="expFrom" value="{{ date('Y-m-d') }}" /></div>
      <div class="fg"><label class="fl">Sampai Tanggal</label><input class="fi" type="date" id="expTo" value="{{ date('Y-m-d') }}" /></div>
      <div style="display:flex;gap:8px;margin-bottom:13px;"><button class="btn btn-ou" style="flex:1;justify-content:center;border-radius:25px;" onclick="downloadPdf()">⬇ Unduh PDF</button><button class="btn btn-ou" style="flex:1;justify-content:center;border-radius:20px;" onclick="downloadExcel()">⬇ Unduh Excel</button></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:9px;">
        <div class="total-box" style="text-align:center;background:var(--or-pale);border-radius:11px;padding:14px 12px;"><div id="totalRevenueValue" style="font-size:18px;font-weight:900;font-family:'Poppins',sans-serif;color:var(--or);">{{ $totalRevenue >= 1000000 ? number_format($totalRevenue / 1000000, 1, ',', '.') . ' JT' : 'Rp ' . number_format($totalRevenue, 0, ',', '.') }}</div><div style="font-size:11px;color:var(--muted);font-weight:700;margin-top:4px;">Total Revenue</div></div>
        <div class="total-box" style="text-align:center;background:var(--tl-pale);border-radius:11px;padding:14px 12px;"><div id="totalVisitorsValue" style="font-size:18px;font-weight:900;font-family:'Poppins',sans-serif;color:var(--tl);">{{ number_format($totalVisitors, 0, ',', '.') }}</div><div style="font-size:11px;color:var(--muted);font-weight:700;margin-top:4px;">Total Pengunjung</div></div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    const chartLabels = @json(array_column($chartData, 'label')), chartValues = @json(array_column($chartData, 'value')); initChart(chartLabels, chartValues);
    function enableEditCap() { document.getElementById('capViewMode').style.display = 'none'; document.getElementById('capEditMode').style.display = 'block'; }
    function cancelEditCap() { const currentCap = document.getElementById('capDisplay').innerText.trim(); document.getElementById('capInput').value = currentCap; document.getElementById('capEditMode').style.display = 'none'; document.getElementById('capViewMode').style.display = 'block'; }

    async function saveCapVal() {
      const capInput = document.getElementById('capInput'), capDisplay = document.getElementById('capDisplay'), newCap = parseInt(capInput.value);
      if (!newCap || newCap < 1) { alert('Kapasitas minimal 1'); return; }
      try {
        const response = await fetch("{{ route('admin.settings.capacity') }}", { method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" }, body: JSON.stringify({ kapasitas_maksimal: newCap }) });
        const data = await response.json();
        if (!response.ok || !data.success) { alert('Gagal update kapasitas'); console.log(data); return; }
        capDisplay.innerText = newCap; capInput.value = newCap; updateOccupancyAfterCapacityChange(newCap);
        document.getElementById('capEditMode').style.display = 'none'; document.getElementById('capViewMode').style.display = 'block'; showFlash('success', 'Berhasil', 'Kapasitas berhasil diupdate');
      } catch (error) { alert('Terjadi error saat update kapasitas'); console.log(error); }
    }

    function updateOccupancyAfterCapacityChange(newCap) {
      const visitorToday = parseInt(document.getElementById('visitorTodayValue').innerText) || 0, newPct = newCap > 0 ? Math.min(100, Math.round((visitorToday / newCap) * 100)) : 0;
      const occupancyPctValue = document.getElementById('occupancyPctValue'), occupancyPctBanner = document.getElementById('occupancyPctBanner');
      if (occupancyPctValue) occupancyPctValue.innerText = newPct + '%';
      if (occupancyPctBanner) occupancyPctBanner.innerText = newPct + '%';
    }

    function downloadPdf() { const from = document.getElementById('expFrom').value, to = document.getElementById('expTo').value; window.location = `/admin/export/pdf?from=${from}&to=${to}`; }
    function downloadExcel() { const from = document.getElementById('expFrom').value, to = document.getElementById('expTo').value; window.location = `/admin/export/excel?from=${from}&to=${to}`; }
    function showFlash(type, title, text) { const content = document.querySelector('.content'), oldFlash = document.getElementById('flashMsg'); if (oldFlash) oldFlash.remove(); const flash = document.createElement('div'); flash.id = 'flashMsg'; flash.className = `flash ${type === 'success' ? 'flash-success' : 'flash-error'}`; flash.innerHTML = `<strong>${title}.</strong>&nbsp;${text}`; content.prepend(flash); setTimeout(() => flash.remove(), 3000); }

    document.getElementById('expFrom').addEventListener('change', loadSummary);
    document.getElementById('expTo').addEventListener('change', loadSummary);

    async function loadSummary() {
      const from = document.getElementById('expFrom').value, to = document.getElementById('expTo').value;
      if (!from || !to) return;
      try {
        const response = await fetch(`/admin/dashboard-summary?from=${from}&to=${to}`);
        const data = await response.json();
        document.getElementById('totalRevenueValue').innerText = data.revenue >= 1000000 ? (data.revenue / 1000000).toFixed(1).replace('.', ',') + ' JT' : 'Rp ' + Number(data.revenue).toLocaleString('id-ID');
        document.getElementById('totalVisitorsValue').innerText = Number(data.visitors).toLocaleString('id-ID');
      } catch (error) { console.error(error); }
    }
  </script>
@endpush