<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\KelompokKelas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StatusAkademik;
use App\Services\KolomExcelMahasiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export (xlsx) untuk halaman Administrasi > Mahasiswa — dipanggil dari tombol Export di
 * App\Livewire\Admin\Mahasiswa\Index. Query & filter (search, prodi, kelas mahasiswa, semester
 * masuk, status akademik) disalin ulang dari MahasiswaController::index (bukan di-share), sama
 * seperti NilaiExportController terhadap NilaiController — lihat skill siak-livewire-module.
 * Tidak dipaginasi: seluruh baris yang cocok dengan filter yang sedang dipilih ikut diexport.
 *
 * Kolomnya SAMA PERSIS dengan template impor (App\Services\KolomExcelMahasiswa), supaya hasil
 * ekspor bisa diedit lalu diimpor kembali. Karena itu sheet data sengaja polos: impor membaca
 * sheet AKTIF dan hanya membuang SATU baris judul, jadi judul laporan, info filter, dan kolom "No"
 * yang dulu ada di atas tabel akan menggeser seluruh kolom saat diimpor ulang. Info filter dipindah
 * ke sheet kedua.
 */
class MahasiswaExportController extends Controller
{
    public function excel(Request $request): StreamedResponse
    {
        $search = $request->get('search');
        $prodiId = $request->get('id_prodi') ? (int) $request->get('id_prodi') : null;
        $kelompokKelasId = $request->get('id_kelompok_kelas') ? (int) $request->get('id_kelompok_kelas') : null;
        $semesterMasukId = $request->get('id_semester_masuk') ? (int) $request->get('id_semester_masuk') : null;
        $statusAkademikId = $request->get('id_status_akademik') ? (int) $request->get('id_status_akademik') : null;

        $query = Mahasiswa::with(KolomExcelMahasiswa::RELASI);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($prodiId) {
            $query->where('id_prodi', $prodiId);
        }

        if ($kelompokKelasId) {
            $query->where('id_kelompok_kelas', $kelompokKelasId);
        }

        if ($semesterMasukId) {
            $query->where('id_semester_masuk', $semesterMasukId);
        }

        if ($statusAkademikId) {
            $query->where('id_status_akademik', $statusAkademikId);
        }

        $mahasiswaList = $query->orderBy('nama')->get();

        $spreadsheet = new Spreadsheet;

        // ---- Sheet 1: data, identik dengan template impor (judul di baris 1, data mulai baris 2).
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Mahasiswa');

        $headers = KolomExcelMahasiswa::HEADER;
        $sheet->fromArray([$headers], null, 'A1');
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 2;
        foreach ($mahasiswaList as $mhs) {
            foreach (KolomExcelMahasiswa::baris($mhs) as $i => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $cell = Coordinate::stringFromColumnIndex($i + 1).$row;
                if (in_array($i, KolomExcelMahasiswa::KOLOM_ANGKA, true)) {
                    // Tanpa format ribuan: impor membaca nilai yang sudah diformat, dan "5,000,000"
                    // akan terbaca sebagai 5,0.
                    $sheet->setCellValue($cell, $value);
                } else {
                    $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                }
            }
            $row++;
        }

        for ($i = 1; $i <= count($headers); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(20);
        }
        $sheet->freezePane('C2');
        if ($row > 2) {
            $sheet->setAutoFilter('A1:'.$lastCol.($row - 1));
        }

        // ---- Sheet 2: info ekspor (dulu di atas tabel data).
        $filterLines = [
            'Pencarian' => $search ?: null,
            'Prodi' => $prodiId ? Prodi::find($prodiId)?->nama : null,
            'Kelas Mahasiswa' => $kelompokKelasId ? KelompokKelas::find($kelompokKelasId)?->nama : null,
            'Semester Masuk' => $semesterMasukId ? Semester::find($semesterMasukId)?->nama : null,
            'Status Akademik' => $statusAkademikId ? StatusAkademik::find($statusAkademikId)?->nama : null,
        ];
        $filterLabel = collect($filterLines)->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode(' | ');

        $info = $spreadsheet->createSheet();
        $info->setTitle('Info Export');
        $info->fromArray([
            ['DATA MAHASISWA'],
            ['Filter:', $filterLabel !== '' ? $filterLabel : 'Semua data (tanpa filter)'],
            ['Tanggal Export:', date('d/m/Y H:i:s')],
            ['Total Data:', $mahasiswaList->count()],
            [],
            ['Catatan:', "Sheet 'Data Mahasiswa' memakai format yang sama dengan template impor, sehingga bisa diedit lalu diimpor kembali. Baris dengan NIM yang sudah ada akan memperbarui data mahasiswa tersebut."],
        ], null, 'A1');
        $info->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $info->getColumnDimension('A')->setWidth(18);
        $info->getColumnDimension('B')->setWidth(90);

        // Impor membaca sheet AKTIF — pastikan yang aktif adalah sheet data, bukan sheet info.
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'mahasiswa_'.date('YmdHis').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
