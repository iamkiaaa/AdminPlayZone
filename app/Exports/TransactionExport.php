<?php

namespace App\Exports;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TransactionExport implements FromArray, ShouldAutoSize, WithStyles
{

    protected $data;
    protected $from;
    protected $to;

    public function __construct($data, $from, $to)
    {
        $this->data = $data;
        $this->from = $from;
        $this->to = $to;
    }
    public function array(): array
    {
        $rows = [];
        $rows[] = ['PLAYZONE'];
        $rows[] = ['LAPORAN TRANSAKSI'];
        $rows[] = ['Periode : ' . $this->from . ' s/d ' . $this->to];

        $totalRevenue = 0;
        $totalTransaksi = 0;

        foreach ($this->data as $tx) {
            if ($tx['status_pembayaran'] == 'paid') {
                $totalRevenue += $tx['total_harga'];
            }
            $totalTransaksi++;
        }

        $rows[] = ['Total Revenue : Rp ' . number_format($totalRevenue, 0, ',', '.')];
        $rows[] = ['Total Transaksi : ' . $totalTransaksi];
        $rows[] = [];

        $rows[] = ['No', 'Tanggal', 'Nama', 'Paket', 'Total', 'Status'];

        $no = 1;

        foreach ($this->data as $tx) {
            $rows[] = [
                $no++,
                Carbon::parse($tx['created_at'])->format('d-m-Y'),
                $tx['nama_customer'],
                $tx['details'][0]['nama_paket'] ?? '-',
                'Rp ' . number_format($tx['total_harga'], 0, ',', '.'),
                ucfirst($tx['status_pembayaran'])
            ];
        }

        return $rows;
    }
    public function styles(Worksheet $sheet)
    {

        // Judul utama
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()
            ->setBold(true)
            ->setSize(16);

        $sheet->getStyle('A1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Judul laporan
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()
            ->setBold(true)
            ->setSize(13);

        $sheet->getStyle('A2')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Periode
        $sheet->mergeCells('A3:F3');
        $sheet->getStyle('A3')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // HEADER SEKARANG BARIS 6
        $sheet->getStyle('A6:F6')->getFont()->setBold(true);

        $sheet->getStyle('A6:F6')
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFD9D9D9');

        $sheet->getStyle('A6:F6')
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $lastRow = $sheet->getHighestRow();

        // BORDER
        $sheet->getStyle("A6:F{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // FORMAT RUPIAH
        $sheet->getStyle("E5:E{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('"Rp" #,##0');

        // ALIGN DATA
        $sheet->getStyle("A5:A{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("B5:B{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("F5:F{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

    }

}