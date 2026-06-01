// ── NOTIFIKASI ─────────────────────────────────────────
function removeNotif(event, btn) {
    event.stopPropagation();
    const item = btn.closest('.np-item'), notifId = item.dataset.id;
    let deleted = JSON.parse(localStorage.getItem('deleted_notifs') || '[]');

    if (!deleted.includes(notifId)) deleted.push(notifId);
    localStorage.setItem('deleted_notifs', JSON.stringify(deleted));
    item.remove();
    if (document.querySelectorAll('.np-item').length === 0) {
    document.querySelector('#notifPanel').insertAdjacentHTML(
        'beforeend',
        '<div class="notif-empty">🔕 Belum ada notifikasi</div>'
    );
}
}
function toggleNotif(e) { e.stopPropagation(); document.getElementById('notifPanel').classList.toggle('open'); }
function closeNotifPanel() { const p = document.getElementById('notifPanel'); if (p) p.classList.remove('open'); }
function markAllRead() {
    document.querySelectorAll('.np-unread').forEach(el => el.classList.remove('np-unread'));
    const dot = document.getElementById('notifDot'); if (dot) dot.style.display = 'none';

    const notifSignature = Array.from(document.querySelectorAll('.np-item')).map(el => el.innerText.trim()).join('|');
    localStorage.setItem('notif_signature', notifSignature);
}
document.addEventListener('click', e => {
    const panel = document.getElementById('notifPanel'), btn = document.getElementById('notifBtn');
    if (panel && btn && !panel.contains(e.target) && !btn.contains(e.target)) panel.classList.remove('open');
});

// ── MODAL ─────────────────────────────────────────────
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.addEventListener('click', e => { if (e.target.classList.contains('mo')) e.target.classList.remove('open'); });

// ── TOMBOL MATA TRANSAKSI ─────────────────────────────
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.tx-detail-btn'); if (!btn) return;
    const fields = ['id', 'av', 'nm', 'em', 'pk', 'tgl', 'tanggal', 'jam', 'metode', 'telepon', 'tot'];
    const elements = ['tdId', 'tdAva', 'tdName', 'tdEmail', 'tdPaket', 'tdTgl', 'tdTanggal', 'tdJam', 'tdMetode', 'tdTelepon', 'tdTotal'];
    
    fields.forEach((field, i) => document.getElementById(elements[i]).textContent = btn.dataset[field]);
    window.currentTxId = btn.dataset.id;
    
    document.getElementById('refundBtn').style.display = (btn.dataset.status === 'refund' || btn.dataset.status === 'refunded' || btn.dataset.status === 'digunakan') ? 'none' : 'flex';
    openModal('txDetailModal');
});

// ── KAPASITAS (AJAX) ──────────────────────────────────
function enableEditCap() { document.getElementById('capViewMode').style.display = 'none'; document.getElementById('capEditMode').style.display = 'block'; }
function cancelEditCap() { document.getElementById('capViewMode').style.display = 'block'; document.getElementById('capEditMode').style.display = 'none'; }
function saveCapVal() {
    const v = parseInt(document.getElementById('capInput').value);
    if (!v || v < 1) { alert('Masukkan kapasitas yang valid!'); return; }
    fetch('/admin/settings/capacity', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ kapasitas_maksimal: v })
    })
    .then(r => r.json())
    .then(data => { if (data.success) { document.getElementById('capDisplay').textContent = v; location.reload(); } else { alert('Gagal update kapasitas'); } })
    .catch(err => { console.error(err); alert('Terjadi kesalahan koneksi'); });
}

function openLogoutModal() { document.getElementById('logoutModal').classList.add('open'); }
function closeLogoutModal() { document.getElementById('logoutModal').classList.remove('open'); }
function confirmLogout() { document.getElementById('logoutForm').submit(); }
window.addEventListener('click', function(e) { if (e.target === document.getElementById('logoutModal')) closeLogoutModal(); });

// ── CHART.JS ──────────────────────────────────────────
function initChart(labels, values) {
    const ctx = document.getElementById('salesChart'); if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{ data: values, borderRadius: 8, borderSkipped: false, backgroundColor: ['rgba(245,163,74,0.75)', 'rgba(244,119,122,0.75)', 'rgba(109,200,192,0.75)', 'rgba(176,125,212,0.75)', 'rgba(245,163,74,0.75)', 'rgba(244,119,122,0.9)', 'rgba(109,200,192,0.75)'] }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1000000, font: { family: 'Nunito', size: 10, weight: '700' }, color: '#B0A098', callback: v => v === 0 ? '0' : (v / 1000000) + ' jt' }, grid: { color: '#F0E8E0' } },
                x: { grid: { display: false }, ticks: { font: { family: 'Nunito', size: 11, weight: '700' }, color: '#B0A098' } }
            }
        }
    });
}

// ── FLASH MESSAGE & NOTIF INIT ────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const deleted = JSON.parse(localStorage.getItem('deleted_notifs') || '[]');
    document.querySelectorAll('.np-item').forEach(item => { if (deleted.includes(item.dataset.id)) item.remove(); });
    const flash = document.getElementById('flashMsg'); if (flash) setTimeout(() => flash.style.display = 'none', 3500);
    const btn = document.getElementById('notifBtn'), dot = document.getElementById('notifDot'); if (!btn || !dot) return;
    
    const currentSignature = Array.from(document.querySelectorAll('.np-item')).map(el => el.innerText.trim()).join('|');
    const lastSignature = localStorage.getItem('notif_signature');

    if (lastSignature === null) {
        localStorage.setItem('notif_signature', currentSignature);
        dot.style.display = 'none';
        return;
    }
    dot.style.display = (currentSignature !== lastSignature) ? 'block' : 'none';
});

// ── REFUND CONFIRMED ──────────────────────────────────
function refundConfirmed() {
    if (!window.currentTxId) { alert('ID transaksi tidak ditemukan'); return; }
    fetch('/admin/transactions/refund/' + window.currentTxId, {
        method: 'PUT',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) { closeModal('refundConfirmModal'); alert('Refund berhasil'); setTimeout(() => location.reload(), 500); } 
        else { alert('Refund gagal'); }
    })
    .catch(err => { console.error(err); alert('Terjadi kesalahan koneksi'); });
}