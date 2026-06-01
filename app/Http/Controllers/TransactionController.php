<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class TransactionController extends Controller
{
    private $apiUrl = 'https://sixties-pout-envoy.ngrok-free.dev/api/transactions';

    public function index(Request $request)
    {
        $page = 1;
        $allData = collect();
        $maxPage = 2;

        do {
            $response = Http::timeout(10)->get($this->apiUrl, ['page' => $page]);

            if (!$response->successful()) {
                break;
            }

            $json = $response->json();
            $allData = $allData->merge($json['data'] ?? []);
            $lastPage = $json['last_page'] ?? 1;
            $page++;
        } while ($page <= $lastPage && $page <= $maxPage);

        $oneMonthAgo = Carbon::now()->subDays(30);

        $transactions = collect();

        foreach ($allData as $tx) {
            $details = $tx['details'] ?? [];

            if (empty($details)) {
                $transactions->push((object) [
                    'id' => $tx['id'],
                    'transaction_id' => $tx['id'],
                    'user_name' => $tx['nama_customer'] ?? '-',
                    'user_email' => $tx['telepon'] ?? '-',
                    'package_name' => '-',
                    'total' => $tx['total_harga'] ?? 0,
                    'status' => $tx['status_pembayaran'] ?? 'paid',
                    'status_ticket' => $tx['status_ticket'] ?? null,
                    'date' => $tx['created_at'] ?? now(),
                    'hari' => $tx['hari_reservasi'] ?? '-',
                    'tanggal_reservasi' => $tx['tanggal_reservasi'] ?? '-',
                    'jam' => '-',
                    'metode' => $tx['metode_pembayaran'] ?? '-',
                    'telepon' => $tx['telepon'] ?? '-',
                ]);

                continue;
            }

            foreach ($details as $detail) {
                $kodeQr = $detail['kode_qr'] ?? $detail['qr_code'] ?? $detail['kodeQR'] ?? $tx['id'];

                $transactions->push((object) [
                    'id' => $tx['id'],
                    'transaction_id' => $kodeQr,
                    'user_name' => $tx['nama_customer'] ?? '-',
                    'user_email' => $tx['telepon'] ?? '-',
                    'package_name' => $detail['nama_paket'] ?? $detail['nama_package'] ?? '-',
                    'total' => $detail['harga'] ?? $detail['subtotal'] ?? $tx['total_harga'] ?? 0,
                    'status' => $tx['status_pembayaran'] ?? 'paid',
                    'status_ticket' => $detail['status_ticket'] ?? $detail['status'] ?? null,
                    'date' => $tx['created_at'] ?? now(),
                    'hari' => $tx['hari_reservasi'] ?? '-',
                    'tanggal_reservasi' => $tx['tanggal_reservasi'] ?? '-',
                    'jam' => $detail['jam_kunjungan'] ?? '-',
                    'metode' => $tx['metode_pembayaran'] ?? '-',
                    'telepon' => $tx['telepon'] ?? '-',
                ]);
            }
        }

        $transactions = $transactions->filter(function ($tx) use ($oneMonthAgo) {
            return Carbon::parse($tx->date) >= $oneMonthAgo;
        });

        if ($request->search) {
            $search = strtolower($request->search);

            $transactions = $transactions->filter(function ($tx) use ($search) {
                return str_contains(strtolower($tx->user_name), $search)
                    || str_contains(strtolower($tx->transaction_id), $search)
                    || str_contains(strtolower($tx->package_name), $search);
            });
        }

        if ($request->status && $request->status !== 'all') {
            $transactions = $transactions->filter(function ($tx) use ($request) {
                $status = strtolower($tx->status_ticket ?? $tx->status);
                return $status === strtolower($request->status);
            });
        }

        if ($request->package && $request->package !== 'all') {
            $transactions = $transactions->filter(function ($tx) use ($request) {
                return $tx->package_name === $request->package;
            });
        }

        if ($request->date) {
            $transactions = $transactions->filter(function ($tx) use ($request) {
                return Carbon::parse($tx->date)->toDateString() === $request->date;
            });
        }

        $transactions = $transactions->sortByDesc('date')->values();

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;

        $paged = $transactions->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $transactions = new LengthAwarePaginator(
            $paged,
            $transactions->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $total = $transactions->total();

        $pkgResponse = Http::timeout(10)->get('https://sixties-pout-envoy.ngrok-free.dev/api/packages');
        $packages = $pkgResponse->successful()
            ? collect($pkgResponse->json()['data'] ?? [])
            : collect();

        return view('transactions.index', compact('transactions', 'total', 'packages'));
    }

    public function show($id)
    {
        return response()->json(Http::get("{$this->apiUrl}/{$id}")->json());
    }

    public function refund(Request $request, $id)
    {
        $request->validate([
            'kode_qr' => 'required|string',
        ]);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->put("https://sixties-pout-envoy.ngrok-free.dev/api/transactions/refund/{$id}", [
                    'kode_qr' => $request->kode_qr,
                ]);

            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
                'api_response' => $response->json() ?? $response->body(),
            ], $response->successful() ? 200 : 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}