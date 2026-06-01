<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class PackageController extends Controller
{

    private $apiUrl =
        'https://sixties-pout-envoy.ngrok-free.dev/api/packages';



    public function index(Request $request)
    {
        $response = Http::get($this->apiUrl);

        if ($response->successful()) {

            $json = $response->json();

            if (isset($json['data'])) {

                $packages = collect($json['data']);

            } else {

                $packages = collect($json);

            }

        } else {

            $packages = collect([]);

        }

        // SEARCH
        if ($request->search) {

            $packages = $packages->filter(function ($pkg) use ($request) {

                return str_contains(
                    strtolower($pkg['nama_package'] ?? ''),
                    strtolower($request->search)
                );

            });

        }

        // FILTER STATUS
        if (
            $request->status &&
            $request->status !== 'all'
        ) {

            $packages = $packages->filter(function ($pkg) use ($request) {

                return ($pkg['status'] ?? '') == $request->status;

            });
        }
        return view(
            'packages.index',
            compact('packages')
        );
    }
    public function store(Request $request)
    {
        $request->validate([
            'nama_package' => 'required',
            'harga' => 'required|integer|min:0',
            'durasi_jam' => 'required|integer|min:1',

            'usia_min' => 'required|integer|min:0',
            'usia_maks' => 'required|integer|gte:usia_min',

            'deskripsi' => 'required',
            'status' => 'required',
        ]);

        $data = [
            'nama_package' => $request->nama_package,
            'ikon' => $request->ikon,
            'harga' => (int) $request->harga,
            'durasi_jam' => (int) $request->durasi_jam,
            'usia_min' => (int) $request->usia_min,
            'usia_maks' => (int) $request->usia_maks,
            'deskripsi' => $request->deskripsi,
            'status' => $request->status,
        ];

        Http::post(
            $this->apiUrl,
            $data
        );

        return redirect()
            ->route('admin.packages.index')
            ->with(
                'success',
                'Paket berhasil ditambah!'
            );

    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_package' => 'required',
            'harga' => 'required|integer|min:0',
            'durasi_jam' => 'required|integer|min:1',

            'usia_min' => 'required|integer|min:0',
            'usia_maks' => 'required|integer|gte:usia_min',

            'deskripsi' => 'required',
            'status' => 'required',
        ]);

        $data = [

            'nama_package' => $request->nama_package,
            'ikon' => $request->ikon,
            'harga' => (int) $request->harga,
            'durasi_jam' => (int) $request->durasi_jam,
            'usia_min' => (int) $request->usia_min,
            'usia_maks' => (int) $request->usia_maks,
            'deskripsi' => $request->deskripsi,
            'status' => $request->status,

        ];

        $response = Http::put(
            $this->apiUrl . '/' . $id,
            $data
        );

        if (!$response->successful()) {

            return redirect()
                ->route('admin.packages.index')
                ->with('error', 'Update gagal');

        }

        return redirect()
            ->route('admin.packages.index')
            ->with(
                'success',
                'Paket diperbarui!'
            );

    }
    public function destroy($id)
    {

        // DELETE KE API
        Http::delete(

            $this->apiUrl . '/' . $id

        );


        return redirect()
            ->route('admin.packages.index')
            ->with(
                'success',
                'Paket dihapus!'
            );

    }

}