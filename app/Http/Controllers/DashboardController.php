<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    /**
     * Endpoint API utama.
     * Sesuaikan kalau URL API berubah.
     */
    private $apiUrl = "https://sixties-pout-envoy.ngrok-free.dev/api";

    public function index()
    {
        $today = Carbon::today('Asia/Jakarta')->toDateString();
        $capacity = 120;
        $occupancyPct = 0;

        // =====================================================
        // AMBIL KAPASITAS HARI INI DARI /slots/today
        // =====================================================
        try {
            $todaySlot = $this->getTodaySlot();

            if ($todaySlot) {
                $capacity = (int) (
                    $todaySlot['kapasitas_maksimal']
                    ?? $todaySlot['capacity']
                    ?? $todaySlot['max_capacity']
                    ?? 120
                );
            }
        } catch (\Exception $e) {
            $capacity = 120;
        }

        // =====================================================
        // TRANSAKSI
        // =====================================================
        $allTransactions = $this->fetchAllTransactions(3);

        $totalRevenue = $allTransactions
            ->filter(fn ($tx) => ($tx['status_pembayaran'] ?? null) == 'paid')
            ->sum('total_harga');

        $totalVisitors = $allTransactions
            ->where('status_pembayaran', 'paid')
            ->count();

        // =====================================================
        // HITUNG DATA HARI INI
        // =====================================================
        $todayTransactions = $allTransactions->filter(function ($tx) use ($today) {
            return isset($tx['created_at']) && Carbon::parse($tx['created_at'])->toDateString() == $today;
        });

        $revenueToday = $todayTransactions
            ->where('status_pembayaran', 'paid')
            ->sum('total_harga');

        $ticketSold = $todayTransactions->sum(function ($tx) {
            return collect($tx['details'] ?? [])
                ->whereIn('status_ticket', ['aktif', 'digunakan'])
                ->count();
        });

        $visitorToday = $todayTransactions
            ->where('status_pembayaran', 'paid')
            ->count();

        $occupancyPct = $capacity > 0
            ? min(100, round(($visitorToday / $capacity) * 100))
            : 0;

        // =====================================================
        // GRAFIK 7 HARI
        // =====================================================
        $chartData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today('Asia/Jakarta')->subDays($i);

            $chartData[] = [
                'label' => $date->locale('id')->isoFormat('ddd'),
                'value' => $allTransactions
                    ->filter(function ($tx) use ($date) {
                        return isset($tx['created_at'])
                            && Carbon::parse($tx['created_at'])->toDateString() == $date->toDateString()
                            && ($tx['status_pembayaran'] ?? null) == 'paid';
                    })
                    ->sum('total_harga')
            ];
        }

        // =====================================================
        // TRANSAKSI TERBARU
        // =====================================================
        $recentTransactions = collect();

        foreach ($allTransactions as $tx) {
            foreach (($tx['details'] ?? []) as $detail) {
                $recentTransactions->push((object) [
                    'user_name' => $tx['nama_customer'] ?? '-',
                    'package_name' => $detail['nama_paket'] ?? '-',
                    'total' => $tx['total_harga'] ?? 0,
                    'status' => $detail['status_ticket'] ?? 'paid',
                    'created_at' => $tx['created_at'] ?? null,
                ]);
            }
        }

        $recentTransactions = $recentTransactions
            ->sortByDesc('created_at')
            ->take(4);

        return view('dashboard.index', compact(
            'revenueToday',
            'visitorToday',
            'ticketSold',
            'occupancyPct',
            'capacity',
            'chartData',
            'recentTransactions',
            'totalRevenue',
            'totalVisitors'
        ));
    }

    /**
     * Update kapasitas dari admin.
     * Alur:
     * 1. Ambil slot hari ini dari GET /api/slots/today
     * 2. Ambil ID slot dari response API
     * 3. Update kapasitas ke PUT /api/capacity/update/{id}
     */
    public function updateCapacity(Request $request)
    {
        $request->validate([
            'kapasitas_maksimal' => 'required|integer|min:1'
        ]);

        try {
            $todaySlot = $this->getTodaySlot();

            if (!$todaySlot) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data slot hari ini tidak ditemukan dari API /slots/today.'
                ], 404);
            }

            $slotId = $this->extractSlotId($todaySlot);

            if (!$slotId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID slot hari ini tidak ditemukan dari response API.',
                    'response' => $todaySlot
                ], 500);
            }

            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->put("{$this->apiUrl}/capacity/update/{$slotId}", [
                    'kapasitas_maksimal' => (int) $request->kapasitas_maksimal
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'API gagal update kapasitas.',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'slot_id' => $slotId
                ], 500);
            }

            $json = $response->json();

            return response()->json([
                'success' => $json['success'] ?? true,
                'message' => $json['message'] ?? 'Kapasitas berhasil diperbarui.',
                'slot_id' => $slotId,
                'data' => $json
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi error di admin: ' . $e->getMessage()
            ], 500);
        }
    }

    public function summary(Request $request)
    {
        $from = $request->from;
        $to = $request->to;

        $transactions = $this->fetchAllTransactions();

        if ($from && $to) {
            $transactions = $transactions->filter(function ($tx) use ($from, $to) {
                $date = Carbon::parse($tx['created_at'])->toDateString();
                return $date >= $from && $date <= $to;
            });
        }

        $totalRevenue = $transactions
            ->where('status_pembayaran', 'paid')
            ->sum('total_harga');

        $totalVisitors = $transactions->sum(function ($tx) {
            return collect($tx['details'] ?? [])
                ->whereIn('status_ticket', ['aktif', 'digunakan'])
                ->count();
        });

        return response()->json([
            'revenue' => $totalRevenue,
            'visitors' => $totalVisitors,
        ]);
    }

    public function exportPdf(Request $request)
    {
        $from = $request->from;
        $to = $request->to;

        $transactions = $this->fetchAllTransactions()->filter(function ($tx) use ($from, $to) {
            $d = Carbon::parse($tx['created_at'])->toDateString();
            return $d >= $from && $d <= $to;
        });

        return Pdf::loadView('exports.transactions_pdf', compact('transactions', 'from', 'to'))
            ->download('laporan-transaksi.pdf');
    }

    public function exportExcel(Request $request)
    {
        $from = $request->from;
        $to = $request->to;

        $transactions = $this->fetchAllTransactions()->filter(function ($tx) use ($from, $to) {
            $d = Carbon::parse($tx['created_at'])->toDateString();
            return $d >= $from && $d <= $to;
        });

        return Excel::download(
            new \App\Exports\TransactionExport($transactions, $from, $to),
            'laporan-transaksi.xlsx'
        );
    }

    /**
     * Ambil slot hari ini dari API.
     * Support banyak bentuk response:
     * - { data: {...} }
     * - { slot: {...} }
     * - { time_slot: {...} }
     * - { today_slot: {...} }
     * - langsung {...}
     */
    private function getTodaySlot()
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$this->apiUrl}/slots/today");

            if (!$response->successful()) {
                return null;
            }

            $json = $response->json();

            if (!is_array($json)) {
                return null;
            }

            if (isset($json['success']) && $json['success'] === false) {
                return null;
            }

            return $json['data']
                ?? $json['slot']
                ?? $json['time_slot']
                ?? $json['today_slot']
                ?? $json;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Ambil ID dari berbagai kemungkinan nama field.
     */
    private function extractSlotId(array $slot)
    {
        return $slot['_id']
            ?? $slot['id']
            ?? $slot['id_time_slot']
            ?? $slot['time_slot_id']
            ?? $slot['slot_id']
            ?? null;
    }

    /**
     * Ambil semua transaksi dari API dengan pagination.
     */
    private function fetchAllTransactions($maxPage = null)
    {
        $page = 1;
        $allData = collect();

        do {
            try {
                $response = Http::timeout(10)->get("{$this->apiUrl}/transactions", [
                    'page' => $page
                ]);

                if (!$response->successful()) {
                    break;
                }

                $json = $response->json();
                $allData = $allData->merge($json['data'] ?? []);
                $lastPage = $json['last_page'] ?? 1;
                $page++;
            } catch (\Exception $e) {
                break;
            }
        } while ($page <= $lastPage && ($maxPage === null || $page <= $maxPage));

        return $allData;
    }
}
