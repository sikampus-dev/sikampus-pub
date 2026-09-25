<?php

namespace App\Http\Controllers;

use App\Models\KomponenBiaya;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use App\Models\Tagihan;
use App\Models\TagihanRinci;
use App\Models\User;
use App\Services\JadwalTagihanTahap;
use App\Services\KeringananBiayaKreditService;
use App\Services\KeuanganAksesMahasiswaService;
use App\Services\PenerapanTagihanTahap;
use App\Services\PenomoranDokumen;
use App\Services\SeriNomorDokumen;
use App\Services\StatusPembayaranTagihan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TagihanController extends Controller
{
    /**
     * Status tampilan admin. Rumusnya tinggal satu, di StatusPembayaranTagihan.
     *
     * @return string lunas|dibayar_sebagian|belum_bayar|kedaluwarsa
     */
    private function statusPembayaranAcc(Tagihan $tagihan, float $totalDisetujui, float $kreditKeringanan = 0.0): string
    {
        return StatusPembayaranTagihan::hitung($tagihan, $totalDisetujui, $kreditKeringanan);
    }

    /**
     * @return array<int>|null null = tanpa batasan scope
     */
    private function allowedProdiIdsForStrukturBiaya(?User $user): ?array
    {
        if (! $user || ! $user->hasScopeRestriction()) {
            return null;
        }

        return $user->getAllowedProdiIds();
    }

    /** Sama seperti filter di StrukturBiayaController::index */
    private function applyStrukturBiayaProdiScope(Builder $query, ?User $user): void
    {
        $allowed = $this->allowedProdiIdsForStrukturBiaya($user);
        if ($allowed === null) {
            return;
        }
        if ($allowed === []) {
            $query->whereRaw('0 = 1');
        } else {
            $query->whereIn('id_prodi', $allowed);
        }
    }

    /**
     * @return array<int>|null null = tanpa batasan scope
     */
    private function allowedProdiIdsForTagihan(?User $user): ?array
    {
        if (! $user || ! $user->hasScopeRestriction()) {
            return null;
        }

        return $user->getAllowedProdiIds();
    }

    private function applyTagihanMahasiswaProdiScope(Builder $query, ?User $user): void
    {
        $allowed = $this->allowedProdiIdsForTagihan($user);
        if ($allowed === null) {
            return;
        }
        if ($allowed === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereHas('mahasiswa', function (Builder $q) use ($allowed): void {
            $q->whereIn('id_prodi', $allowed);
        });
    }

    private function assertTagihanInUserScope(Request $request, Tagihan $tagihan): void
    {
        $allowed = $this->allowedProdiIdsForTagihan($request->user());
        if ($allowed === null) {
            return;
        }

        if ($allowed === []) {
            abort(403, 'Tagihan tidak termasuk lingkup akses Anda.');
        }

        $prodiId = (int) ($tagihan->mahasiswa?->id_prodi ?? 0);
        if (! in_array($prodiId, $allowed, true)) {
            abort(403, 'Tagihan tidak termasuk lingkup akses Anda.');
        }
    }

    /**
     * Query dasar daftar tagihan (filter sama untuk index & ekspor Excel).
     */
    private function buildTagihanListQuery(Request $request): Builder
    {
        $search = $request->get('search');
        $mahasiswaId = $request->get('id_mahasiswa');
        $prodiId = $request->get('id_prodi');
        $semesterId = $request->get('id_semester');
        $status = $request->get('status');
        $statusPembayaranAcc = $request->get('status_pembayaran_acc');

        $query = Tagihan::with([
            'mahasiswa.prodi',
            'semester',
            'tagihanRinci.komponenBiaya',
        ]);
        $this->applyTagihanMahasiswaProdiScope($query, $request->user());

        $allowedProdiIds = $this->allowedProdiIdsForTagihan($request->user());
        if ($prodiId && $allowedProdiIds !== null && ! in_array((int) $prodiId, $allowedProdiIds, true)) {
            $prodiId = null;
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('mahasiswa', function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('nim', 'like', "%{$search}%");
                });
            });
        }

        if ($mahasiswaId) {
            $query->where('id_mahasiswa', $mahasiswaId);
        }

        if ($prodiId) {
            $query->whereHas('mahasiswa', function ($q) use ($prodiId) {
                $q->where('id_prodi', $prodiId);
            });
        }

        if ($semesterId) {
            $query->where('id_semester', $semesterId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($request->boolean('lewat_jatuh_tempo')) {
            $query->whereNotNull('tanggal_jatuh_tempo')
                ->whereDate('tanggal_jatuh_tempo', '<', Carbon::today());
        }

        // Filter memakai ekspresi yang sama dengan label barisnya, jadi mustahil mengembalikan
        // baris yang statusnya berbeda dari filter yang dipilih.
        if (array_key_exists($statusPembayaranAcc, StatusPembayaranTagihan::opsi())) {
            $query->whereRaw(StatusPembayaranTagihan::sqlEkspresi().' = ?', [$statusPembayaranAcc]);
        }

        return $query;
    }

    /**
     * Satu tagihan per (mahasiswa, semester, tahap) — bukan lagi per (mahasiswa, semester).
     *
     * Aturan lama menolak tagihan kedua di semester yang sama, padahal generateFromStrukturBiaya
     * memang membuat satu tagihan per tahap. Akibatnya tagihan multi-tahap tidak pernah bisa
     * diedit lagi lewat update()/form. Tagihan tanpa tahap (mis. hasil import atau input manual)
     * menempati slotnya sendiri, jadi tetap dibatasi satu per semester.
     */
    private function tagihanKembarExists(int $mahasiswaId, int $semesterId, ?int $tahap, ?int $kecualiId = null): bool
    {
        return Tagihan::where('id_mahasiswa', $mahasiswaId)
            ->where('id_semester', $semesterId)
            ->when(
                $tahap === null,
                fn ($q) => $q->whereNull('tahap'),
                fn ($q) => $q->where('tahap', $tahap)
            )
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->exists();
    }

    private function pesanTagihanKembar(?int $tahap): string
    {
        return $tahap === null
            ? 'Tagihan tanpa tahap untuk mahasiswa dan semester ini sudah ada.'
            : "Tagihan tahap {$tahap} untuk mahasiswa dan semester ini sudah ada.";
    }

    private function labelStatusPembayaranAcc(string $status): string
    {
        return StatusPembayaranTagihan::opsiRingkas()[$status] ?? $status;
    }

    /**
     * Ekspor daftar tagihan ke Excel sesuai filter halaman index (bukan hanya halaman terkini).
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $query = $this->buildTagihanListQuery($request);
        $tagihans = $query->orderBy('tanggal_tagihan', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $ids = $tagihans->pluck('id');
        $sums = collect();
        if ($ids->isNotEmpty()) {
            $sums = Pembayaran::query()
                ->whereIn('id_tagihan', $ids->all())
                ->whereNull('deleted_at')
                ->whereNotNull('approved_at')
                ->groupBy('id_tagihan')
                ->selectRaw('id_tagihan, SUM(nominal) as total_disetujui')
                ->pluck('total_disetujui', 'id_tagihan');
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tagihan');

        $sheet->setCellValue('A1', 'DAFTAR TAGIHAN');
        $sheet->setCellValue('A2', 'Tanggal ekspor: '.date('d/m/Y H:i:s'));

        $rowMeta = 3;
        if ($request->get('search')) {
            $sheet->setCellValue('A'.$rowMeta, 'Pencarian: '.$request->get('search'));
            $rowMeta++;
        }
        if ($request->get('id_prodi')) {
            $p = Prodi::whereNull('deleted_at')->find((int) $request->get('id_prodi'));
            $sheet->setCellValue('A'.$rowMeta, 'Prodi: '.($p ? ($p->kode ? $p->kode.' — '.$p->nama : $p->nama) : '-'));
            $rowMeta++;
        }
        if ($request->get('id_semester')) {
            $s = Semester::whereNull('deleted_at')->find((int) $request->get('id_semester'));
            $sheet->setCellValue('A'.$rowMeta, 'Semester: '.($s ? ($s->kode ? $s->kode.' — '.$s->nama : $s->nama) : '-'));
            $rowMeta++;
        }
        if ($request->get('status_pembayaran_acc')) {
            $sheet->setCellValue('A'.$rowMeta, 'Status pembayaran (ACC): '.$this->labelStatusPembayaranAcc((string) $request->get('status_pembayaran_acc')));
            $rowMeta++;
        }
        if ($request->boolean('lewat_jatuh_tempo')) {
            $sheet->setCellValue('A'.$rowMeta, 'Filter: lewat tanggal jatuh tempo');
            $rowMeta++;
        }

        $kredit = KeringananBiayaKreditService::kreditUntukTagihanIds($ids->all());

        $headerRow = $rowMeta + 1;
        $headers = [
            'No',
            'No. Tagihan',
            'NIM',
            'Nama Mahasiswa',
            'Program Studi',
            'Semester',
            'Tahap',
            'Total (Rp)',
            'Terbayar disetujui (Rp)',
            'Keringanan disetujui (Rp)',
            'Sisa (Rp)',
            'Status (ACC)',
            'Tanggal tagihan',
            'Jatuh tempo',
            'Keterangan',
        ];
        $sheet->fromArray([$headers], null, 'A'.$headerRow);

        $dataStart = $headerRow + 1;
        $row = $dataStart;
        $no = 1;
        $sumTotal = 0.0;
        $sumTerbayar = 0.0;
        $sumKredit = 0.0;

        foreach ($tagihans as $tagihan) {
            $totalDisetujui = (float) ($sums[$tagihan->id] ?? 0);
            $kreditBaris = (float) ($kredit[$tagihan->id] ?? 0);
            $totalTagihan = (float) $tagihan->total;
            $sisa = max(0.0, $totalTagihan - $totalDisetujui - $kreditBaris);
            $acc = $this->statusPembayaranAcc($tagihan, $totalDisetujui, $kreditBaris);

            $m = $tagihan->mahasiswa;
            $prodiNama = $m?->prodi ? (($m->prodi->kode ? $m->prodi->kode.' · ' : '').$m->prodi->nama) : '';
            $sem = $tagihan->semester;
            $semLabel = $sem ? (($sem->kode ? $sem->kode.' — ' : '').$sem->nama) : '';

            $sheet->setCellValue('A'.$row, $no);
            $sheet->setCellValue('B'.$row, $tagihan->no_tagihan ?? '');
            $sheet->setCellValue('C'.$row, $m?->nim ?? '');
            $sheet->setCellValue('D'.$row, $m?->nama ?? '');
            $sheet->setCellValue('E'.$row, $prodiNama);
            $sheet->setCellValue('F'.$row, $semLabel);
            $sheet->setCellValue('G'.$row, $tagihan->tahap ?? '');
            $sheet->setCellValue('H'.$row, $totalTagihan);
            $sheet->setCellValue('I'.$row, $totalDisetujui);
            $sheet->setCellValue('J'.$row, $kreditBaris);
            $sheet->setCellValue('K'.$row, $sisa);
            $sheet->setCellValue('L'.$row, $this->labelStatusPembayaranAcc($acc));
            $sheet->setCellValue('M'.$row, $tagihan->tanggal_tagihan ? Carbon::parse($tagihan->tanggal_tagihan)->format('Y-m-d') : '');
            $sheet->setCellValue('N'.$row, $tagihan->tanggal_jatuh_tempo ? Carbon::parse($tagihan->tanggal_jatuh_tempo)->format('Y-m-d') : '');
            $sheet->setCellValue('O'.$row, $tagihan->keterangan ?? '');

            $sumTotal += $totalTagihan;
            $sumTerbayar += $totalDisetujui;
            $sumKredit += $kreditBaris;
            $row++;
            $no++;
        }

        $totalRow = $row;
        $sheet->setCellValue('G'.$totalRow, 'TOTAL');
        $sheet->setCellValue('H'.$totalRow, $sumTotal);
        $sheet->setCellValue('I'.$totalRow, $sumTerbayar);
        $sheet->setCellValue('J'.$totalRow, $sumKredit);
        $sheet->setCellValue('K'.$totalRow, max(0, $sumTotal - $sumTerbayar - $sumKredit));

        $titleStyle = ['font' => ['bold' => true, 'size' => 14]];
        $sheet->getStyle('A1')->applyFromArray($titleStyle);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A'.$headerRow.':O'.$headerRow)->applyFromArray($headerStyle);

        if ($tagihans->isNotEmpty()) {
            $totalStyle = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E7E6E6'],
                ],
            ];
            $sheet->getStyle('G'.$totalRow.':K'.$totalRow)->applyFromArray($totalStyle);
        }

        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'daftar_tagihan_'.date('YmdHis').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);

        $query = $this->buildTagihanListQuery($request);

        $data = $query->orderBy('tanggal_tagihan', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $ids = $data->getCollection()->pluck('id');
        $sums = collect();
        if ($ids->isNotEmpty()) {
            $sums = Pembayaran::query()
                ->whereIn('id_tagihan', $ids->all())
                ->whereNull('deleted_at')
                ->whereNotNull('approved_at')
                ->groupBy('id_tagihan')
                ->selectRaw('id_tagihan, SUM(nominal) as total_disetujui')
                ->pluck('total_disetujui', 'id_tagihan');
        }

        $kredit = KeringananBiayaKreditService::kreditUntukTagihanIds($ids->all());

        $data->getCollection()->transform(function (Tagihan $tagihan) use ($sums, $kredit) {
            $totalDisetujui = (float) ($sums[$tagihan->id] ?? 0);
            $kreditBaris = (float) ($kredit[$tagihan->id] ?? 0);
            $totalTagihan = (float) $tagihan->total;
            $arr = $tagihan->toArray();
            $arr['total_pembayaran_disetujui'] = $totalDisetujui;
            $arr['kredit_keringanan'] = $kreditBaris;
            $arr['sisa_pembayaran_disetujui'] = max(0.0, $totalTagihan - $totalDisetujui - $kreditBaris);
            $arr['status_pembayaran_acc'] = $this->statusPembayaranAcc($tagihan, $totalDisetujui, $kreditBaris);

            return $arr;
        });

        return response()->json($data);
    }

    /**
     * Preview grup struktur biaya untuk proses generate tagihan.
     * Pengelompokan: id_periode, id_angkatan, id_prodi, id_komponen_biaya (tanpa tahap).
     */
    public function generatePreview(Request $request): JsonResponse
    {
        $search = trim((string) $request->get('search', ''));
        $query = StrukturBiaya::with(['periode', 'angkatan', 'prodi', 'komponenBiaya']);
        $this->applyStrukturBiayaProdiScope($query, $request->user());
        $rows = $query->get();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function (StrukturBiaya $row) use ($needle) {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row->periode->nama ?? ''),
                    (string) ($row->periode->kode ?? ''),
                    (string) ($row->angkatan->nama ?? ''),
                    (string) ($row->angkatan->kode ?? ''),
                    (string) ($row->prodi->nama ?? ''),
                    (string) ($row->prodi->kode ?? ''),
                    (string) ($row->komponenBiaya->nama ?? ''),
                    (string) ($row->komponenBiaya->kode ?? ''),
                ]));

                return str_contains($haystack, $needle);
            })->values();
        }

        $grouped = $rows
            ->groupBy(function (StrukturBiaya $row) {
                return implode('|', [
                    (string) $row->id_periode,
                    (string) $row->id_angkatan,
                    (string) ($row->id_prodi ?? 'null'),
                    (string) ($row->id_komponen_biaya ?? 'null'),
                ]);
            })
            ->map(function ($items) {
                /** @var StrukturBiaya $first */
                $first = $items->first();
                $availableTahap = $items->pluck('tahap')->filter()->unique()->sort()->values();

                return [
                    'id_periode' => $first->id_periode,
                    'id_angkatan' => $first->id_angkatan,
                    'id_prodi' => $first->id_prodi,
                    'id_komponen_biaya' => $first->id_komponen_biaya,
                    'periode' => $first->periode ? [
                        'id' => $first->periode->id,
                        'nama' => $first->periode->nama,
                        'kode' => $first->periode->kode,
                    ] : null,
                    'angkatan' => $first->angkatan ? [
                        'id' => $first->angkatan->id,
                        'nama' => $first->angkatan->nama,
                        'kode' => $first->angkatan->kode,
                    ] : null,
                    'prodi' => $first->prodi ? [
                        'id' => $first->prodi->id,
                        'nama' => $first->prodi->nama,
                        'kode' => $first->prodi->kode,
                    ] : null,
                    'komponen_biaya' => $first->komponenBiaya ? [
                        'id' => $first->komponenBiaya->id,
                        'nama' => $first->komponenBiaya->nama,
                        'kode' => $first->komponenBiaya->kode,
                    ] : null,
                    'total_baris_struktur' => $items->count(),
                    'available_tahap' => $availableTahap,
                    'min_tahap' => $availableTahap->first(),
                    'max_tahap' => $availableTahap->last(),
                ];
            })
            ->values()
            ->sortBy([
                ['id_periode', 'desc'],
                ['id_angkatan', 'desc'],
                ['id_prodi', 'asc'],
                ['id_komponen_biaya', 'asc'],
            ])
            ->values();

        return response()->json([
            'data' => $grouped,
            'total' => $grouped->count(),
        ]);
    }

    /**
     * Generate tagihan dari satu grup struktur biaya.
     * Tahap bisa semua atau satu tahap tertentu.
     */
    public function generateFromStrukturBiaya(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_periode' => ['required', 'integer', 'exists:semester,id'],
            'id_angkatan' => ['required', 'integer', 'exists:semester,id'],
            'id_prodi' => ['nullable', 'integer', 'exists:prodi,id'],
            'id_komponen_biaya' => ['nullable', 'integer', 'exists:komponen_biaya,id'],
            'opsi_tahap' => ['required', Rule::in(['all', 'specific'])],
            'tahap' => ['nullable', 'integer', 'min:1'],
            'tanggal_tagihan' => ['nullable', 'date'],
            'tanggal_jatuh_tempo' => ['nullable', 'date', 'after_or_equal:tanggal_tagihan'],
            'keterangan' => ['nullable', 'string'],
        ], [
            'id_periode.required' => 'Periode harus dipilih.',
            'id_angkatan.required' => 'Angkatan harus dipilih.',
            'opsi_tahap.required' => 'Opsi tahap harus dipilih.',
        ]);

        if ($validated['opsi_tahap'] === 'specific' && empty($validated['tahap'])) {
            return response()->json(['message' => 'Tahap wajib dipilih jika opsi tahap tertentu dipakai.'], 422);
        }

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowed = $user->getAllowedProdiIds();
            if ($allowed !== null) {
                if (($validated['id_prodi'] ?? null) === null) {
                    return response()->json([
                        'message' => 'Akun terbatas scope hanya dapat generate tagihan per program studi dalam lingkup akses (bukan grup semua prodi).',
                    ], 422);
                }
                if (! in_array((int) $validated['id_prodi'], $allowed, true)) {
                    return response()->json([
                        'message' => 'Program studi tidak termasuk lingkup akses Anda.',
                    ], 403);
                }
            }
        }

        $strukturQuery = StrukturBiaya::query()
            ->where('id_periode', $validated['id_periode'])
            ->where('id_angkatan', $validated['id_angkatan']);

        $this->applyStrukturBiayaProdiScope($strukturQuery, $user);

        if (array_key_exists('id_prodi', $validated)) {
            if ($validated['id_prodi'] === null) {
                $strukturQuery->whereNull('id_prodi');
            } else {
                $strukturQuery->where('id_prodi', $validated['id_prodi']);
            }
        }

        if (array_key_exists('id_komponen_biaya', $validated)) {
            if ($validated['id_komponen_biaya'] === null) {
                $strukturQuery->whereNull('id_komponen_biaya');
            } else {
                $strukturQuery->where('id_komponen_biaya', $validated['id_komponen_biaya']);
            }
        }

        if ($validated['opsi_tahap'] === 'specific') {
            $strukturQuery->where('tahap', $validated['tahap']);
        }

        $strukturRows = $strukturQuery->get();
        if ($strukturRows->isEmpty()) {
            return response()->json(['message' => 'Tidak ada struktur biaya untuk kombinasi filter ini.'], 422);
        }

        $mahasiswaQuery = Mahasiswa::query()
            ->with(['kategori_biaya_mahasiswa' => function ($q) {
                $q->where('status', 'active');
            }])
            ->where('id_semester_masuk', $validated['id_angkatan']);

        if ($user && $user->hasScopeRestriction()) {
            $allowedMhs = $user->getAllowedProdiIds();
            if ($allowedMhs !== null && $allowedMhs !== []) {
                $mahasiswaQuery->whereIn('id_prodi', $allowedMhs);
            }
        }

        if (array_key_exists('id_prodi', $validated) && $validated['id_prodi'] !== null) {
            $mahasiswaQuery->where('id_prodi', $validated['id_prodi']);
        }

        $mahasiswaList = $mahasiswaQuery->get();
        if ($mahasiswaList->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada mahasiswa yang cocok untuk kombinasi filter ini.',
                'created_count' => 0,
                'skipped_count' => 0,
            ]);
        }

        $keteranganBase = $validated['keterangan'] ?? 'Generate otomatis dari struktur biaya';

        // Tanggal berasal dari periode yang ditagih atau dari isian operator — tidak lagi dari
        // Carbon::now(), yang membuat tagihan periode lama bertanggal hari generate dijalankan.
        $jadwal = JadwalTagihanTahap::resolve(
            Semester::find($validated['id_periode']),
            $validated['tanggal_tagihan'] ?? null,
            $validated['tanggal_jatuh_tempo'] ?? null,
        );

        if (! $jadwal) {
            return response()->json([
                'message' => JadwalTagihanTahap::pesanTanggalTidakDiketahui(),
            ], 422);
        }

        // Dua pramuat untuk memangkas query berulang di dalam loop: nomor dokumen dikunci
        // sekali lalu dilanjutkan di memori, dan tagihan yang sudah ada diambil dalam satu query.
        $seriNomor = SeriNomorDokumen::tagihan();
        $tagihanTerpasang = PenerapanTagihanTahap::pramuat(
            (int) $validated['id_periode'],
            $mahasiswaList->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $detailSkipped = [];
        $detailDitambah = [];

        DB::beginTransaction();
        try {
            foreach ($mahasiswaList as $mahasiswa) {
                $activeKategoriId = optional($mahasiswa->kategori_biaya_mahasiswa->first())->id_kategori_biaya;
                $applicable = $strukturRows->filter(function (StrukturBiaya $row) use ($activeKategoriId) {
                    if ($row->id_kategori_biaya === null) {
                        return true;
                    }

                    return (int) $row->id_kategori_biaya === (int) $activeKategoriId;
                });

                if ($applicable->isEmpty()) {
                    $skippedCount++;
                    $detailSkipped[] = [
                        'id_mahasiswa' => $mahasiswa->id,
                        'nim' => $mahasiswa->nim,
                        'nama' => $mahasiswa->nama,
                        'reason' => 'Tidak ada struktur biaya yang cocok dengan kategori biaya mahasiswa',
                    ];

                    continue;
                }

                $allTahap = $applicable
                    ->pluck('tahap')
                    ->filter()
                    ->map(fn ($t) => (int) $t)
                    ->unique()
                    ->sort()
                    ->values();

                if ($allTahap->isEmpty()) {
                    $skippedCount++;
                    $detailSkipped[] = [
                        'id_mahasiswa' => $mahasiswa->id,
                        'nim' => $mahasiswa->nim,
                        'nama' => $mahasiswa->nama,
                        'reason' => 'Tahap struktur biaya tidak ditemukan',
                    ];

                    continue;
                }

                $targetTahapList = $validated['opsi_tahap'] === 'specific'
                    ? collect([(int) $validated['tahap']])->filter(fn ($t) => $allTahap->contains($t))->values()
                    : $allTahap;

                if ($targetTahapList->isEmpty()) {
                    $skippedCount++;
                    $detailSkipped[] = [
                        'id_mahasiswa' => $mahasiswa->id,
                        'nim' => $mahasiswa->nim,
                        'nama' => $mahasiswa->nama,
                        'reason' => 'Tahap yang dipilih tidak tersedia untuk mahasiswa ini',
                    ];

                    continue;
                }

                foreach ($targetTahapList as $tahap) {
                    $rincian = $applicable
                        ->where('tahap', (int) $tahap)
                        ->groupBy('id_komponen_biaya')
                        ->map(function ($rows, $komponenId) {
                            return [
                                'id_komponen_biaya' => $komponenId,
                                'nominal' => (float) $rows->sum('nominal'),
                            ];
                        })
                        ->values()
                        ->filter(function ($row) {
                            return ! empty($row['id_komponen_biaya']) && $row['nominal'] > 0;
                        })
                        ->values();

                    if ($rincian->isEmpty()) {
                        $skippedCount++;
                        $detailSkipped[] = [
                            'id_mahasiswa' => $mahasiswa->id,
                            'nim' => $mahasiswa->nim,
                            'nama' => $mahasiswa->nama,
                            'reason' => "Rincian kosong pada tahap {$tahap}",
                        ];

                        continue;
                    }

                    // Komponen yang belum tercatat digabungkan ke tagihan tahap ini; dulu
                    // seluruh generate dilewati begitu tahapnya sudah punya tagihan, sehingga
                    // komponen biaya kedua tidak pernah tertagih.
                    $penerapan = PenerapanTagihanTahap::terapkan(
                        (int) $mahasiswa->id,
                        (int) $validated['id_periode'],
                        (int) $tahap,
                        $rincian,
                        $jadwal->untukTahap((int) $tahap),
                        $keteranganBase,
                        fn () => $seriNomor->berikutnya(),
                        $tagihanTerpasang->get(PenerapanTagihanTahap::kunciPramuat((int) $mahasiswa->id, (int) $tahap)),
                        sudahDipramuat: true,
                    );

                    if ($penerapan['hasil'] === PenerapanTagihanTahap::DIBUAT) {
                        $tagihanTerpasang->put(
                            PenerapanTagihanTahap::kunciPramuat((int) $mahasiswa->id, (int) $tahap),
                            $penerapan['tagihan'],
                        );
                    }

                    if ($penerapan['hasil'] === PenerapanTagihanTahap::DIBUAT) {
                        $createdCount++;
                    } elseif ($penerapan['hasil'] === PenerapanTagihanTahap::DITAMBAH) {
                        $updatedCount++;
                        $detailDitambah[] = [
                            'id_mahasiswa' => $mahasiswa->id,
                            'nim' => $mahasiswa->nim,
                            'nama' => $mahasiswa->nama,
                            'no_tagihan' => $penerapan['tagihan']->no_tagihan,
                            'komponen_baru' => $penerapan['komponen_baru'],
                        ];
                    } else {
                        $skippedCount++;
                        $detailSkipped[] = [
                            'id_mahasiswa' => $mahasiswa->id,
                            'nim' => $mahasiswa->nim,
                            'nama' => $mahasiswa->nama,
                            'reason' => $penerapan['alasan'],
                        ];
                    }
                }
            }

            DB::commit();

            $pesan = "Generate selesai. Berhasil: {$createdCount}";
            if ($updatedCount > 0) {
                $pesan .= ", Ditambahkan ke tagihan yang sudah ada: {$updatedCount}";
            }
            $pesan .= ", Dilewati: {$skippedCount}.";

            return response()->json([
                'message' => $pesan,
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
                'skipped_detail' => $detailSkipped,
                'updated_detail' => $detailDitambah,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan saat generate tagihan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_mahasiswa' => ['required', 'integer', 'exists:mahasiswa,id'],
            'id_semester' => ['required', 'integer', 'exists:semester,id'],
            'total' => ['required', 'numeric', 'min:0'],
            'tahap' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(['unpaid', 'paid', 'expired'])],
            'tanggal_tagihan' => ['nullable', 'date'],
            'tanggal_jatuh_tempo' => ['nullable', 'date', 'after_or_equal:tanggal_tagihan'],
            'tanggal_pembayaran' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.id_komponen_biaya' => ['required', 'integer', 'exists:komponen_biaya,id'],
            'rincian.*.nominal' => ['required', 'numeric', 'min:0'],
        ], [
            'id_mahasiswa.required' => 'Mahasiswa harus dipilih.',
            'id_mahasiswa.exists' => 'Mahasiswa tidak valid.',
            'id_semester.required' => 'Semester harus dipilih.',
            'id_semester.exists' => 'Semester tidak valid.',
            'total.required' => 'Total harus diisi.',
            'total.numeric' => 'Total harus berupa angka.',
            'total.min' => 'Total tidak boleh negatif.',
            'rincian.required' => 'Minimal harus ada satu rincian tagihan.',
            'rincian.min' => 'Minimal harus ada satu rincian tagihan.',
            'rincian.*.id_komponen_biaya.required' => 'Komponen biaya harus dipilih.',
            'rincian.*.id_komponen_biaya.exists' => 'Komponen biaya tidak valid.',
            'rincian.*.nominal.required' => 'Nominal harus diisi.',
            'rincian.*.nominal.numeric' => 'Nominal harus berupa angka.',
            'rincian.*.nominal.min' => 'Nominal tidak boleh negatif.',
        ]);

        $tahap = isset($validated['tahap']) ? (int) $validated['tahap'] : null;

        if ($this->tagihanKembarExists((int) $validated['id_mahasiswa'], (int) $validated['id_semester'], $tahap)) {
            return response()->json([
                'message' => $this->pesanTagihanKembar($tahap),
            ], 422);
        }

        // Validate that total matches sum of rincian
        $sumRincian = array_sum(array_column($validated['rincian'], 'nominal'));
        if (abs($sumRincian - $validated['total']) > 0.01) {
            return response()->json([
                'message' => 'Total tagihan harus sama dengan jumlah rincian.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generate nomor tagihan otomatis
            $noTagihan = PenomoranDokumen::tagihan();

            $tagihan = Tagihan::create([
                'id_mahasiswa' => $validated['id_mahasiswa'],
                'id_semester' => $validated['id_semester'],
                'no_tagihan' => $noTagihan,
                'total' => $validated['total'],
                'tahap' => $tahap,
                'status' => $validated['status'] ?? 'unpaid',
                'tanggal_tagihan' => $validated['tanggal_tagihan'] ?? now(),
                'tanggal_jatuh_tempo' => $validated['tanggal_jatuh_tempo'] ?? null,
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'] ?? null,
                'keterangan' => $validated['keterangan'] ?? null,
            ]);

            // Create rincian
            foreach ($validated['rincian'] as $rinci) {
                // Check for unique constraint in rincian
                $rinciExists = TagihanRinci::where('id_tagihan', $tagihan->id)
                    ->where('id_komponen_biaya', $rinci['id_komponen_biaya'])
                    ->exists();

                if ($rinciExists) {
                    DB::rollBack();

                    return response()->json([
                        'message' => 'Komponen biaya duplikat dalam rincian tagihan.',
                    ], 422);
                }

                TagihanRinci::create([
                    'id_tagihan' => $tagihan->id,
                    'id_komponen_biaya' => $rinci['id_komponen_biaya'],
                    'nominal' => $rinci['nominal'],
                ]);
            }

            DB::commit();
            $tagihan->load(['mahasiswa.prodi', 'semester', 'tagihanRinci.komponenBiaya']);

            return response()->json($tagihan, 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan tagihan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, Tagihan $tagihan): JsonResponse
    {
        $this->assertTagihanInUserScope($request, $tagihan);
        $tagihan->load([
            'mahasiswa.prodi',
            'semester',
            'tagihanRinci.komponenBiaya',
            'pembayaran' => function ($q) {
                $q->whereNotNull('approved_at')
                    ->whereNull('deleted_at')
                    ->orderByDesc('tanggal_pembayaran')
                    ->orderByDesc('id');
            },
        ]);

        $totalDisetujui = (float) Pembayaran::approvedQueryForTagihan($tagihan->id)->sum('nominal');
        $kreditBaris = KeringananBiayaKreditService::kreditUntukTagihan($tagihan);
        $totalTagihan = (float) $tagihan->total;

        $payload = $tagihan->toArray();
        $payload['total_pembayaran_disetujui'] = $totalDisetujui;
        $payload['kredit_keringanan'] = $kreditBaris;
        $payload['sisa_pembayaran_disetujui'] = max(0.0, $totalTagihan - $totalDisetujui - $kreditBaris);
        $payload['status_pembayaran_acc'] = $this->statusPembayaranAcc($tagihan, $totalDisetujui, $kreditBaris);

        return response()->json($payload);
    }

    public function update(Request $request, Tagihan $tagihan): JsonResponse
    {
        $this->assertTagihanInUserScope($request, $tagihan);
        $validated = $request->validate([
            'id_mahasiswa' => ['sometimes', 'required', 'integer', 'exists:mahasiswa,id'],
            'id_semester' => ['sometimes', 'required', 'integer', 'exists:semester,id'],
            'total' => ['sometimes', 'required', 'numeric', 'min:0'],
            'tahap' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(['unpaid', 'paid', 'expired'])],
            'tanggal_tagihan' => ['nullable', 'date'],
            'tanggal_jatuh_tempo' => ['nullable', 'date', 'after_or_equal:tanggal_tagihan'],
            'tanggal_pembayaran' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string'],
            'rincian' => ['sometimes', 'required', 'array', 'min:1'],
            'rincian.*.id_komponen_biaya' => ['required', 'integer', 'exists:komponen_biaya,id'],
            'rincian.*.nominal' => ['required', 'numeric', 'min:0'],
        ], [
            'id_mahasiswa.required' => 'Mahasiswa harus dipilih.',
            'id_mahasiswa.exists' => 'Mahasiswa tidak valid.',
            'id_semester.required' => 'Semester harus dipilih.',
            'id_semester.exists' => 'Semester tidak valid.',
            'total.required' => 'Total harus diisi.',
            'total.numeric' => 'Total harus berupa angka.',
            'total.min' => 'Total tidak boleh negatif.',
            'rincian.required' => 'Minimal harus ada satu rincian tagihan.',
            'rincian.min' => 'Minimal harus ada satu rincian tagihan.',
            'rincian.*.id_komponen_biaya.required' => 'Komponen biaya harus dipilih.',
            'rincian.*.id_komponen_biaya.exists' => 'Komponen biaya tidak valid.',
            'rincian.*.nominal.required' => 'Nominal harus diisi.',
            'rincian.*.nominal.numeric' => 'Nominal harus berupa angka.',
            'rincian.*.nominal.min' => 'Nominal tidak boleh negatif.',
        ]);

        // Cek kembar selalu mengecualikan baris ini sendiri, jadi menyimpan ulang tagihan tanpa
        // mengubah apa pun tidak akan pernah ditolak — itu yang dulu mengunci tagihan multi-tahap.
        $mahasiswaId = (int) ($validated['id_mahasiswa'] ?? $tagihan->id_mahasiswa);
        $semesterId = (int) ($validated['id_semester'] ?? $tagihan->id_semester);
        $tahap = array_key_exists('tahap', $validated)
            ? ($validated['tahap'] !== null ? (int) $validated['tahap'] : null)
            : ($tagihan->tahap !== null ? (int) $tagihan->tahap : null);

        if ($this->tagihanKembarExists($mahasiswaId, $semesterId, $tahap, (int) $tagihan->id)) {
            return response()->json([
                'message' => $this->pesanTagihanKembar($tahap),
            ], 422);
        }

        // Validate total if rincian is provided
        if (isset($validated['rincian'])) {
            $sumRincian = array_sum(array_column($validated['rincian'], 'nominal'));
            $total = $validated['total'] ?? $tagihan->total;
            if (abs($sumRincian - $total) > 0.01) {
                return response()->json([
                    'message' => 'Total tagihan harus sama dengan jumlah rincian.',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Update tagihan
            $tagihan->update([
                'id_mahasiswa' => $mahasiswaId,
                'id_semester' => $semesterId,
                'total' => $validated['total'] ?? $tagihan->total,
                'tahap' => $tahap,
                'status' => $validated['status'] ?? $tagihan->status,
                'tanggal_tagihan' => $validated['tanggal_tagihan'] ?? $tagihan->tanggal_tagihan,
                'tanggal_jatuh_tempo' => $validated['tanggal_jatuh_tempo'] ?? $tagihan->tanggal_jatuh_tempo,
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'] ?? $tagihan->tanggal_pembayaran,
                'keterangan' => $validated['keterangan'] ?? $tagihan->keterangan,
            ]);

            // Update rincian if provided
            if (isset($validated['rincian'])) {
                // Delete existing rincian
                TagihanRinci::where('id_tagihan', $tagihan->id)->delete();

                // Create new rincian
                foreach ($validated['rincian'] as $rinci) {
                    // Check for unique constraint in rincian
                    $rinciExists = TagihanRinci::where('id_tagihan', $tagihan->id)
                        ->where('id_komponen_biaya', $rinci['id_komponen_biaya'])
                        ->exists();

                    if ($rinciExists) {
                        DB::rollBack();

                        return response()->json([
                            'message' => 'Komponen biaya duplikat dalam rincian tagihan.',
                        ], 422);
                    }

                    TagihanRinci::create([
                        'id_tagihan' => $tagihan->id,
                        'id_komponen_biaya' => $rinci['id_komponen_biaya'],
                        'nominal' => $rinci['nominal'],
                    ]);
                }
            }

            DB::commit();
            $tagihan->load(['mahasiswa.prodi', 'semester', 'tagihanRinci.komponenBiaya']);

            return response()->json($tagihan);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui tagihan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, Tagihan $tagihan): JsonResponse
    {
        $this->assertTagihanInUserScope($request, $tagihan);
        $tagihan->delete();

        return response()->json(['message' => 'Tagihan dihapus']);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        // Sheet 1: Data Tagihan
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Tagihan');

        $headers1 = [
            'NIM*',
            'Kode Semester*',
            'Status (unpaid/paid/expired)',
            'Tanggal Tagihan (YYYY-MM-DD)',
            'Tanggal Jatuh Tempo (YYYY-MM-DD)',
            'Tanggal Pembayaran (YYYY-MM-DD)',
            'Keterangan',
        ];

        $sheet1->fromArray([$headers1], null, 'A1');

        // Set column widths
        $sheet1->getColumnDimension('A')->setWidth(15);
        $sheet1->getColumnDimension('B')->setWidth(20);
        $sheet1->getColumnDimension('C')->setWidth(25);
        $sheet1->getColumnDimension('D')->setWidth(25);
        $sheet1->getColumnDimension('E')->setWidth(25);
        $sheet1->getColumnDimension('F')->setWidth(25);
        $sheet1->getColumnDimension('G')->setWidth(40);

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet1->getStyle('A1:G1')->applyFromArray($headerStyle);

        // Add example row
        $exampleRow1 = [
            '2024001',
            '20241',
            'unpaid',
            date('Y-m-d'),
            date('Y-m-d', strtotime('+30 days')),
            '',
            'Tagihan semester ganjil',
        ];
        $sheet1->fromArray([$exampleRow1], null, 'A2');

        // Sheet 2: Data Rincian Tagihan
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Rincian Tagihan');

        $headers2 = [
            'NIM*',
            'Kode Semester*',
            'Nama Komponen Biaya*',
            'Nominal*',
        ];

        $sheet2->fromArray([$headers2], null, 'A1');

        // Set column widths
        $sheet2->getColumnDimension('A')->setWidth(15);
        $sheet2->getColumnDimension('B')->setWidth(20);
        $sheet2->getColumnDimension('C')->setWidth(25);
        $sheet2->getColumnDimension('D')->setWidth(20);

        // Style header
        $sheet2->getStyle('A1:D1')->applyFromArray($headerStyle);

        // Add example rows (multiple rincian for one tagihan)
        $exampleRows2 = [
            ['2024001', '20241', 'SPP', '500000'],
            ['2024001', '20241', 'UKT', '2000000'],
        ];
        $sheet2->fromArray($exampleRows2, null, 'A2');

        $filename = 'template_import_tagihan_'.date('YmdHis').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());

        // Read Sheet 1: Tagihan
        $sheet1 = $spreadsheet->getSheet(0);
        $rows1 = $sheet1->toArray();

        // Read Sheet 2: Rincian
        $sheet2 = $spreadsheet->getSheet(1);
        $rows2 = $sheet2->toArray();

        if (count($rows1) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Sheet Tagihan kosong atau tidak valid.',
            ], 400);
        }

        if (count($rows2) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Sheet Rincian Tagihan kosong atau tidak valid.',
            ], 400);
        }

        // Remove header rows
        array_shift($rows1);
        array_shift($rows2);

        $errors = [];
        $successCount = 0;
        $skipCount = 0;

        // Group rincian by NIM and Semester
        $rincianMap = [];
        foreach ($rows2 as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2;

            if (! is_array($row)) {
                continue;
            }

            // Pastikan indeks kolom A–D ada (PhpSpreadsheet kadang memotong trailing kosong)
            $row = array_values($row);
            $row = array_pad($row, 4, null);

            // Skip empty rows
            if ($this->spreadsheetDataRowIsEmpty($row)) {
                continue;
            }

            $nim = $this->spreadsheetCellToString($row[0] ?? null);
            $kodeSemester = $this->spreadsheetCellToString($row[1] ?? null);
            $namaKomponenBiaya = $this->spreadsheetCellToString($row[2] ?? null);
            $nominalRaw = $row[3] ?? null;

            $missing = [];
            if ($nim === '') {
                $missing[] = 'NIM';
            }
            if ($kodeSemester === '') {
                $missing[] = 'Kode Semester';
            }
            if ($namaKomponenBiaya === '') {
                $missing[] = 'Nama Komponen Biaya';
            }
            if (! $this->spreadsheetCellIsFilled($nominalRaw)) {
                $missing[] = 'Nominal';
            }

            if ($missing !== []) {
                $errors[] = "Sheet Rincian Baris {$rowNumber}: Wajib diisi: ".implode(', ', $missing).'.';

                continue;
            }

            $key = "{$nim}|{$kodeSemester}";
            if (! isset($rincianMap[$key])) {
                $rincianMap[$key] = [];
            }

            $rincianMap[$key][] = [
                'nama_komponen_biaya' => $namaKomponenBiaya,
                'nominal' => $nominalRaw,
            ];
        }

        foreach ($rows1 as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2;

            if (! is_array($row)) {
                continue;
            }

            $row = array_values($row);
            $row = array_pad($row, 7, null);

            // Skip empty rows
            if ($this->spreadsheetDataRowIsEmpty($row)) {
                continue;
            }

            $nim = $this->spreadsheetCellToString($row[0] ?? null);
            $kodeSemester = $this->spreadsheetCellToString($row[1] ?? null);
            $status = $this->spreadsheetCellToString($row[2] ?? null) ?: 'unpaid';
            $tanggalTagihan = $this->spreadsheetCellToString($row[3] ?? null);
            $tanggalJatuhTempoRaw = $row[4] ?? null;
            $tanggalJatuhTempo = $this->spreadsheetCellIsFilled($tanggalJatuhTempoRaw)
                ? $this->spreadsheetCellToString($tanggalJatuhTempoRaw)
                : null;
            $tanggalPembayaranRaw = $row[5] ?? null;
            $tanggalPembayaran = $this->spreadsheetCellIsFilled($tanggalPembayaranRaw)
                ? $this->spreadsheetCellToString($tanggalPembayaranRaw)
                : null;
            $keteranganRaw = $row[6] ?? null;
            $keterangan = $this->spreadsheetCellIsFilled($keteranganRaw)
                ? $this->spreadsheetCellToString($keteranganRaw)
                : null;

            // Validate required fields
            if ($nim === '') {
                $errors[] = "Baris {$rowNumber}: NIM wajib diisi.";
                $skipCount++;

                continue;
            }

            if ($kodeSemester === '') {
                $errors[] = "Baris {$rowNumber}: Kode Semester wajib diisi.";
                $skipCount++;

                continue;
            }

            // Find Mahasiswa by NIM
            $mahasiswa = Mahasiswa::where('nim', $nim)->first();
            if (! $mahasiswa) {
                $errors[] = "Baris {$rowNumber}: Mahasiswa dengan NIM '{$nim}' tidak ditemukan.";
                $skipCount++;

                continue;
            }

            // Find Semester by kode
            $semester = Semester::where('kode', $kodeSemester)->first();
            if (! $semester) {
                $errors[] = "Baris {$rowNumber}: Semester dengan kode '{$kodeSemester}' tidak ditemukan.";
                $skipCount++;

                continue;
            }

            // Validate status
            if (! in_array($status, ['unpaid', 'paid', 'expired'])) {
                $status = 'unpaid';
            }

            // Validate tanggal_tagihan
            if ($tanggalTagihan === '') {
                $tanggalTagihan = date('Y-m-d');
            } else {
                $tanggalTagihan = date('Y-m-d', strtotime($tanggalTagihan));
                if (! $tanggalTagihan) {
                    $errors[] = "Baris {$rowNumber}: Format tanggal tagihan tidak valid.";
                    $skipCount++;

                    continue;
                }
            }

            // Validate tanggal_jatuh_tempo
            if ($tanggalJatuhTempo) {
                $tanggalJatuhTempo = date('Y-m-d', strtotime($tanggalJatuhTempo));
                if (! $tanggalJatuhTempo || $tanggalJatuhTempo < $tanggalTagihan) {
                    $errors[] = "Baris {$rowNumber}: Tanggal jatuh tempo harus setelah atau sama dengan tanggal tagihan.";
                    $skipCount++;

                    continue;
                }
            }

            // Validate tanggal_pembayaran
            if ($tanggalPembayaran) {
                $tanggalPembayaran = date('Y-m-d', strtotime($tanggalPembayaran));
                if (! $tanggalPembayaran) {
                    $errors[] = "Baris {$rowNumber}: Format tanggal pembayaran tidak valid.";
                    $skipCount++;

                    continue;
                }
            }

            // Check for existing tagihan
            $exists = Tagihan::where('id_mahasiswa', $mahasiswa->id)
                ->where('id_semester', $semester->id)
                ->exists();

            if ($exists) {
                $errors[] = "Baris {$rowNumber}: Tagihan untuk mahasiswa NIM '{$nim}' dan semester '{$kodeSemester}' sudah ada.";
                $skipCount++;

                continue;
            }

            // Get rincian for this tagihan
            $key = "{$nim}|{$kodeSemester}";
            $rincianData = $rincianMap[$key] ?? [];

            if (empty($rincianData)) {
                $errors[] = "Baris {$rowNumber}: Tidak ada rincian tagihan untuk NIM '{$nim}' dan semester '{$kodeSemester}'.";
                $skipCount++;

                continue;
            }

            // Validate and convert rincian
            $rincian = [];
            $total = 0;
            $komponenBiayaIds = [];

            foreach ($rincianData as $rinci) {
                $komponenBiaya = KomponenBiaya::where('nama', $rinci['nama_komponen_biaya'])->first();
                if (! $komponenBiaya) {
                    $errors[] = "Baris {$rowNumber}: Komponen biaya dengan nama '{$rinci['nama_komponen_biaya']}' tidak ditemukan.";
                    $skipCount++;

                    continue 2; // Skip this tagihan
                }

                $nominal = (float) $rinci['nominal'];

                /*
                if ($nominal <= 0) {
                    $errors[] = "Baris {$rowNumber}: Nominal komponen biaya '{$rinci['nama_komponen_biaya']}' harus lebih dari 0.";
                    $skipCount++;

                    continue 2; // Skip this tagihan
                }
                */

                // Check for duplicate komponen biaya
                if (in_array($komponenBiaya->id, $komponenBiayaIds)) {
                    $errors[] = "Baris {$rowNumber}: Komponen biaya '{$rinci['nama_komponen_biaya']}' duplikat dalam rincian.";
                    $skipCount++;

                    continue 2; // Skip this tagihan
                }

                $komponenBiayaIds[] = $komponenBiaya->id;
                $rincian[] = [
                    'id_komponen_biaya' => $komponenBiaya->id,
                    'nominal' => $nominal,
                ];
                $total += $nominal;
            }

            if (empty($rincian)) {
                $skipCount++;

                continue;
            }

            try {
                DB::transaction(function () use (
                    $mahasiswa,
                    $semester,
                    $status,
                    $tanggalTagihan,
                    $tanggalJatuhTempo,
                    $tanggalPembayaran,
                    $keterangan,
                    $rincian,
                    $total
                ) {
                    $noTagihan = PenomoranDokumen::tagihan();

                    $tagihan = Tagihan::create([
                        'id_mahasiswa' => $mahasiswa->id,
                        'id_semester' => $semester->id,
                        'no_tagihan' => $noTagihan,
                        'total' => $total,
                        'status' => $status,
                        'tanggal_tagihan' => $tanggalTagihan,
                        'tanggal_jatuh_tempo' => $tanggalJatuhTempo,
                        'tanggal_pembayaran' => $tanggalPembayaran,
                        'keterangan' => $keterangan,
                    ]);

                    foreach ($rincian as $rinci) {
                        TagihanRinci::create([
                            'id_tagihan' => $tagihan->id,
                            'id_komponen_biaya' => $rinci['id_komponen_biaya'],
                            'nominal' => $rinci['nominal'],
                        ]);
                    }
                });

                $successCount++;
            } catch (\Throwable $e) {
                $errors[] = "Baris {$rowNumber}: ".$e->getMessage();
                $skipCount++;
            }
        }

        $message = "Import selesai. Berhasil: {$successCount}, Dilewati: {$skipCount}";
        if (! empty($errors)) {
            $message .= "\n\nError:\n".implode("\n", array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $message .= "\n... dan ".(count($errors) - 10).' error lainnya.';
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'success_count' => $successCount,
            'skip_count' => $skipCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Get tagihan untuk mahasiswa yang sedang login
     */
    public function getTagihanMahasiswa(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ambil data mahasiswa dari user
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();

        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan',
            ], 404);
        }

        // Hanya tagihan yang sudah masuk masa berlaku: tanggal_tagihan <= hari ini (per tanggal kalender)
        $tagihanList = Tagihan::with([
            'semester',
            'tagihanRinci.komponenBiaya',
        ])
            ->where('id_mahasiswa', $mahasiswa->id)
            ->whereNull('deleted_at')
            ->whereNotNull('tanggal_tagihan')
            ->whereDate('tanggal_tagihan', '<=', Carbon::today())
            ->orderBy('tanggal_tagihan', 'desc')
            ->get();

        // Hitung total pembayaran untuk setiap tagihan
        $kreditKeringanan = KeringananBiayaKreditService::kreditUntukTagihanIds($tagihanList->pluck('id')->all());

        $tagihanWithPembayaran = $tagihanList->map(function ($tagihan) use ($kreditKeringanan) {
            $totalPembayaranDisetujui = Pembayaran::approvedQueryForTagihan($tagihan->id)->sum('nominal');
            $totalSemuaPembayaran = Pembayaran::where('id_tagihan', $tagihan->id)
                ->whereNull('deleted_at')
                ->sum('nominal');
            $kredit = (float) ($kreditKeringanan[$tagihan->id] ?? 0);
            $sisaTagihan = (float) $tagihan->total - (float) $totalPembayaranDisetujui - $kredit;
            // `sisa_dapat_dibayar` sengaja ikut memperhitungkan pembayaran yang masih menunggu
            // verifikasi, supaya mahasiswa tidak mengunggah bukti dua kali untuk nominal yang sama.
            $sisaDapatDibayar = max(0.0, (float) $tagihan->total - (float) $totalSemuaPembayaran - $kredit);

            return [
                'id' => $tagihan->id,
                'no_tagihan' => $tagihan->no_tagihan,
                'total' => $tagihan->total,
                'status' => $tagihan->status,
                'tanggal_tagihan' => $tagihan->tanggal_tagihan ? $tagihan->tanggal_tagihan->format('Y-m-d') : null,
                'tanggal_jatuh_tempo' => $tagihan->tanggal_jatuh_tempo ? $tagihan->tanggal_jatuh_tempo->format('Y-m-d') : null,
                'tanggal_pembayaran' => $tagihan->tanggal_pembayaran ? $tagihan->tanggal_pembayaran->format('Y-m-d') : null,
                'keterangan' => $tagihan->keterangan,
                'semester' => [
                    'id' => $tagihan->semester->id ?? null,
                    'kode' => $tagihan->semester->kode ?? null,
                    'nama' => $tagihan->semester->nama ?? null,
                ],
                'tagihan_rinci' => $tagihan->tagihanRinci->map(function ($rinci) {
                    return [
                        'id' => $rinci->id,
                        'komponen_biaya' => [
                            'id' => $rinci->komponenBiaya->id ?? null,
                            'kode' => $rinci->komponenBiaya->kode ?? null,
                            'nama' => $rinci->komponenBiaya->nama ?? null,
                        ],
                        'nominal' => $rinci->nominal,
                    ];
                })->toArray(),
                'total_pembayaran' => $totalPembayaranDisetujui,
                'kredit_keringanan' => $kredit,
                'sisa_tagihan' => $sisaTagihan,
                'sisa_dapat_dibayar' => $sisaDapatDibayar,
            ];
        });

        return response()->json([
            'mahasiswa' => [
                'id' => $mahasiswa->id,
                'nim' => $mahasiswa->nim,
                'nama' => $mahasiswa->nama,
            ],
            'data' => $tagihanWithPembayaran,
        ]);
    }

    /**
     * Cek apakah mahasiswa login memenuhi persentase pembayaran untuk aturan akses keuangan (mis. kode krs).
     */
    public function cekAksesKeuanganMahasiswa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'id_semester' => ['nullable', 'integer', 'exists:semester,id'],
        ]);

        $kode = $validated['kode'] ?? 'krs';
        $idSemester = isset($validated['id_semester']) ? (int) $validated['id_semester'] : null;

        $user = $request->user();
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();

        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan',
            ], 404);
        }

        $result = KeuanganAksesMahasiswaService::canAccessByKode($mahasiswa->id, $kode, $idSemester);

        return response()->json([
            'allowed' => $result['allowed'],
            'allowed_via_keringanan_biaya' => $result['allowed_via_keringanan_biaya'],
            'kode_akses' => $kode,
            'persentase_pembayaran' => $result['persentase_pembayaran'],
            'persentase_minimum_required' => $result['persentase_minimum_required'],
            'total_tagihan_berlaku' => $result['total_tagihan_berlaku'],
            'total_terbayar_disetujui' => $result['total_terbayar_disetujui'],
            'jumlah_tagihan_berlaku' => $result['jumlah_tagihan_berlaku'],
            'aturan' => $result['aturan'],
        ]);
    }

    /**
     * Get tagihan mahasiswa dikelompokkan per semester akademik (scope prodi) - untuk halaman detail mahasiswa prodi.
     * Setiap item tagihan dilengkapi total pembayaran dan sisa.
     */
    public function getTagihanBySemesterForMahasiswaProdi(Request $request, $idMahasiswa): JsonResponse
    {
        $user = $request->user();
        $prodiScopeIds = $user && $user->hasProdiScope() ? $user->getProdiScopeIds() : [];
        if (empty($prodiScopeIds)) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $mahasiswa = Mahasiswa::with(['prodi', 'semester_masuk'])->find($idMahasiswa);
        if (! $mahasiswa || ! in_array($mahasiswa->id_prodi, $prodiScopeIds)) {
            return response()->json(['message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        $tagihanList = Tagihan::with(['semester'])
            ->where('id_mahasiswa', $idMahasiswa)
            ->whereNull('deleted_at')
            ->orderBy('tanggal_tagihan', 'desc')
            ->get();

        $tagihanIds = $tagihanList->pluck('id')->toArray();
        $totalPembayaranByTagihan = [];
        if (! empty($tagihanIds)) {
            $sums = Pembayaran::whereIn('id_tagihan', $tagihanIds)
                ->whereNull('deleted_at')
                ->whereNotNull('approved_at')
                ->selectRaw('id_tagihan, SUM(nominal) as total')
                ->groupBy('id_tagihan')
                ->pluck('total', 'id_tagihan');
            $totalPembayaranByTagihan = $sums->toArray();
        }

        $bySemester = [];
        foreach ($tagihanList as $tagihan) {
            $semester = $tagihan->semester;
            if (! $semester) {
                continue;
            }
            $semesterId = $semester->id;
            $totalPembayaran = (float) ($totalPembayaranByTagihan[$tagihan->id] ?? 0);
            $totalTagihan = (float) $tagihan->total;
            $sisa = $totalTagihan - $totalPembayaran;

            if (! isset($bySemester[$semesterId])) {
                $bySemester[$semesterId] = [
                    'semester' => [
                        'id' => $semester->id,
                        'kode' => $semester->kode,
                        'nama' => $semester->nama,
                    ],
                    'tagihan_list' => [],
                    'total_tagihan_semester' => 0,
                    'total_pembayaran_semester' => 0,
                ];
            }

            $bySemester[$semesterId]['total_tagihan_semester'] += $totalTagihan;
            $bySemester[$semesterId]['total_pembayaran_semester'] += $totalPembayaran;
            $bySemester[$semesterId]['tagihan_list'][] = [
                'id' => $tagihan->id,
                'no_tagihan' => $tagihan->no_tagihan,
                'total' => $totalTagihan,
                'total_pembayaran' => $totalPembayaran,
                'sisa_tagihan' => $sisa,
                'status' => $tagihan->status,
                'tanggal_tagihan' => $tagihan->tanggal_tagihan ? $tagihan->tanggal_tagihan->format('Y-m-d') : null,
                'tanggal_jatuh_tempo' => $tagihan->tanggal_jatuh_tempo ? $tagihan->tanggal_jatuh_tempo->format('Y-m-d') : null,
                'keterangan' => $tagihan->keterangan,
            ];
        }

        usort($bySemester, function ($a, $b) {
            return $b['semester']['kode'] <=> $a['semester']['kode'];
        });

        return response()->json([
            'mahasiswa' => [
                'id' => $mahasiswa->id,
                'nim' => $mahasiswa->nim,
                'nama' => $mahasiswa->nama,
                'prodi' => $mahasiswa->prodi ? [
                    'id' => $mahasiswa->prodi->id,
                    'nama' => $mahasiswa->prodi->nama,
                    'kode' => $mahasiswa->prodi->kode ?? null,
                ] : null,
            ],
            'data' => array_values($bySemester),
        ]);
    }

    /**
     * Normalisasi nilai sel PhpSpreadsheet ke string (NIM, kode semester, nama, dll.).
     * Menghindari trim() pada int/float (PHP 8+) dan nilai numerik dari Excel.
     */
    private function spreadsheetCellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value) || is_int($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return trim((string) $value);
    }

    /**
     * True jika sel berisi nilai (nominal, tanggal serial Excel, teks; termasuk angka 0).
     * Jangan memakai empty(): di PHP empty("0") dan empty(0) bernilai true.
     */
    private function spreadsheetCellIsFilled(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if ($value instanceof \DateTimeInterface) {
            return true;
        }
        if (is_float($value) || is_int($value)) {
            return true;
        }
        if (is_bool($value)) {
            return $value;
        }

        return trim((string) $value) !== '';
    }

    /**
     * Baris dianggap kosong jika tidak ada sel bermakna (angka non-nol atau teks non-kosong).
     */
    private function spreadsheetDataRowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell === null) {
                continue;
            }
            if ($cell instanceof \DateTimeInterface) {
                return false;
            }
            if (is_float($cell) || is_int($cell)) {
                if ((float) $cell !== 0.0) {
                    return false;
                }

                continue;
            }
            if (is_string($cell) && trim($cell) !== '') {
                return false;
            }
            if (is_bool($cell) && $cell) {
                return false;
            }
            if (! is_string($cell) && ! is_int($cell) && ! is_float($cell) && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
