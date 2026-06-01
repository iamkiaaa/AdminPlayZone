@extends('layouts.app')
@section('title', 'Transaksi')
@section('page_title', 'Transaksi')
@section('page_subtitle', 'Cek seluruh riwayat transaksi disini')

@section('content')
  <div class="toolbar">
    <form method="GET" action="{{ route('admin.transactions.index') }}" style="display:contents;">
      <div class="srch"><i class="fas fa-search srch-icon"></i><input name="search" placeholder="Cari transaksi, nama, id"
          value="{{ request('search') }}" oninput="autoSearch(this)" /></div>
      <input type="date" name="date" class="fsel" value="{{ request('date') }}" onchange="this.form.submit()" />
      <select name="status" class="fsel" onchange="this.form.submit()">
        <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Semua Status</option>
        <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Aktif</option>
        <option value="refunded" {{ request('status') === 'refunded' ? 'selected' : '' }}>Refunded</option>
        <option value="digunakan" {{ request('status') === 'digunakan' ? 'selected' : '' }}>Digunakan</option>
      </select>
      <select name="package" class="fsel" onchange="this.form.submit()">
        <option value="all">Semua Paket</option>
        @foreach($packages as $pkg)
          <option value="{{ $pkg['nama_package'] }}" {{ request('package') == $pkg['nama_package'] ? 'selected' : '' }}>
            {{ $pkg['nama_package'] }}
          </option>
        @endforeach
      </select>
    </form>
  </div>

  <div class="card">
    <div class="ch">
      <div>
        <div class="ct">💳 Semua Transaksi</div>
        <div class="cs">{{ $total }} Transaksi ditemukan</div>
      </div>
    </div>
    <div class="tw">
      <table>
        <thead>
          <tr>
            <th style="width:140px;">Kode QR</th>
            <th>Pengguna</th>
            <th>Paket</th>
            <th>Total</th>
            <th>Status</th>
            <th>Tanggal Pesan</th>
            <th>Detail</th>
          </tr>
        </thead>
        <tbody>
          @forelse($transactions as $tx)
            @php $displayStatus = strtolower($tx->status_ticket ?? $tx->status ?? 'paid'); @endphp
            <tr>
              <td><code
                  style="font-size:10px;background:#FAF7FE;padding:2px 6px;border-radius:5px;">{{ $tx->transaction_id }}</code>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:7px;">
                  <div class="uava"
                    style="background:var(--pk-pale);color:var(--pk);width:28px;height:28px;font-size:10px;">
                    {{ strtoupper(substr($tx->user_name, 0, 1)) }}
                  </div>
                  <div>
                    <div style="font-weight:800;font-size:11.5px;">{{ $tx->user_name }}</div>
                    <div style="font-size:10px;color:var(--muted);">{{ $tx->user_email }}</div>
                  </div>
                </div>
              </td>
              <td style="font-weight:700;font-size:11.5px;">{{ $tx->package_name }}</td>
              <td style="font-weight:900;color:var(--or);font-family:'Poppins',sans-serif;font-size:11.5px;">
                Rp.{{ number_format($tx->total, 0, ',', '.') }}</td>
              <td><span
                  class="badge {{ $displayStatus === 'refunded' ? 'refund' : $displayStatus }}">{{ $displayStatus === 'paid' ? 'Aktif' : ($displayStatus === 'refunded' ? 'Refunded' : ucfirst($displayStatus)) }}</span>
              </td>
              <td style="font-size:11px;">{{ \Carbon\Carbon::parse($tx->date)->isoFormat('D MMM YYYY') }}</td>
              <td><button class="adbtn tx-detail-btn" data-id="{{ $tx->id }}"
                  data-av="{{ strtoupper(substr($tx->user_name, 0, 1)) }}" data-nm="{{ $tx->user_name }}"
                  data-em="{{ $tx->user_email }}" data-pk="{{ $tx->package_name }}"
                  data-tgl="{{ \Carbon\Carbon::parse($tx->date)->isoFormat('D MMM YYYY') }}"
                  data-tot="Rp.{{ number_format($tx->total, 0, ',', '.') }}" data-status="{{ $displayStatus }}"
                  data-tanggal="{{ \Carbon\Carbon::parse($tx->tanggal_reservasi)->isoFormat('D MMM YYYY') }}"
                  data-jam="{{ $tx->jam }}" data-metode="{{ ucwords(str_replace('_', ' ', $tx->metode)) }}"
                  data-telepon="{{ $tx->telepon }}" data-kodeqr="{{ $tx->transaction_id }}">👁</button></td>
            </tr>
          @empty
            <tr>
              <td colspan="7" style="text-align:center;color:var(--muted);padding:30px;">Tidak ada transaksi ditemukan.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div
      style="display:flex;align-items:center;justify-content:space-between;margin-top:13px;padding-top:13px;border-top:1px solid var(--border);">
      <div style="font-size:11.5px;color:var(--muted);font-weight:600;">Menampilkan
        {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} dari {{ $total }} transaksi
      </div>
      {{-- PAGINATION --}}
      @if($transactions->lastPage() > 1)
        <div class="pg-wrap">
          @if($transactions->onFirstPage()) <span class="pg-btn pg-disabled">&lt;</span> @else <a class="pg-btn"
          href="{{ $transactions->previousPageUrl() }}&{{ http_build_query(request()->except('page')) }}">&lt;</a> @endif
          <a class="pg-btn {{ $transactions->currentPage() == 1 ? 'pg-active' : '' }}"
            href="{{ $transactions->url(1) }}&{{ http_build_query(request()->except('page')) }}">1</a>
          @if($transactions->currentPage() > 3) <span class="pg-btn pg-dots">...</span> @endif
          @for($i = max(2, $transactions->currentPage() - 1); $i <= min($transactions->lastPage() - 1, $transactions->currentPage() + 1); $i++)
            <a class="pg-btn {{ $transactions->currentPage() == $i ? 'pg-active' : '' }}"
              href="{{ $transactions->url($i) }}&{{ http_build_query(request()->except('page')) }}">{{ $i }}</a>
          @endfor
          @if($transactions->currentPage() < $transactions->lastPage() - 2) <span class="pg-btn pg-dots">...</span> @endif
          @if($transactions->lastPage() > 1) <a
            class="pg-btn {{ $transactions->currentPage() == $transactions->lastPage() ? 'pg-active' : '' }}"
            href="{{ $transactions->url($transactions->lastPage()) }}&{{ http_build_query(request()->except('page')) }}">{{ $transactions->lastPage() }}</a>
          @endif
          @if($transactions->hasMorePages()) <a class="pg-btn"
          href="{{ $transactions->nextPageUrl() }}&{{ http_build_query(request()->except('page')) }}">&gt;</a> @else <span
            class="pg-btn pg-disabled">&gt;</span> @endif
        </div>
      @endif
    </div>
  </div>

  {{-- MODAL DETAIL --}}
  <div class="mo" id="txDetailModal">
    <div class="modal">
      <div class="mh">
        <div class="mt">💳 Detail Transaksi</div><button class="mc" onclick="closeModal('txDetailModal')">✕</button>
      </div>
      <div style="background:#FBF8F5;border-radius:11px;padding:13px;margin-bottom:13px;">
        <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;">ID
          Transaksi</div>
        <div style="font-size:15px;font-weight:900;font-family:'Poppins',sans-serif;color:var(--or);" id="tdId"></div>
      </div>
      <div class="urow" style="border:none;padding:0;margin-bottom:11px;">
        <div class="uava" style="background:var(--pk-pale);color:var(--pk);width:42px;height:42px;font-size:17px;"
          id="tdAva"></div>
        <div class="udet">
          <div class="unm" style="font-size:14px;" id="tdName"></div>
          <div class="uml" id="tdEmail"></div>
        </div>
      </div>
      <table class="modal-table" style="width:100%;">
        <tr>
          <td style="padding:6px 0;font-size:11.5px;font-weight:900;">Paket</td>
          <td style="font-weight:800;text-align:right;" id="tdPaket"></td>
        </tr>
        <tr>
          <td style="padding:6px 0;font-size:11.5px;font-weight:900;">Tanggal Pesan</td>
          <td style="font-weight:800;text-align:right;" id="tdTgl"></td>
        </tr>
        <tr>
          <td style="padding:6px 0;font-size:11.5px;font-weight:900;">Tanggal Reservasi</td>
          <td style="font-weight:800;text-align:right;" id="tdTanggal"></td>
        </tr>
        <tr>
          <td style="padding:6px 0;font-size:11.5px;font-weight:900;">Jam Kunjungan</td>
          <td style="font-weight:800;text-align:right;" id="tdJam"></td>
        </tr>
        <tr>
          <td style="padding:6px 0;font-size:11.5px;font-weight:900;">Metode Pembayaran</td>
          <td style="font-weight:800;text-align:right;" id="tdMetode"></td>
        </tr>
        <tr>
          <td style="padding:6px 0;font-size:11.5px;font-weight:900;">Telepon</td>
          <td style="font-weight:800;text-align:right;" id="tdTelepon"></td>
        </tr>
        <tr style="border-top:2px solid var(--border);">
          <td style="padding:9px 0;font-size:13px;font-weight:900;">Total</td>
          <td style="font-size:16px;font-weight:900;text-align:right;color:var(--or);font-family:'Poppins',sans-serif;"
            id="tdTotal"></td>
        </tr>
      </table>
      <div style="display:flex;gap:8px;margin-top:10px;">
        <button id="refundBtn" class="btn btn-ou btn-dg" style="flex:1; justify-content:center;"
          onclick="openModal('refundConfirmModal')">↩ Refund</button>
      </div>
    </div>
  </div>

  {{-- MODAL KONFIRMASI REFUND --}}
  <div class="mo" id="refundConfirmModal">
    <div class="modal" style="max-width:380px;">
      <div class="delete-head" style="background:#FFF4E6;border-bottom:0.5px solid #FFD9A8;">
        <div class="delete-icon-wrap" style="background:#FFD9A8;"><svg width="24" height="24" viewBox="0 0 24 24"
            fill="none" stroke="#C77700" stroke-width="2">
            <polyline points="1 4 1 10 7 10" />
            <path d="M3.51 15a9 9 0 1 0 .49-5L1 10" />
          </svg></div>
        <div class="delete-title" style="color:#C77700;">Refund transaksi ini?</div>
        <div class="delete-sub" style="color:#C77700;">Pembayaran yang sudah direfund tidak bisa dikembalikan.</div>
      </div>
      <div class="delete-body">
        <div style="display:flex;flex-direction:column;gap:10px;">
          <button class="btn btn-dg" style="display:flex;align-items:center;justify-content:center;text-align:center;"
            onclick="refundConfirmed()">Ya, refund sekarang</button>
          <button class="btn btn-ou" style="display:flex;align-items:center;justify-content:center;text-align:center;"
            onclick="closeModal('refundConfirmModal')">Batal</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    let selectedRefundId = null, selectedKodeQr = null;
    function autoSearch(input) { clearTimeout(window.searchTimer); window.searchTimer = setTimeout(() => input.form.submit(), 500); }

    document.querySelectorAll('.tx-detail-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        selectedRefundId = this.dataset.id; selectedKodeQr = this.dataset.kodeqr;
        document.getElementById('tdId').textContent = this.dataset.kodeqr; document.getElementById('tdAva').textContent = this.dataset.av; document.getElementById('tdName').textContent = this.dataset.nm; document.getElementById('tdEmail').textContent = this.dataset.em; document.getElementById('tdPaket').textContent = this.dataset.pk; document.getElementById('tdTgl').textContent = this.dataset.tgl; document.getElementById('tdTotal').textContent = this.dataset.tot; document.getElementById('tdTanggal').textContent = this.dataset.tanggal; document.getElementById('tdJam').textContent = this.dataset.jam; document.getElementById('tdMetode').textContent = this.dataset.metode; document.getElementById('tdTelepon').textContent = this.dataset.telepon;
        const refundBtn = document.getElementById('refundBtn');
        const status = this.dataset.status;

        console.log(status);

        if (status === 'paid') {
          refundBtn.style.display = 'flex';
        } else {
          refundBtn.style.display = 'none';
        }

        openModal('txDetailModal');
      });
    });

    async function refundConfirmed() {
      if (!selectedRefundId || !selectedKodeQr) { alert('ID transaksi atau kode QR tidak ditemukan'); return; }
      try {
        const response = await fetch(`/admin/transactions/refund/${selectedRefundId}`, { method: 'PUT', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ kode_qr: selectedKodeQr }) });
        const data = await response.json();
        if (data.success) { location.reload(); } else { alert(data.message || 'Refund gagal'); }
      } catch (error) { console.error(error); alert('Terjadi kesalahan saat refund'); }
    }
  </script>
@endpush