<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;
use App\Models\Package;
use App\Models\Transaction;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Bersihkan koleksi lama
        Admin::truncate();
        Package::truncate();
        Transaction::truncate();

        // ── ADMIN ──────────────────────────────────────────────
        Admin::create([
            'name'     => 'Kailala',
            'email'    => 'admin@playzone.com',
            'password' => Hash::make('password123'),
            'role'     => 'admin',
        ]);

        // ── PACKAGES ───────────────────────────────────────────
        $packages = [
            ['icon'=>'🍼', 'name'=>'Tiny Tots',      'description'=>'Usia 2-4 Tahun -- 2 jam',        'price'=>50000,  'duration'=>2, 'age_min'=>2,  'age_max'=>4,  'status'=>'active',   'color_class'=>'cp'],
            ['icon'=>'🧒', 'name'=>'Kids Explorer',  'description'=>'Usia 5-8 Tahun -- 3 jam',        'price'=>80000,  'duration'=>3, 'age_min'=>5,  'age_max'=>8,  'status'=>'active',   'color_class'=>'ct'],
            ['icon'=>'⭐', 'name'=>'Junior Champ',   'description'=>'Usia 9-12 Tahun -- 3 jam',       'price'=>100000, 'duration'=>3, 'age_min'=>9,  'age_max'=>12, 'status'=>'active',   'color_class'=>'co'],
            ['icon'=>'👨‍👩‍👧', 'name'=>'Family Blast',   'description'=>'Keluarga -- 4 jam',             'price'=>200000, 'duration'=>4, 'age_min'=>0,  'age_max'=>99, 'status'=>'active',   'color_class'=>'cpu'],
            ['icon'=>'🎉', 'name'=>'Birthday Party', 'description'=>'Khusus ulang tahun -- 5 jam',    'price'=>500000, 'duration'=>5, 'age_min'=>0,  'age_max'=>99, 'status'=>'inactive', 'color_class'=>'cg'],
        ];

        foreach ($packages as $pkg) {
            Package::create($pkg);
        }

        // ── TRANSACTIONS ────────────────────────────────────────
        $data = [
            ['Kayclab', 'kayclab@gmail.com', 'Family Blast',  200000, 'paid'],
            ['Kailo',   'kailo@gmail.com',   'Family Blast',  200000, 'paid'],
            ['Kaili',   'kaili@gmail.com',   'Tiny Tots',      50000, 'paid'],
            ['Kaile',   'kaile@gmail.com',   'Tiny Tots',      50000, 'unpaid'],
            ['Kaila',   'kaila@gmail.com',   'Tiny Tots',      50000, 'paid'],
            ['Kailu',   'kailu@gmail.com',   'Family Blast',  200000, 'refund'],
            ['Aulia',   'aulia@gmail.com',   'Junior Champ',  100000, 'paid'],
            ['Bima',    'bima@gmail.com',    'Kids Explorer',  80000, 'paid'],
        ];

        foreach ($data as $i => $row) {
            Transaction::create([
                'transaction_id' => 'TRX-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'user_name'      => $row[0],
                'user_email'     => $row[1],
                'package_name'   => $row[2],
                'total'          => $row[3],
                'status'         => $row[4],
                'date'           => Carbon::create(2026, 3, 3)->subDays($i % 3)->toDateString(),
            ]);
        }

        // ── SETTINGS (kapasitas) ────────────────────────────────
        // Pakai model Settings sederhana, atau simpan di collection manual
        \App\Models\Setting::updateOrCreate(
            ['key' => 'capacity'],
            ['value' => 120]
        );

        $this->command->info('✅ Seeder PlayZone selesai!');
    }
}