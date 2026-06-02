<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    /**
     * Endpoint API utama.
     * Ganti URL ini jika endpoint ngrok/API kamu berubah.
     */
    private string $apiUrl = "https://sixties-pout-envoy.ngrok-free.dev/api";

    /**
     * Fallback ID lama.
     * Hanya dipakai kalau API belum bisa mengambil capacity berdasarkan tanggal hari ini.
     * Kalau endpoint daily capacity sudah benar-benar tersedia, boleh dihapus.
     */
    private ?string $fallbackCapId = "6a1aaad36e2bd186d80b0e5d";

    public function index()
    {
        $today = Carbon::today()->toDateString();
        $capacity = 120;
        $occupancyPct = 0;

        // Ambil kapasitas berdasarkan tanggal hari ini, bukan berdasarkan ID hardcode.
        $todayCapacity = $this->getTodayCapacity();

        if ($todayCapacity && isset($todayCapacity['kapasitas_maksimal'])) {
            $capacity = (int) $todayCapacity['kapasitas_maksimal'];
        }

        // Menggabungkan request pagination API menggunakan helper internal.
        $allTransactions = $this->fetchAllTransactions(3);

        $totalRevenue = $allTransactions
            ->filter(fn ($tx) => ($tx['status_pembayaran'] ?? null) === 'paid')
            ->sum('total_harga');

        $totalVisitors = $allTransactions
            ->where('status_pembayaran', 'paid')
            ->count();

        // =============================
        // HITUNG DATA HARI INI
        // =============================
        $todayTransactions = $allTransactions->filter(function ($tx) use ($today) {
            return isset($tx['created_at']) && Carbon::parse($tx['created_at'])->toDateString() === $today;
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

        // =============================
        // GRAFIK 7 HARI
        // =============================
        $chartData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);

            $chartData[] = [
                'label' => $date->locale('id')->isoFormat('ddd'),
                'value' => $allTransactions
                    ->filter(function ($tx) use ($date) {
                        return isset($tx['created_at'])
                            && Carbon::parse($tx['created_at'])->toDateString() === $date->toDateString()
                            && ($tx['status_pembayaran'] ?? null) === 'paid';
                    })
                    ->sum('total_harga'),
            ];
        }

        // =============================
        // TRANSAKSI TERBARU
        // =============================
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

    public function updateCapacity(Request $request)
    {
        $request->validate([
            'kapasitas_maksimal' => 'required|integer|min:1'
        ]);

        try {
            // 1. Ambil slot hari ini dari API
            $slotResponse = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$this->apiUrl}/slots/today");

            if (!$slotResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil slot hari ini dari API.',
                    'status' => $slotResponse->status(),
                    'body' => $slotResponse->body(),
                ], 500);
            }

            $json = $slotResponse->json();

            // 2. Baca kemungkinan bentuk response API
            $slot = $json['data'] 
                ?? $json['slot'] 
                ?? $json['time_slot'] 
                ?? $json;

            $slotId = $slot['_id'] 
                ?? $slot['id'] 
                ?? $slot['id_time_slot'] 
                ?? null;

            if (!$slotId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID slot hari ini tidak ditemukan dari response /slots/today.',
                    'response_slots_today' => $json,
                ], 500);
            }

            // 3. Update kapasitas berdasarkan ID slot hari ini
            $updateResponse = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->put("{$this->apiUrl}/capacity/update/{$slotId}", [
                    'kapasitas_maksimal' => (int) $request->kapasitas_maksimal
                ]);

            if (!$updateResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'API gagal update kapasitas.',
                    'slot_id' => $slotId,
                    'status' => $updateResponse->status(),
                    'body' => $updateResponse->body(),
                ], 500);
            }

            $updateJson = $updateResponse->json();

            return response()->json([
                'success' => $updateJson['success'] ?? true,
                'message' => $updateJson['message'] ?? 'Kapasitas berhasil diperbarui.',
                'slot_id' => $slotId,
                'api_response' => $updateJson,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi error di admin: ' . $e->getMessage(),
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
                if (!isset($tx['created_at'])) {
                    return false;
                }

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
            if (!isset($tx['created_at'])) {
                return false;
            }

            $date = Carbon::parse($tx['created_at'])->toDateString();

            return $date >= $from && $date <= $to;
        });

        return Pdf::loadView('exports.transactions_pdf', compact('transactions', 'from', 'to'))
            ->download('laporan-transaksi.pdf');
    }

    public function exportExcel(Request $request)
    {
        $from = $request->from;
        $to = $request->to;

        $transactions = $this->fetchAllTransactions()->filter(function ($tx) use ($from, $to) {
            if (!isset($tx['created_at'])) {
                return false;
            }

            $date = Carbon::parse($tx['created_at'])->toDateString();

            return $date >= $from && $date <= $to;
        });

        return Excel::download(
            new \App\Exports\TransactionExport($transactions, $from, $to),
            'laporan-transaksi.xlsx'
        );
    }

    /**
     * Ambil data capacity/time_slot untuk tanggal hari ini.
     *
     * Catatan:
     * - Fungsi ini mencoba beberapa pola endpoint supaya lebih fleksibel.
     * - Kalau API kamu hanya punya satu endpoint khusus, boleh sisakan endpoint yang sesuai saja.
     */
    private function getTodayCapacity(): ?array
    {
        $today = Carbon::today()->toDateString();

        $endpointCandidates = [
            [
                'url' => "{$this->apiUrl}/capacity/today",
                'query' => ['tanggal' => $today],
            ],
            [
                'url' => "{$this->apiUrl}/capacity/today",
                'query' => ['date' => $today],
            ],
            [
                'url' => "{$this->apiUrl}/capacity/by-date/{$today}",
                'query' => [],
            ],
            [
                'url' => "{$this->apiUrl}/capacity",
                'query' => ['tanggal' => $today],
            ],
            [
                'url' => "{$this->apiUrl}/capacity",
                'query' => ['date' => $today],
            ],
        ];

        foreach ($endpointCandidates as $endpoint) {
            try {
                $response = Http::timeout(10)->get($endpoint['url'], $endpoint['query']);

                if (!$response->successful()) {
                    continue;
                }

                $capacity = $this->normalizeCapacityResponse($response->json());

                if ($capacity) {
                    return $capacity;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        /**
         * Fallback supaya dashboard tetap tampil kalau endpoint daily capacity belum tersedia.
         * Namun untuk update per hari, sebaiknya tetap gunakan endpoint berdasarkan tanggal.
         */
        if ($this->fallbackCapId) {
            try {
                $response = Http::timeout(10)->get("{$this->apiUrl}/capacity/{$this->fallbackCapId}");

                if ($response->successful()) {
                    return $this->normalizeCapacityResponse($response->json());
                }
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Menyamakan bentuk response API menjadi array capacity langsung.
     * Mendukung beberapa bentuk response umum:
     * - { success: true, data: {...} }
     * - { data: {...} }
     * - { capacity: {...} }
     * - { _id: ..., kapasitas_maksimal: ... }
     */
    private function normalizeCapacityResponse(?array $json): ?array
    {
        if (!$json) {
            return null;
        }

        if (isset($json['data']) && is_array($json['data'])) {
            // Kalau data berupa list, ambil item pertama.
            if (array_is_list($json['data'])) {
                return $json['data'][0] ?? null;
            }

            return $json['data'];
        }

        if (isset($json['capacity']) && is_array($json['capacity'])) {
            return $json['capacity'];
        }

        if (isset($json['time_slot']) && is_array($json['time_slot'])) {
            return $json['time_slot'];
        }

        if (isset($json['kapasitas_maksimal'])) {
            return $json;
        }

        return null;
    }

    /**
     * Ambil ID capacity dari beberapa kemungkinan nama field.
     */
    private function resolveCapacityId(array $capacity): ?string
    {
        return $capacity['_id']
            ?? $capacity['id']
            ?? $capacity['capacity_id']
            ?? $capacity['id_capacity']
            ?? $capacity['time_slot_id']
            ?? $capacity['id_time_slot']
            ?? null;
    }

    /**
     * Helper method: sentralisasi perulangan HTTP Client.
     */
    private function fetchAllTransactions($maxPage = null)
    {
        $page = 1;
        $allData = collect();

        do {
            try {
                $response = Http::timeout(10)->get("{$this->apiUrl}/transactions", [
                    'page' => $page,
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
