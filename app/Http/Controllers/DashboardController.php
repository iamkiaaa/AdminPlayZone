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
    // Properti Endpoint API untuk memangkas duplikasi string URL
    private $apiUrl = "https://sixties-pout-envoy.ngrok-free.dev/api";
    private $capId = "6a1aaad36e2bd186d80b0e5d";

    public function index()
    {
        $today = Carbon::today()->toDateString();
        $capacity = 120;
        $occupancyPct = 0;

        try {
            $response = Http::get("{$this->apiUrl}/capacity/{$this->capId}");
            if ($response->successful() && isset($response->json()['success']) && $response->json()['success']) {
                $capacity = (int) $response->json()['kapasitas_maksimal'];
            }
        } catch (\Exception $e) {
        }

        // Menggabungkan request pagination API menggunakan refaktorisasi helper internal
        $allTransactions = $this->fetchAllTransactions(3);

        $totalRevenue = $allTransactions->filter(fn($tx) => $tx['status_pembayaran'] == 'paid')->sum('total_harga');
        $totalVisitors = $allTransactions
            ->where('status_pembayaran', 'paid')
            ->count();

        // =============================
        // HITUNG DATA HARI INI
        // =============================
        $todayTransactions = $allTransactions->filter(fn($tx) => Carbon::parse($tx['created_at'])->toDateString() == $today);
        $revenueToday = $todayTransactions->where('status_pembayaran', 'paid')->sum('total_harga');
        $ticketSold = $todayTransactions->sum(function ($tx) {
            return collect($tx['details'] ?? [])
                ->whereIn('status_ticket', ['aktif', 'digunakan'])
                ->count();
        });
        $visitorToday = $todayTransactions
            ->where('status_pembayaran', 'paid')
            ->count();

        $occupancyPct = $capacity > 0 ? min(100, round(($visitorToday / $capacity) * 100)) : 0;

        // =============================
        // GRAFIK 7 HARI
        // =============================
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $chartData[] = [
                'label' => $date->locale('id')->isoFormat('ddd'),
                'value' => $allTransactions->filter(fn($tx) => Carbon::parse($tx['created_at'])->toDateString() == $date->toDateString() && $tx['status_pembayaran'] == 'paid')->sum('total_harga')
            ];
        }

        // =============================
        // TRANSAKSI TERBARU
        // =============================
        $recentTransactions = collect();

        foreach ($allTransactions as $tx) {

            foreach (($tx['details'] ?? []) as $detail) {

                $recentTransactions->push((object) [
                    'user_name' => $tx['nama_customer'],
                    'package_name' => $detail['nama_paket'] ?? '-',
                    'total' => $tx['total_harga'],
                    'status' => $detail['status_ticket'] ?? 'paid',
                    'created_at' => $tx['created_at'],
                ]);
            }
        }

        $recentTransactions = $recentTransactions
            ->sortByDesc('created_at')
            ->take(4);

        return view('dashboard.index', compact('revenueToday', 'visitorToday', 'ticketSold', 'occupancyPct', 'capacity', 'chartData', 'recentTransactions', 'totalRevenue', 'totalVisitors'));
    }

    public function updateCapacity(Request $request)
    {
        $request->validate(['kapasitas_maksimal' => 'required|integer|min:1']);
        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])->put("{$this->apiUrl}/capacity/update/{$this->capId}", [
                'kapasitas_maksimal' => (int) $request->kapasitas_maksimal
            ]);
            return response()->json(['success' => $response->successful(), 'message' => $response->successful() ? null : $response->body()], $response->successful() ? 200 : 500);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
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
        $transactions = $this->fetchAllTransactions()->filter(fn($tx) => ($d = Carbon::parse($tx['created_at'])->toDateString()) >= $from && $d <= $to);
        return Pdf::loadView('exports.transactions_pdf', compact('transactions', 'from', 'to'))->download('laporan-transaksi.pdf');
    }

    public function exportExcel(Request $request)
    {
        $from = $request->from;
        $to = $request->to;
        $transactions = $this->fetchAllTransactions()->filter(fn($tx) => ($d = Carbon::parse($tx['created_at'])->toDateString()) >= $from && $d <= $to);
        return Excel::download(new \App\Exports\TransactionExport($transactions, $from, $to), 'laporan-transaksi.xlsx');
    }

    // HELPER METHOD: Sentralisasi perulangan HTTP Client (Don't Repeat Yourself)
    private function fetchAllTransactions($maxPage = null)
    {
        $page = 1;
        $allData = collect();
        do {
            $response = Http::timeout(10)->get("{$this->apiUrl}/transactions", ['page' => $page]);
            if (!$response->successful())
                break;

            $json = $response->json();
            $allData = $allData->merge($json['data'] ?? []);
            $lastPage = $json['last_page'] ?? 1;
            $page++;
        } while ($page <= $lastPage && ($maxPage === null || $page <= $maxPage));

        return $allData;
    }
}