@extends('layouts.app')
@section('title', 'Paket Bermain')
@section('page_title', 'Paket Bermain')
@section('page_subtitle', 'Kelola Paket dan Harga')

@section('content')
  <div class="toolbar">
    <form method="GET" action="{{ route('admin.packages.index') }}" style="display:flex;gap:9px;flex:1;flex-wrap:wrap;">
      <div class="srch" style="max-width:280px;"><i class="fas fa-search srch-icon"></i><input name="search" placeholder="Cari Paket..." value="{{ request('search') }}" /></div>
      <select name="status" class="fsel" onchange="this.form.submit()">
        <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Semua Status</option>
        <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
        <option value="non-aktif" {{ request('status') === 'non-aktif' ? 'selected' : '' }}>Non-aktif</option>
      </select>
    </form>
    <button class="btn btn-pr" onclick="openModal('addPkgModal')">+ Tambah Paket</button>
  </div>

  <div style="max-width:720px;">
    @forelse($packages as $pkg)
      @php
        $colorMap = ['cp' => 'var(--pk)', 'ct' => 'var(--tl)', 'co' => 'var(--or)', 'cpu' => 'var(--pu)', 'cg' => 'var(--muted)'];
        $colors = ['cp', 'ct', 'co', 'cpu', 'cg']; $index = $loop->index ?? 0;
        $colorClass = $colors[$index % count($colors)]; $priceColor = $colorMap[$colorClass];
      @endphp
      <div class="pkc {{ $colorClass }} {{ ($pkg['status'] ?? '') == 'non-aktif' ? 'muted' : '' }}">
        <div class="pki">{{ $pkg['ikon'] }}</div>
        <div class="pkin">
          <div class="pknm">{{ $pkg['nama_package'] }}</div>
          <div class="pkmt">{{ $pkg['deskripsi'] }} (Usia {{ $pkg['usia_min'] ?? '-' }}–{{ $pkg['usia_maks'] ?? '-' }} tahun)</div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div class="pkpr" style="color:{{ $priceColor }};">Rp {{ number_format($pkg['harga'], 0, ',', '.') }}</div>
          <span class="pkbg {{ ($pkg['status'] == 'aktif') ? 'active' : 'inactive' }}">{{ $pkg['status'] == 'aktif' ? 'Aktif' : 'Non-aktif' }}</span>
        </div>
        <button class="btn btn-ou btn-sm" style="margin-left:8px;flex-shrink:0;" onclick='openEditPkg(@json($pkg["id"]), @json($pkg["nama_package"]), @json($pkg["ikon"]), @json($pkg["harga"]), @json($pkg["durasi_jam"]), @json($pkg["usia_min"] ?? null), @json($pkg["usia_maks"] ?? null), @json($pkg["deskripsi"]), @json($pkg["status"]))'>Edit</button>
      </div>
    @empty
      <div style="text-align:center;padding:40px;color:var(--muted);font-weight:700;">Belum ada paket. Tambahkan paket pertama!</div>
    @endforelse
  </div>

  {{-- MODAL TAMBAH --}}
  <div class="mo" id="addPkgModal">
    <div class="modal">
      <div class="mh"><div class="mt">🎁 Tambah Paket Baru</div><button class="mc" onclick="closeModal('addPkgModal')">✕</button></div>
      <form action="{{ route('admin.packages.store') }}" method="POST">
        @csrf
        <div class="frow">
          <div class="fg"><label class="fl">Nama Paket</label><input class="fi" name="nama_package" placeholder="Nama paket..." required /></div>
          <div class="fg"><label class="fl">Ikon</label><input class="fi" name="ikon" placeholder="🎈" /></div>
        </div>
        <div class="frow">
          <div class="fg"><label class="fl">Harga (Rp)</label><input class="fi" type="number" name="harga" placeholder="50000" required /></div>
          <div class="fg"><label class="fl">Durasi (Jam)</label><input class="fi" type="number" name="durasi_jam" placeholder="2" required /></div>
        </div>
        <div class="frow">
          <div class="fg"><label class="fl">Usia Minimal (Tahun)</label><input class="fi" type="number" name="usia_min" min="0" placeholder="2" required /></div>
          <div class="fg"><label class="fl">Usia Maksimal (Tahun)</label><input class="fi" type="number" name="usia_maks" min="0" placeholder="30" required /></div>
        </div>
        <div class="fg"><label class="fl">Deskripsi</label><input class="fi" name="deskripsi" placeholder="Masukan deskripsi paket..." required /></div>
        <div class="fg"><label class="fl">Status</label><select class="fi" name="status"><option value="aktif">Aktif</option><option value="non-aktif">Non-aktif</option></select></div>
        <div style="display:flex;gap:9px;"><button class="btn btn-ou" type="submit" style="flex:0.5; justify-content:center;">Simpan</button><button class="btn btn-dg" type="button" style="flex:0.5; justify-content:center;" onclick="closeModal('addPkgModal')">Batal</button></div>
      </form>
    </div>
  </div>

  {{-- MODAL EDIT --}}
  <div class="mo" id="editPkgModal">
    <div class="modal">
      <div class="mh"><div class="mt">✏️ Edit Paket</div><button class="mc" onclick="closeModal('editPkgModal')">✕</button></div>
      <form id="editPkgForm" method="POST">
        @csrf @method('PUT')
        <div class="frow">
          <div class="fg"><label class="fl">Nama Paket</label><input class="fi" name="nama_package" id="ePNm" /></div>
          <div class="fg"><label class="fl">Ikon</label><input class="fi" name="ikon" id="ePIc" /></div>
        </div>
        <div class="frow">
          <div class="fg"><label class="fl">Harga (Rp)</label><input class="fi" type="number" name="harga" id="ePPr" /></div>
          <div class="fg"><label class="fl">Durasi (Jam)</label><input class="fi" type="number" name="durasi_jam" id="ePDr" /></div>
        </div>
        <div class="frow">
          <div class="fg"><label class="fl">Usia Minimal (Tahun)</label><input class="fi" type="number" name="usia_min" min="0" id="ePUsMin" /></div>
          <div class="fg"><label class="fl">Usia Maksimal (Tahun)</label><input class="fi" type="number" name="usia_maks" min="0" id="ePUsMax" /></div>
        </div>
        <div class="fg"><label class="fl">Deskripsi</label><input class="fi" name="deskripsi" required id="ePMt" /></div>
        <div class="fg"><label class="fl">Status</label><select class="fi" name="status" id="ePSt"><option value="aktif">Aktif</option><option value="non-aktif">Non-aktif</option></select></div>
        <div style="display:flex;gap:9px;"><button class="btn btn-ou" type="submit" style="flex:0.5; justify-content:center;">Simpan</button><button class="btn btn-dg" type="button" style="flex:0.5; justify-content:center;" onclick="confirmDelete()">🗑 Hapus</button></div>
      </form>
      <form id="deletePkgForm" method="POST" style="display:none;">@csrf @method('DELETE')</form>
    </div>
  </div>

  {{-- MODAL KONFIRMASI DELETE --}}
  <div class="mo" id="deleteConfirmModal">
    <div class="modal" style="max-width:380px;">
      <div class="delete-head">
        <div class="delete-icon-wrap"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#A32D2D" stroke-width="2"><polyline points="3 6 5 6 21 6" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /><path d="M10 11v6" /><path d="M14 11v6" /><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" /></svg></div>
        <div class="delete-title">Hapus paket ini?</div>
        <div class="delete-sub">Data yang dihapus tidak dapat dikembalikan.</div>
      </div>
      <div class="delete-body">
        <div style="display:flex;flex-direction:column;gap:10px;">
          <button class="btn btn-dg" style="display:flex;justify-content:center;text-align:center;" onclick="deleteConfirmed()">Ya, hapus sekarang</button>
          <button class="btn btn-ou" style="display:flex;justify-content:center;text-align:center;" onclick="closeModal('deleteConfirmModal')">Batal</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    function openEditPkg(id, name, ikon, price, duration, usiaMin, usiaMax, desc, status) {
      let url = `/admin/packages/${id}`; document.getElementById('editPkgForm').action = url; document.getElementById('deletePkgForm').action = url;
      document.getElementById('ePNm').value = name; document.getElementById('ePIc').value = ikon; document.getElementById('ePPr').value = price; document.getElementById('ePDr').value = duration;
      document.getElementById('ePUsMin').value = usiaMin; document.getElementById('ePUsMax').value = usiaMax; document.getElementById('ePMt').value = desc; document.getElementById('ePSt').value = status ?? 'aktif';
      openModal('editPkgModal');
    }
    function confirmDelete() { openModal('deleteConfirmModal'); }
    function deleteConfirmed() { document.getElementById('deletePkgForm').submit(); }
  </script>
@endpush