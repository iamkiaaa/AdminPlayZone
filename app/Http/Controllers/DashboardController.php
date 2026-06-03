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
     * Base URL API.
     * Pastikan ini sesuai dengan API kamu yang aktif.
     */
    private $apiUrl = "https://sixties-pout-envoy.ngrok-free.dev/api";

    public function index()
    {
        $today = Carbon::today('Asia/Jakarta')->toDateString();
        $capacity = 120;
        $occupancyPct = 0;

        /**
         * Ambil kapasitas dari slot hari ini.
         * Dibuat sangat fleksibel karena response API /slots/today bisa berbentuk:
         * - { kapasitas_maksimal: 20 }
         * - { data: { kapasitas_maksimal: 20 } }
         * - { slot: { kapasitas_maksimal: 20 } }
         * - { time_slot: { kapasitas_maksimal: 20 } }
         * - nested lebih dalam.
         */
        try {
            $todaySlotResponse = $this->getTodaySlotResponse();
            $apiCapacity = $this->findFirstValueByKeys($todaySlotResponse, [
                'kapasitas_maksimal',
                'kapasitas',
                'capacity',
                'max_capacity',
                'maximum_capacity',
            ]);

            if ($apiCapacity !== null && is_numeric($apiCapacity)) {
                $capacity = (int) $apiCapacity;
            }
        } catch (\Exception $e) {
            // Jangan matikan dashboard kalau API gagal.
            $capacity = 120;
        }

        $allTransactions = $this->fetchAllTransactions(3);

        $totalRevenue = $allTransactions
            ->filter(fn ($tx) => ($tx['status_pembayaran'] ?? null) == 'paid')
            ->sum('total_harga');

        $totalVisitors = $allTransactions
            ->where('status_pembayaran', 'paid')
            ->count();

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

        $occupancyPct = $capacity > 0 ? min(100, round(($visitorToday / $capacity) * 100)) : 0;

        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today('Asia/Jakarta')->subDays($i);
            $chartData[] = [
                'label' => $date->locale('id')->isoFormat('ddd'),
                'value' => $allTransactions->filter(function ($tx) use ($date) {
                    return isset($tx['created_at'])
                        && Carbon::parse($tx['created_at'])->toDateString() == $date->toDateString()
                        && ($tx['status_pembayaran'] ?? null) == 'paid';
                })->sum('total_harga')
            ];
        }

        $recentTransactions = collect();

        foreach ($allTransactions as $tx) {
            foreach (($tx['details'] ?? []) as $detail) {
                $recentTransactions->push((object) [
                    'user_name' => $tx['nama_customer'] ?? '-',
                    'package_name' => $detail['nama_paket'] ?? '-',
                    'total' => $tx['total_harga'] ?? 0,
                    'status' => $detail['status_ticket'] ?? 'paid',
                    'created_at' => $tx['created_at'] ?? now(),
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
            $todaySlotResponse = $this->getTodaySlotResponse();

            $slotId = $this->findFirstValueByKeys($todaySlotResponse, [
                '_id',
                'id',
                'id_time_slot',
                'time_slot_id',
                'slot_id',
            ]);

            if (!$slotId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID slot hari ini tidak ditemukan dari endpoint /slots/today.',
                    'slots_today_response' => $todaySlotResponse,
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
                    'slot_id' => $slotId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ], 500);
            }

            $json = $response->json();

            return response()->json([
                'success' => $json['success'] ?? true,
                'message' => $json['message'] ?? 'Kapasitas berhasil diperbarui.',
                'slot_id' => $slotId,
                'api_response' => $json,
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
            $date = Carbon::parse($tx['created_at'])->toDateString();
            return $date >= $from && $date <= $to;
        });

        return Excel::download(
            new \App\Exports\TransactionExport($transactions, $from, $to),
            'laporan-transaksi.xlsx'
        );
    }

    /**
     * Ambil response mentah dari /slots/today.
     */
    private function getTodaySlotResponse()
    {
        $response = Http::timeout(10)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("{$this->apiUrl}/slots/today");

        if (!$response->successful()) {
            throw new \Exception('Gagal mengambil /slots/today. Status: ' . $response->status() . ' Body: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Cari value pertama dari key yang mungkin muncul di response API, termasuk nested array.
     */
    private function findFirstValueByKeys($data, array $keys)
    {
        if (!is_array($data)) {
            return null;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $found = $this->findFirstValueByKeys($value, $keys);
                if ($found !== null && $found !== '') {
                    return $found;
                }
            }
        }

        return null;
    }

    private function fetchAllTransactions($maxPage = null)
    {
        $page = 1;
        $allData = collect();

        do {
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
        } while ($page <= $lastPage && ($maxPage === null || $page <= $maxPage));

        return $allData;
    }
}
