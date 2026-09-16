<?php

namespace App\Http\Controllers;

use App\Models\AturanAksesKeuangan;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Tagihan;
use App\Models\Ujian;
use App\Services\JadwalUjianDuplikat;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UjianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $prodiId = $request->get('id_prodi');
        $kelasId = $request->get('id_kelas');
        $semesterId = $request->get('id_semester');

        $query = Ujian::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.kurikulumMatkul.kurikulum',
            'kelas.prodi',
            'kelas.semester',
            'semester',
            'ruangan',
        ])->whereHas('kelas', function ($q) {
            $q->whereNull('deleted_at');
        });

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereHas('kelas', function ($q) use ($allowedProdiIds) {
                    $q->whereIn('id_prodi', $allowedProdiIds);
                });
                if ($prodiId && ! in_array((int) $prodiId, $allowedProdiIds, true)) {
                    $prodiId = null;
                }
            }
        }

        if ($search && trim((string) $search) !== '') {
            $s = trim((string) $search);
            $query->where(function ($q) use ($s) {
                $q->whereHas('kelas.kurikulumMatkul.matkul', function ($q) use ($s) {
                    $q->where('nama', 'like', "%{$s}%")
                        ->orWhere('kode', 'like', "%{$s}%");
                })
                    ->orWhereHas('kelas.kurikulumMatkul', function ($q) use ($s) {
                        $q->where('nama_matkul', 'like', "%{$s}%")
                            ->orWhere('kode_matkul', 'like', "%{$s}%");
                    });
            });
        }

        if ($prodiId) {
            $query->whereHas('kelas', function ($q) use ($prodiId) {
                $q->where('id_prodi', $prodiId);
            });
        }

        if ($kelasId) {
            $query->where('id_kelas', $kelasId);
        }

        if ($semesterId) {
            $query->where('id_semester', $semesterId);
        }

        $data = $query
            ->orderByDesc('tanggal_mulai')
            ->orderBy('id_kelas')
            ->orderBy('jenis_ujian')
            ->paginate($perPage);

        return response()->json($data);
    }

    public function show(Request $request, Ujian $ujian): JsonResponse
    {
        $ujian->load([
            'kelas.kurikulumMatkul.matkul',
            'kelas.kurikulumMatkul.kurikulum',
            'kelas.prodi',
            'kelas.semester',
            'kelas.kelompokKelas',
            'semester',
            'ruangan',
        ]);

        $kelas = $ujian->kelas;
        if (! $kelas) {
            abort(404, 'Kelas untuk jadwal ujian ini tidak ditemukan.');
        }

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data ini.');
            }
        }

        $peserta = $this->buildPesertaRowsForKelas((int) $ujian->id_kelas);

        $kodeAkses = Str::lower((string) $ujian->jenis_ujian);
        $aturan = AturanAksesKeuangan::query()
            ->where('kode_akses', $kodeAkses)
            ->where('status', 'active')
            ->first();
        $persentaseMinimumSyarat = $aturan && $aturan->persentase_minimum !== null
            ? (float) $aturan->persentase_minimum
            : null;

        return response()->json([
            'ujian' => $ujian,
            'peserta' => $peserta,
            'persentase_minimum_syarat' => $persentaseMinimumSyarat,
        ]);
    }

    public function exportPesertaExcel(Request $request, Ujian $ujian): StreamedResponse
    {
        $this->assertCanExportPesertaUjian($request, $ujian);

        $peserta = $this->buildPesertaRowsForKelas((int) $ujian->id_kelas);

        $kodeAkses = Str::lower((string) $ujian->jenis_ujian);
        $aturan = AturanAksesKeuangan::query()
            ->where('kode_akses', $kodeAkses)
            ->where('status', 'active')
            ->first();
        $persentaseMinimumSyarat = $aturan && $aturan->persentase_minimum !== null
            ? (float) $aturan->persentase_minimum
            : null;

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'No',
            'NIM',
            'Nama',
            'Prodi',
            'Status Pembayaran',
            'Keterangan',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

        $rowNum = 2;
        $no = 1;
        foreach ($peserta as $p) {
            $m = $p['mahasiswa'] ?? null;
            $prodiText = '—';
            if (is_array($m) && ! empty($m['prodi'])) {
                $pr = $m['prodi'];
                $prodiText = ($pr['nama'] ?? '').(! empty($pr['kode']) ? ' ('.$pr['kode'].')' : '');
            }
            $pct = $p['persentase_pembayaran_akademik'] ?? null;
            $pctStr = $pct === null
                ? '—'
                : number_format((float) $pct, 2, ',', '.').'%';

            $keterangan = '—';
            if ($this->pesertaTidakMemenuhiSyaratKeuangan($p, $persentaseMinimumSyarat)) {
                $keterangan = 'Belum memenuhi persyaratan';
            }

            $sheet->fromArray([[
                $no,
                (string) (is_array($m) ? ($m['nim'] ?? '—') : '—'),
                (string) (is_array($m) ? ($m['nama'] ?? '—') : '—'),
                $prodiText,
                $pctStr,
                $keterangan,
            ]], null, 'A'.$rowNum);
            $rowNum++;
            $no++;
        }

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'peserta_ujian_'.$ujian->id.'_'.date('Y-m-d_His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function exportPesertaPdf(Request $request, Ujian $ujian): StreamedResponse
    {
        $this->assertCanExportPesertaUjian($request, $ujian);

        $ujian->load([
            'kelas.kurikulumMatkul.matkul',
            'kelas.kurikulumMatkul.kurikulum',
            'kelas.prodi',
            'kelas.semester',
            'kelas.kelompokKelas',
            'semester',
            'ruangan',
        ]);

        $peserta = $this->buildPesertaRowsForKelas((int) $ujian->id_kelas);

        $kodeAkses = Str::lower((string) $ujian->jenis_ujian);
        $aturan = AturanAksesKeuangan::query()
            ->where('kode_akses', $kodeAkses)
            ->where('status', 'active')
            ->first();
        $persentaseMinimum = $aturan && $aturan->persentase_minimum !== null
            ? (float) $aturan->persentase_minimum
            : null;

        $matkul = $ujian->kelas?->kurikulumMatkul?->matkul;
        $km = $ujian->kelas?->kurikulumMatkul;
        $matkulLabel = $matkul
            ? ($matkul->kode.' – '.$matkul->nama)
            : (($km?->nama_matkul ?? '') !== ''
                ? (($km->kode_matkul ?? '—').' – '.$km->nama_matkul)
                : '—');

        $semLabel = ($ujian->semester ?? $ujian->kelas?->semester);
        $semText = $semLabel ? ($semLabel->nama.' ('.$semLabel->kode.')') : '—';

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; }
        .title { text-align: center; font-size: 13pt; font-weight: bold; margin-bottom: 4px; }
        .meta { margin-bottom: 12px; font-size: 9pt; }
        .meta table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 8px 2px 0; vertical-align: top; }
        .meta .k { width: 140px; color: #333; }
        table.peserta { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.peserta th { background-color: #334155; color: #fff; padding: 6px 4px; border: 1px solid #000; font-size: 8pt; text-align: center; }
        table.peserta td { padding: 4px; border: 1px solid #000; font-size: 8pt; vertical-align: top; }
        table.peserta td.num { text-align: center; }
        table.peserta tr.tidak-memenuhi td.strike { text-decoration: line-through; }
        table.peserta td.ttd { min-height: 3em; }
        .ket { font-size: 7pt; color: #b91c1c; font-style: italic; }
        .ket-info { font-size: 7pt; line-height: 1.35; }
        .footer { margin-top: 14px; font-size: 8pt; text-align: right; color: #444; }
        </style></head><body>';

        $html .= '<div class="title">DAFTAR PESERTA UJIAN</div>';
        $html .= '<div class="meta"><table>';
        $html .= '<tr><td class="k">Jenis ujian</td><td>'.htmlspecialchars((string) $ujian->jenis_ujian).'</td></tr>';
        $html .= '<tr><td class="k">Mata kuliah</td><td>'.htmlspecialchars($matkulLabel).'</td></tr>';
        $html .= '<tr><td class="k">Program studi</td><td>'.htmlspecialchars(
            $ujian->kelas?->prodi
                ? ($ujian->kelas->prodi->nama.($ujian->kelas->prodi->kode ? ' ('.$ujian->kelas->prodi->kode.')' : ''))
                : '—'
        ).'</td></tr>';
        $html .= '<tr><td class="k">Semester</td><td>'.htmlspecialchars($semText).'</td></tr>';
        $html .= '<tr><td class="k">Kode kelas</td><td>'.htmlspecialchars((string) ($ujian->kelas?->kode ?? '—')).'</td></tr>';
        $html .= '<tr><td class="k">Ruangan</td><td>'.htmlspecialchars((string) ($ujian->ruangan?->nama ?? '—')).'</td></tr>';
        $html .= '<tr><td class="k">Waktu</td><td>Mulai: '.htmlspecialchars($ujian->tanggal_mulai?->format('d/m/Y H:i') ?? '—')
            .' &nbsp; Selesai: '.htmlspecialchars($ujian->tanggal_selesai?->format('d/m/Y H:i') ?? '—').'</td></tr>';
        if ($persentaseMinimum !== null) {
            $html .= '<tr><td class="k">Syarat bayar (min.)</td><td>'.htmlspecialchars(number_format($persentaseMinimum, 2, ',', '.')).'% (kode akses: '
                .htmlspecialchars($kodeAkses).')</td></tr>';
        }
        $html .= '</table></div>';

        $html .= '<table class="peserta"><thead><tr>';
        $html .= '<th style="width:4%">No</th><th style="width:11%">NIM</th><th style="width:20%">Nama</th>';
        $html .= '<th style="width:16%">Prodi</th>';
        $html .= '<th style="width:13%">Keterangan</th><th>Tanda Tangan</th>';
        $html .= '</tr></thead><tbody>';

        $no = 1;
        foreach ($peserta as $p) {
            $tidakMemenuhi = $this->pesertaTidakMemenuhiSyaratKeuangan($p, $persentaseMinimum);
            $rowClass = $tidakMemenuhi ? ' class="tidak-memenuhi"' : '';

            $prodiText = '—';
            if (! empty($p['mahasiswa']['prodi'])) {
                $pr = $p['mahasiswa']['prodi'];
                $prodiText = ($pr['nama'] ?? '').(! empty($pr['kode']) ? ' ('.$pr['kode'].')' : '');
            }
            $ketHtml = '';
            if ($tidakMemenuhi) {
                $ketHtml = '<span class="ket">Belum memenuhi persyaratan</span>';
            }

            $strike = $tidakMemenuhi ? ' strike' : '';
            $html .= '<tr'.$rowClass.'>';
            $html .= '<td class="num'.$strike.'">'.$no.'</td>';
            $html .= '<td class="'.$strike.'">'.htmlspecialchars((string) ($p['mahasiswa']['nim'] ?? '—')).'</td>';
            $html .= '<td class="'.$strike.'">'.htmlspecialchars((string) ($p['mahasiswa']['nama'] ?? '—')).'</td>';
            $html .= '<td class="'.$strike.'">'.htmlspecialchars($prodiText).'</td>';
            $html .= '<td>'.$ketHtml.'</td>';
            $html .= '<td class="ttd">&nbsp;<br><br><br></td>';
            $html .= '</tr>';
            $no++;
        }

        if ($peserta->isEmpty()) {
            $html .= '<tr><td colspan="6" style="text-align:center">Belum ada peserta (KRS).</td></tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">Dicetak: '.now()->format('d/m/Y H:i').'</div></body></html>';

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'peserta_ujian_'.$ujian->id.'_'.date('Y-m-d_His').'.pdf';

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function assertCanExportPesertaUjian(Request $request, Ujian $ujian): void
    {
        $ujian->loadMissing(['kelas.prodi']);
        $kelas = $ujian->kelas;
        if (! $kelas) {
            abort(404, 'Kelas untuk jadwal ujian ini tidak ditemukan.');
        }
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data ini.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $p  baris dari buildPesertaRowsForKelas
     */
    private function pesertaTidakMemenuhiSyaratKeuangan(array $p, ?float $persentaseMinimum): bool
    {
        if ($persentaseMinimum === null) {
            return false;
        }
        $pct = $p['persentase_pembayaran_akademik'] ?? null;
        if ($pct === null) {
            return true;
        }

        return (float) $pct < $persentaseMinimum;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildPesertaRowsForKelas(int $idKelas): Collection
    {
        $krsRows = Krs::query()
            ->where('krs.id_kelas', $idKelas)
            ->where('krs.approved_at', '!=', null)
            ->whereNull('krs.deleted_at')
            ->whereHas('mahasiswa', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->with([
                'mahasiswa' => static function ($q) {
                    $q->select('id', 'nim', 'nama', 'id_prodi')
                        ->with(['prodi:id,nama,kode']);
                },
            ])
            ->orderBy('id')
            ->get();

        $idMahasiswaPeserta = $krsRows
            ->pluck('id_mahasiswa')
            ->filter()
            ->unique()
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        $persentaseMap = $this->persentasePembayaranAkademikUntukMahasiswa($idMahasiswaPeserta);

        return $krsRows->map(static function (Krs $k) use ($persentaseMap) {
            $m = $k->mahasiswa;
            $mid = $m ? (int) $m->id : null;

            return [
                'id_krs' => $k->id,
                'krs_status' => $k->approved_at ? 'disetujui' : 'menunggu',
                'approved_at' => $k->approved_at?->toIso8601String(),
                'persentase_pembayaran_akademik' => $mid !== null
                    ? ($persentaseMap[$mid] ?? null)
                    : null,
                'mahasiswa' => $m ? [
                    'id' => $m->id,
                    'nim' => $m->nim,
                    'nama' => $m->nama,
                    'prodi' => $m->prodi ? [
                        'id' => $m->prodi->id,
                        'nama' => $m->prodi->nama,
                        'kode' => $m->prodi->kode,
                    ] : null,
                ] : null,
            ];
        });
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_kelas' => [
                'required',
                Rule::exists('kelas', 'id')->whereNull('deleted_at'),
            ],
            'jenis_ujian' => ['required', Rule::in(Ujian::JENIS)],
            'id_ruangan' => ['nullable', Rule::exists('ruangan', 'id')->whereNull('deleted_at')],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
        ]);

        $this->assertTanggalUjianTidakMundur($validated['tanggal_mulai'] ?? null, $validated['tanggal_selesai'] ?? null);

        $kelas = Kelas::query()->whereNull('deleted_at')->findOrFail($validated['id_kelas']);
        $idSemester = (int) $kelas->id_semester;

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kelas ini.');
            }
        }

        // withTrashed() lewat JadwalUjianDuplikat: unique `ujian_unique` tidak menyertakan
        // deleted_at, jadi baris terhapus tetap menduduki slotnya dan dulu lolos cek ini lalu
        // menabrak constraint sebagai 500.
        $bentrok = JadwalUjianDuplikat::cari(
            (int) $validated['id_kelas'],
            $idSemester,
            $validated['jenis_ujian'],
        );
        if ($bentrok !== null) {
            return $this->responsBentrok($bentrok);
        }

        $actor = $request->user() ? ((string) ($request->user()->name ?? $request->user()->id)) : 'system';

        $row = Ujian::create([
            'id_kelas' => (int) $validated['id_kelas'],
            'jenis_ujian' => $validated['jenis_ujian'],
            'id_ruangan' => isset($validated['id_ruangan']) ? (int) $validated['id_ruangan'] : null,
            'id_semester' => $idSemester,
            'tanggal_mulai' => $validated['tanggal_mulai'] ?? null,
            'tanggal_selesai' => $validated['tanggal_selesai'] ?? null,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        $row->load([
            'kelas.kurikulumMatkul.matkul',
            'kelas.prodi',
            'semester',
            'ruangan',
        ]);

        return response()->json($row, 201);
    }

    public function update(Request $request, Ujian $ujian): JsonResponse
    {
        $validated = $request->validate([
            'id_kelas' => [
                'sometimes',
                'required',
                Rule::exists('kelas', 'id')->whereNull('deleted_at'),
            ],
            'jenis_ujian' => ['sometimes', 'required', Rule::in(Ujian::JENIS)],
            'id_ruangan' => ['nullable', Rule::exists('ruangan', 'id')->whereNull('deleted_at')],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
        ]);

        $idKelas = isset($validated['id_kelas']) ? (int) $validated['id_kelas'] : (int) $ujian->id_kelas;
        $jenisUjian = $validated['jenis_ujian'] ?? $ujian->jenis_ujian;

        $kelas = Kelas::query()->whereNull('deleted_at')->findOrFail($idKelas);
        $idSemester = (int) $kelas->id_semester;

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $kelasLama = Kelas::query()->withTrashed()->find((int) $ujian->id_kelas);
                if ($kelasLama && ! in_array((int) $kelasLama->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke jadwal ujian ini.');
                }
                if (! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke kelas ini.');
                }
            }
        }

        $bentrok = JadwalUjianDuplikat::cari($idKelas, $idSemester, $jenisUjian, (int) $ujian->id);
        if ($bentrok !== null) {
            return $this->responsBentrok($bentrok);
        }

        $actor = $request->user() ? ((string) ($request->user()->name ?? $request->user()->id)) : 'system';

        $mulaiFinal = array_key_exists('tanggal_mulai', $validated) ? $validated['tanggal_mulai'] : $ujian->tanggal_mulai?->toIso8601String();
        $selesaiFinal = array_key_exists('tanggal_selesai', $validated) ? $validated['tanggal_selesai'] : $ujian->tanggal_selesai?->toIso8601String();
        $this->assertTanggalUjianTidakMundur($mulaiFinal, $selesaiFinal);

        $update = [
            'id_kelas' => $idKelas,
            'jenis_ujian' => $jenisUjian,
            'id_semester' => $idSemester,
            'updated_by' => $actor,
        ];
        if (array_key_exists('tanggal_mulai', $validated)) {
            $update['tanggal_mulai'] = $validated['tanggal_mulai'];
        }
        if (array_key_exists('tanggal_selesai', $validated)) {
            $update['tanggal_selesai'] = $validated['tanggal_selesai'];
        }
        if (array_key_exists('id_ruangan', $validated)) {
            $update['id_ruangan'] = $validated['id_ruangan'] !== null ? (int) $validated['id_ruangan'] : null;
        }

        $ujian->update($update);

        $ujian->load([
            'kelas.kurikulumMatkul.matkul',
            'kelas.prodi',
            'semester',
            'ruangan',
        ]);

        return response()->json($ujian);
    }

    public function destroy(Request $request, Ujian $ujian): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $kelas = Kelas::query()->withTrashed()->find((int) $ujian->id_kelas);
                if ($kelas && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke jadwal ujian ini.');
                }
            }
        }

        $actor = $user ? ((string) ($user->name ?? $user->id)) : 'system';
        $ujian->update(['deleted_by' => $actor]);
        $ujian->delete();

        return response()->json(['message' => 'Jadwal ujian dihapus.']);
    }

    /**
     * Persentase pelunasan bagian tagihan yang komponennya akademik: pembayaran disetujui
     * dialokasikan ke rincian akademik secara proporsional (A/T) per tagihan.
     *
     * @param  list<int>  $idMahasiswa
     * @return array<int, float|null> id_mahasiswa => 0..100, atau null jika tidak ada tagihan akademik
     */
    private function persentasePembayaranAkademikUntukMahasiswa(array $idMahasiswa): array
    {
        $idMahasiswa = array_values(array_unique(array_filter(
            array_map('intval', $idMahasiswa),
            static fn (int $i) => $i > 0
        )));
        if ($idMahasiswa === []) {
            return [];
        }

        $totalAkademik = array_fill_keys($idMahasiswa, 0.0);
        $terbayarAkademik = array_fill_keys($idMahasiswa, 0.0);

        $tagihans = Tagihan::query()
            ->whereIn('id_mahasiswa', $idMahasiswa)
            ->whereNull('deleted_at')
            ->with([
                'tagihanRinci' => static function ($q) {
                    $q->whereNull('deleted_at')->with('komponenBiaya');
                },
                'pembayaran' => static function ($q) {
                    $q->whereNull('deleted_at')->whereNotNull('approved_at');
                },
            ])
            ->get();

        foreach ($tagihans as $t) {
            $mid = (int) $t->id_mahasiswa;
            if (! array_key_exists($mid, $terbayarAkademik)) {
                continue;
            }

            $T = 0.0;
            $A = 0.0;
            foreach ($t->tagihanRinci as $r) {
                $n = (float) $r->nominal;
                $T += $n;
                $kb = $r->komponenBiaya;
                if ($kb && (bool) $kb->is_akademik) {
                    $A += $n;
                }
            }

            if ($A > 0) {
                $totalAkademik[$mid] += $A;
            }
            if ($T > 0 && $A > 0) {
                $P = (float) $t->pembayaran->sum(static fn ($p) => (float) $p->nominal);
                $terbayarAkademik[$mid] += $P * ($A / $T);
            }
        }

        $out = [];
        foreach ($idMahasiswa as $mid) {
            $tot = (float) ($totalAkademik[$mid] ?? 0);
            if ($tot <= 0) {
                $out[$mid] = null;

                continue;
            }
            $paid = (float) ($terbayarAkademik[$mid] ?? 0);
            $pct = ($paid / $tot) * 100;
            $out[$mid] = round(min(100, max(0, $pct)), 2);
        }

        return $out;
    }

    /**
     * Respons untuk bentrokan unique `ujian_unique`.
     *
     * Bentrok dengan jadwal HIDUP tetap 422 seperti sebelumnya. Bentrok dengan jadwal TERHAPUS
     * dijawab 409 plus id barisnya, karena penyebab dan jalan keluarnya berbeda: klien tidak bisa
     * melihat baris itu di daftar mana pun, jadi tanpa id-nya ia hanya tahu "duplikat" tanpa bisa
     * memulihkan atau menghapusnya permanen. Panel admin memakai ini untuk memunculkan tawaran.
     */
    private function responsBentrok(Ujian $bentrok): JsonResponse
    {
        if (! $bentrok->trashed()) {
            return response()->json([
                'message' => 'Untuk kelas, semester, dan jenis ujian ini sudah ada jadwal.',
                'errors' => [
                    'id_kelas' => [JadwalUjianDuplikat::pesanBentrokHidup()],
                ],
            ], 422);
        }

        return response()->json([
            'message' => JadwalUjianDuplikat::pesanBentrokTerhapus(),
            'errors' => [
                'id_kelas' => [JadwalUjianDuplikat::pesanBentrokTerhapus()],
            ],
            'duplikat_terhapus' => [
                'id' => (int) $bentrok->id,
                'jenis_ujian' => $bentrok->jenis_ujian,
                'tanggal_mulai' => $bentrok->tanggal_mulai?->toIso8601String(),
                'tanggal_selesai' => $bentrok->tanggal_selesai?->toIso8601String(),
                'id_ruangan' => $bentrok->id_ruangan !== null ? (int) $bentrok->id_ruangan : null,
                'deleted_at' => $bentrok->deleted_at?->toIso8601String(),
                'deleted_by' => $bentrok->deleted_by,
            ],
        ], 409);
    }

    private function assertTanggalUjianTidakMundur(mixed $mulai, mixed $selesai): void
    {
        if ($mulai === null || $selesai === null) {
            return;
        }
        if (Carbon::parse((string) $selesai)->lt(Carbon::parse((string) $mulai))) {
            throw ValidationException::withMessages([
                'tanggal_selesai' => ['Tanggal selesai harus sama atau setelah tanggal mulai.'],
            ]);
        }
    }
}
