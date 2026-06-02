<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    private string $apiUrl = "https://sixties-pout-envoy.ngrok-free.dev/api";

    public function index()
    {
        $today = Carbon::today()->toDateString();
        $capacity = 120;
        $occupancyPct = 0;

        try {
            $todaySlot = $this->getTodaySlotWithDebug();
            $slot = $todaySlot['slot'] ?? null;

            if (is_array($slot)) {
                $capacity = (int) ($slot['kapasitas_maksimal'] ?? 120);
            }
        } catch (\Exception $e) {
            Log::error('Gagal mengambil kapasitas hari ini', [
                'error' => $e->getMessage(),
            ]);
        }

        $allTransactions = $this->fetchAllTransactions(3);

        $totalRevenue = $allTransactions
            ->filter(fn ($tx) => ($tx['status_pembayaran'] ?? null) === 'paid')
            ->sum('total_harga');

        $totalVisitors = $allTransactions
            ->where('status_pembayaran', 'paid')
            ->count();

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
            'kapasitas_maksimal' => 'required|integer|min:1',
        ]);

        try {
            $todaySlotResult = $this->getTodaySlotWithDebug();

            if (!$todaySlotResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $todaySlotResult['message'],
                    'debug' => $todaySlotResult,
                ], 500);
            }

            $slot = $todaySlotResult['slot'];
            $slotId = $this->extractSlotId($slot);

            if (!$slotId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID slot hari ini tidak ditemukan dari response /slots/today.',
                    'debug' => [
                        'api_url' => "{$this->apiUrl}/slots/today",
                        'slot_response' => $todaySlotResult,
                    ],
                ], 500);
            }

            $updateUrl = "{$this->apiUrl}/capacity/update/{$slotId}";

            $updateResponse = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->put($updateUrl, [
                    'kapasitas_maksimal' => (int) $request->kapasitas_maksimal,
                ]);

            $bodyText = $updateResponse->body();
            $bodyJson = null;

            try {
                $bodyJson = $updateResponse->json();
            } catch (\Exception $e) {
                $bodyJson = null;
            }

            Log::info('Update kapasitas response', [
                'update_url' => $updateUrl,
                'slot_id' => $slotId,
                'status' => $updateResponse->status(),
                'body' => $bodyText,
            ]);

            if (!$updateResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'API /capacity/update/{id} gagal. Cek debug untuk detail error.',
                    'debug' => [
                        'update_url' => $updateUrl,
                        'slot_id' => $slotId,
                        'status' => $updateResponse->status(),
                        'body' => $bodyText,
                        'json' => $bodyJson,
                    ],
                ], 500);
            }

            if (is_array($bodyJson) && array_key_exists('success', $bodyJson) && $bodyJson['success'] === false) {
                return response()->json([
                    'success' => false,
                    'message' => $bodyJson['message'] ?? 'API mengembalikan success false saat update kapasitas.',
                    'debug' => [
                        'update_url' => $updateUrl,
                        'slot_id' => $slotId,
                        'api_response' => $bodyJson,
                    ],
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => is_array($bodyJson)
                    ? ($bodyJson['message'] ?? 'Kapasitas berhasil diperbarui.')
                    : 'Kapasitas berhasil diperbarui.',
                'slot_id' => $slotId,
                'debug' => [
                    'slots_today_url' => "{$this->apiUrl}/slots/today",
                    'update_url' => $updateUrl,
                    'api_response' => $bodyJson ?? $bodyText,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error update kapasitas dari admin', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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

    private function getTodaySlotWithDebug(): array
    {
        $url = "{$this->apiUrl}/slots/today";

        $response = Http::timeout(20)
            ->withHeaders(['Accept' => 'application/json'])
            ->get($url);

        $bodyText = $response->body();
        $bodyJson = null;

        try {
            $bodyJson = $response->json();
        } catch (\Exception $e) {
            $bodyJson = null;
        }

        Log::info('GET slots today response', [
            'url' => $url,
            'status' => $response->status(),
            'body' => $bodyText,
        ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'Gagal mengambil /slots/today dari API.',
                'url' => $url,
                'status' => $response->status(),
                'body' => $bodyText,
                'json' => $bodyJson,
                'slot' => null,
            ];
        }

        if (!$bodyJson || !is_array($bodyJson)) {
            return [
                'success' => false,
                'message' => 'Response /slots/today bukan JSON array/object yang valid.',
                'url' => $url,
                'status' => $response->status(),
                'body' => $bodyText,
                'json' => $bodyJson,
                'slot' => null,
            ];
        }

        if (($bodyJson['success'] ?? true) === false) {
            return [
                'success' => false,
                'message' => $bodyJson['message'] ?? 'API /slots/today mengembalikan success false.',
                'url' => $url,
                'status' => $response->status(),
                'body' => $bodyText,
                'json' => $bodyJson,
                'slot' => null,
            ];
        }

        $slot = $bodyJson['data']
            ?? $bodyJson['slot']
            ?? $bodyJson['time_slot']
            ?? $bodyJson['todaySlot']
            ?? $bodyJson['today_slot']
            ?? $bodyJson;

        return [
            'success' => is_array($slot),
            'message' => is_array($slot)
                ? 'Slot hari ini ditemukan.'
                : 'Slot hari ini tidak ditemukan di response API.',
            'url' => $url,
            'status' => $response->status(),
            'body' => $bodyText,
            'json' => $bodyJson,
            'slot' => is_array($slot) ? $slot : null,
        ];
    }

    private function extractSlotId(array $slot): ?string
    {
        $possibleKeys = [
            '_id',
            'id',
            'id_time_slot',
            'time_slot_id',
            'slot_id',
            'id_slot',
            'timeSlotId',
            'time_slotId',
        ];

        foreach ($possibleKeys as $key) {
            if (!empty($slot[$key])) {
                return (string) $slot[$key];
            }
        }

        foreach ($slot as $value) {
            if (is_array($value)) {
                $nestedId = $this->extractSlotId($value);
                if ($nestedId) {
                    return $nestedId;
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
                'page' => $page,
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
