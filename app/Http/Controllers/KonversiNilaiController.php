<?php

namespace App\Http\Controllers;

use App\Models\JenisKonversiNilai;
use App\Models\KonversiNilai;
use App\Models\Kurikulum;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\RentangNilai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KonversiNilaiController extends Controller
{
    /**
     * Daftar mahasiswa yang memiliki minimal satu baris konversi nilai,
     * dengan agregat jumlah MK dan total SKS (lama & baru).
     */
    public function ringkasanMahasiswa(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $prodiId = $request->get('id_prodi') ? (int) $request->get('id_prodi') : null;

        $aggSub = DB::table('konversi_nilai')
            ->whereNull('deleted_at')
            ->select('id_mahasiswa')
            ->selectRaw('COUNT(*) as jumlah_matkul')
            ->selectRaw('COALESCE(SUM(sks_baru), 0) as total_sks_baru')
            ->selectRaw('COALESCE(SUM(sks_lama), 0) as total_sks_lama')
            ->groupBy('id_mahasiswa');

        $query = Mahasiswa::query()
            ->joinSub($aggSub, 'k_agg', 'mahasiswa.id', '=', 'k_agg.id_mahasiswa')
            ->select('mahasiswa.*')
            ->addSelect([
                'k_agg.jumlah_matkul',
                'k_agg.total_sks_baru',
                'k_agg.total_sks_lama',
            ])
            ->with(['prodi:id,nama,kode']);

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('mahasiswa.id_prodi', $allowedProdiIds);
                if ($prodiId && ! in_array($prodiId, $allowedProdiIds, true)) {
                    $prodiId = null;
                }
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('mahasiswa.nama', 'like', "%{$search}%")
                    ->orWhere('mahasiswa.nim', 'like', "%{$search}%");
            });
        }

        if ($prodiId) {
            $query->where('mahasiswa.id_prodi', $prodiId);
        }

        $paginator = $query->orderBy('mahasiswa.nama')->paginate($perPage);

        $paginator->getCollection()->transform(function (Mahasiswa $m) {
            return [
                'id' => $m->id,
                'nim' => $m->nim,
                'nama' => $m->nama,
                'prodi' => $m->prodi ? [
                    'id' => $m->prodi->id,
                    'nama' => $m->prodi->nama,
                    'kode' => $m->prodi->kode ?? null,
                ] : null,
                'jumlah_matkul' => (int) ($m->jumlah_matkul ?? 0),
                'total_sks_lama' => (int) ($m->total_sks_lama ?? 0),
                'total_sks_baru' => (int) ($m->total_sks_baru ?? 0),
            ];
        });

        return response()->json($paginator);
    }

    /**
     * Daftar baris konversi nilai untuk portal prodi (scope prodi), dengan filter semester (tahun berlaku kurikulum) dan angkatan.
     */
    public function indexProdi(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $semesterId = $request->get('id_semester') ? (int) $request->get('id_semester') : null;
        $semesterMasukId = $request->get('id_semester_masuk') ? (int) $request->get('id_semester_masuk') : null;

        $query = KonversiNilai::query()
            ->whereNull('konversi_nilai.deleted_at')
            ->with([
                'mahasiswa' => function ($q) {
                    $q->select('id', 'nim', 'nama', 'id_prodi', 'id_semester_masuk')
                        ->with([
                            'prodi:id,nama,kode',
                            'semester_masuk:id,kode,nama',
                        ]);
                },
                'kurikulum' => function ($q) {
                    $q->select('id', 'kode', 'nama', 'id_prodi', 'id_tahun_berlaku')
                        ->with(['tahunBerlaku:id,kode,nama']);
                },
                'jenisKonversi:id,nama',
            ]);

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereHas('mahasiswa', fn ($q) => $q->whereIn('id_prodi', $allowedProdiIds));
            }
        }

        if ($search) {
            $s = trim((string) $search);
            $query->where(function ($q) use ($s) {
                $q->whereHas('mahasiswa', function ($mq) use ($s) {
                    $mq->where('nama', 'like', "%{$s}%")
                        ->orWhere('nim', 'like', "%{$s}%");
                })
                    ->orWhere('kode_mk_lama', 'like', "%{$s}%")
                    ->orWhere('nama_mk_lama', 'like', "%{$s}%")
                    ->orWhere('kode_mk_baru', 'like', "%{$s}%")
                    ->orWhere('nama_mk_baru', 'like', "%{$s}%");
            });
        }

        if ($semesterMasukId) {
            $query->whereHas('mahasiswa', fn ($q) => $q->where('id_semester_masuk', $semesterMasukId));
        }

        if ($semesterId) {
            $query->whereHas('kurikulum', fn ($q) => $q->where('id_tahun_berlaku', $semesterId));
        }

        $paginator = $query->orderByDesc('konversi_nilai.created_at')
            ->orderByDesc('konversi_nilai.id')
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn (KonversiNilai $k) => $this->serializeKonversiNilaiForProdi($k));

        return response()->json($paginator);
    }

    /**
     * Satu baris konversi nilai untuk portal prodi (detail).
     */
    public function showProdi(Request $request, int $id): JsonResponse
    {
        $k = KonversiNilai::query()
            ->whereNull('konversi_nilai.deleted_at')
            ->with([
                'mahasiswa' => function ($q) {
                    $q->select('id', 'nim', 'nama', 'id_prodi', 'id_semester_masuk')
                        ->with([
                            'prodi:id,nama,kode',
                            'semester_masuk:id,kode,nama',
                        ]);
                },
                'kurikulum' => function ($q) {
                    $q->select('id', 'kode', 'nama', 'id_prodi', 'id_tahun_berlaku')
                        ->with(['tahunBerlaku:id,kode,nama']);
                },
                'jenisKonversi:id,nama',
                'nilai:id,huruf_mutu,angka_mutu',
            ])
            ->find($id);

        if (! $k) {
            return response()->json(['message' => 'Data konversi tidak ditemukan.'], 404);
        }

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && $k->mahasiswa && ! in_array((int) $k->mahasiswa->id_prodi, $allowedProdiIds, true)) {
                return response()->json(['message' => 'Anda tidak memiliki akses ke data ini.'], 403);
            }
        }

        $payload = $this->serializeKonversiNilaiForProdi($k);
        $payload['nilai_krs'] = $k->nilai ? [
            'huruf_mutu' => $k->nilai->huruf_mutu,
            'angka_mutu' => $k->nilai->angka_mutu !== null ? (string) $k->nilai->angka_mutu : null,
        ] : null;
        $payload['updated_at'] = $k->updated_at?->toIso8601String();
        $payload['updated_by'] = $k->updated_by;

        return response()->json($payload);
    }

    /**
     * Setujui / batalkan persetujuan konversi nilai (portal prodi).
     */
    public function setApprovalProdi(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'is_approved' => ['required', 'boolean'],
        ]);

        $k = KonversiNilai::with('mahasiswa')->find($id);
        if (! $k) {
            return response()->json(['message' => 'Data konversi tidak ditemukan.'], 404);
        }

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && $k->mahasiswa && ! in_array((int) $k->mahasiswa->id_prodi, $allowedProdiIds, true)) {
                return response()->json(['message' => 'Anda tidak memiliki akses ke data ini.'], 403);
            }
        }

        $k->is_approved = $validated['is_approved'];
        if ($user) {
            $k->updated_by = $user->name ?? (string) $user->id;
        }
        $k->save();

        return response()->json([
            'message' => $k->is_approved ? 'Konversi disetujui.' : 'Persetujuan konversi dibatalkan.',
            'id' => $k->id,
            'is_approved' => (bool) $k->is_approved,
        ]);
    }

    /**
     * Buat baris nilai dari konversi: huruf & angka mutu mengikuti rentang_nilai jenjang prodi mahasiswa.
     * Baris nilai untuk konversi memakai id_krs null (tidak terikat KRS).
     */
    public function transferToNilaiProdi(int $id): JsonResponse
    {
        $k = KonversiNilai::query()
            ->whereNull('deleted_at')
            ->with([
                'mahasiswa' => function ($q) {
                    $q->select('id', 'nim', 'nama', 'id_prodi')
                        ->with(['prodi:id,id_jenjang,nama,kode']);
                },
            ])
            ->find($id);

        if (! $k || ! $k->mahasiswa) {
            return response()->json(['message' => 'Data konversi tidak ditemukan.'], 404);
        }

        $user = request()->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $k->mahasiswa->id_prodi, $allowedProdiIds, true)) {
                return response()->json(['message' => 'Anda tidak memiliki akses ke data ini.'], 403);
            }
        }

        if (! $k->is_approved) {
            return response()->json(['message' => 'Konversi harus disetujui sebelum ditransfer ke nilai.'], 422);
        }

        if ($k->id_nilai && Nilai::query()->whereKey($k->id_nilai)->whereNull('deleted_at')->exists()) {
            return response()->json(['message' => 'Konversi ini sudah terhubung ke data nilai.'], 422);
        }

        $prodi = $k->mahasiswa->prodi;
        if (! $prodi || ! $prodi->id_jenjang) {
            return response()->json(['message' => 'Program studi mahasiswa belum memiliki jenjang (id_jenjang). Lengkapi data prodi dan rentang nilai.'], 422);
        }

        $hurufKonversi = strtoupper(trim($k->nilai_baru));
        $rentang = RentangNilai::query()
            ->where('id_jenjang', (int) $prodi->id_jenjang)
            ->whereRaw('UPPER(TRIM(nilai_huruf)) = ?', [$hurufKonversi])
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();

        if (! $rentang) {
            return response()->json([
                'message' => 'Tidak ada rentang nilai untuk huruf "'.$k->nilai_baru.'" pada jenjang prodi ini. Perbarui master rentang nilai.',
            ], 422);
        }

        $actor = $user ? ($user->name ?? (string) $user->id) : 'system';

        $nilaiExisting = Nilai::query()
            ->where('id_konversi_nilai', $k->id)
            ->whereNull('deleted_at')
            ->first();

        if ($nilaiExisting) {
            if (! $k->id_nilai) {
                DB::transaction(function () use ($k, $nilaiExisting, $actor) {
                    $k->id_nilai = $nilaiExisting->id;
                    $k->updated_by = $actor;
                    $k->save();
                });
            }

            return response()->json([
                'message' => 'Konversi ini sudah memiliki data nilai.',
                'nilai' => [
                    'id' => $nilaiExisting->id,
                    'id_krs' => $nilaiExisting->id_krs,
                    'id_konversi_nilai' => $nilaiExisting->id_konversi_nilai,
                    'sks' => (int) $nilaiExisting->sks,
                    'huruf_mutu' => $nilaiExisting->huruf_mutu,
                    'angka_mutu' => $nilaiExisting->angka_mutu !== null ? (string) $nilaiExisting->angka_mutu : null,
                    'dari_rentang_nilai' => null,
                ],
            ], 200);
        }

        $sksBaru = max(1, (int) $k->sks_baru);

        try {
            $nilai = DB::transaction(function () use ($k, $rentang, $sksBaru, $actor) {
                $atribut = [
                    'id_krs' => null,
                    'id_konversi_nilai' => $k->id,
                    'sks' => $sksBaru,
                    'angka_mutu' => $rentang->nilai_angka,
                    'huruf_mutu' => $rentang->nilai_huruf,
                    'is_final' => true,
                    'revisi' => 0,
                    'created_by' => $actor,
                ];

                // Nilai soft-deleted dipulihkan: unique id_konversi_nilai ikut menghitungnya.
                $nilai = Nilai::pulihkanUntukKonversiBaru($k->id);
                if ($nilai) {
                    $nilai->update($atribut);
                } else {
                    $nilai = Nilai::create($atribut);
                }

                $k->id_nilai = $nilai->id;
                $k->updated_by = $actor;
                $k->save();

                return $nilai;
            });
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal menyimpan nilai.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Konversi berhasil ditransfer ke nilai.',
            'nilai' => [
                'id' => $nilai->id,
                'id_krs' => $nilai->id_krs,
                'id_konversi_nilai' => $nilai->id_konversi_nilai,
                'sks' => (int) $nilai->sks,
                'huruf_mutu' => $nilai->huruf_mutu,
                'angka_mutu' => $nilai->angka_mutu !== null ? (string) $nilai->angka_mutu : null,
                'dari_rentang_nilai' => [
                    'nilai_huruf' => $rentang->nilai_huruf,
                    'nilai_angka' => (string) $rentang->nilai_angka,
                ],
            ],
        ], 201);
    }

    /**
     * Rincian semua baris konversi nilai untuk satu mahasiswa.
     */
    public function rincianMahasiswa(Request $request, Mahasiswa $mahasiswa): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data mahasiswa ini.');
            }
        }

        $mahasiswa->loadMissing(['prodi:id,nama,kode']);

        $rows = KonversiNilai::query()
            ->where('id_mahasiswa', $mahasiswa->id)
            ->whereNull('deleted_at')
            ->with([
                'kurikulum:id,kode,nama,status',
                'jenisKonversi:id,nama',
            ])
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->get();

        $jumlahMatkul = $rows->count();
        $totalSksLama = (int) $rows->sum('sks_lama');
        $totalSksBaru = (int) $rows->sum('sks_baru');

        $items = $rows->map(function (KonversiNilai $k) {
            return [
                'id' => $k->id,
                'id_kurikulum' => $k->id_kurikulum,
                'kurikulum' => $k->kurikulum ? [
                    'id' => $k->kurikulum->id,
                    'kode' => $k->kurikulum->kode,
                    'nama' => $k->kurikulum->nama,
                    'status' => $k->kurikulum->status ?? null,
                ] : null,
                'id_jenis_konversi' => $k->id_jenis_konversi,
                'jenis_konversi' => $k->jenisKonversi ? [
                    'id' => $k->jenisKonversi->id,
                    'nama' => $k->jenisKonversi->nama,
                ] : null,
                'kode_mk_lama' => $k->kode_mk_lama,
                'nama_mk_lama' => $k->nama_mk_lama,
                'sks_lama' => (int) $k->sks_lama,
                'nilai_lama' => $k->nilai_lama,
                'kode_mk_baru' => $k->kode_mk_baru,
                'nama_mk_baru' => $k->nama_mk_baru,
                'sks_baru' => (int) $k->sks_baru,
                'nilai_baru' => $k->nilai_baru,
                'keterangan' => $k->keterangan,
                'is_approved' => (bool) $k->is_approved,
                'created_at' => $k->created_at?->toIso8601String(),
                'created_by' => $k->created_by,
            ];
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
            'ringkasan' => [
                'jumlah_matkul' => $jumlahMatkul,
                'total_sks_lama' => $totalSksLama,
                'total_sks_baru' => $totalSksBaru,
            ],
            'items' => $items,
        ]);
    }

    public function optionsJenisKonversi(Request $request): JsonResponse
    {
        $rows = JenisKonversiNilai::query()
            ->where(function ($q) {
                $q->where('is_aktif', true)->orWhereNull('is_aktif');
            })
            ->whereNull('deleted_at')
            ->orderBy('nama')
            ->get(['id', 'nama', 'keterangan']);

        return response()->json(['data' => $rows]);
    }

    public function optionsKurikulumMatkul(Request $request, Kurikulum $kurikulum): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kurikulum->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kurikulum ini.');
            }
        }

        $rows = KurikulumMatkul::query()
            ->where('id_kurikulum', $kurikulum->id)
            ->whereNull('deleted_at')
            ->with('matkul')
            ->orderBy('kode_matkul')
            ->get();

        $data = $rows->map(function (KurikulumMatkul $km) {
            $m = $km->matkul;
            $kode = $km->kode_matkul ?: ($m?->kode ?? '');
            $nama = $km->nama_matkul ?: ($m?->nama ?? '');
            $sks = (int) ($km->sks ?: ($m?->sks ?? 1));

            return [
                'id_kurikulum_matkul' => $km->id,
                'kode' => $kode,
                'nama' => $nama,
                'sks' => $sks,
                'id_matkul' => $km->id_matkul,
                'label' => trim($kode.' — '.$nama.' ('.$sks.' SKS)'),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Simpan banyak baris konversi nilai sekaligus (satu mahasiswa, satu kurikulum, satu jenis).
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_mahasiswa' => ['required', 'integer', 'exists:mahasiswa,id'],
            'id_kurikulum' => ['required', 'integer', 'exists:kurikulum,id'],
            'id_jenis_konversi' => ['required', 'integer', 'exists:jenis_konversi_nilai,id'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.kode_mk_lama' => ['required', 'string', 'max:50'],
            'rows.*.nama_mk_lama' => ['required', 'string', 'max:255'],
            'rows.*.sks_lama' => ['required', 'integer', 'min:1', 'max:255'],
            'rows.*.nilai_lama' => ['required', 'string', 'max:5'],
            'rows.*.id_kurikulum_matkul' => ['required', 'integer', 'exists:kurikulum_matkul,id'],
            'rows.*.nilai_baru' => ['required', 'string', 'max:5'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:500'],
            'rows.*.id_nilai' => ['nullable', 'integer', 'exists:nilai,id'],
        ]);

        $user = $request->user();
        $mahasiswa = Mahasiswa::find($validated['id_mahasiswa']);
        if (! $mahasiswa) {
            return response()->json(['message' => 'Mahasiswa tidak ditemukan.'], 404);
        }

        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mahasiswa ini.');
            }
        }

        $kurikulum = Kurikulum::find($validated['id_kurikulum']);
        if (! $kurikulum) {
            return response()->json(['message' => 'Kurikulum tidak ditemukan.'], 404);
        }

        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kurikulum->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kurikulum ini.');
            }
        }

        $createdBy = $user ? ($user->name ?? (string) $user->id) : 'system';

        try {
            $created = DB::transaction(function () use ($validated, $kurikulum, $createdBy) {
                $count = 0;
                foreach ($validated['rows'] as $row) {
                    $km = KurikulumMatkul::with('matkul')
                        ->where('id', $row['id_kurikulum_matkul'])
                        ->where('id_kurikulum', $kurikulum->id)
                        ->whereNull('deleted_at')
                        ->first();

                    if (! $km) {
                        throw new \InvalidArgumentException('Mata kuliah kurikulum tidak valid untuk kurikulum yang dipilih.');
                    }

                    $m = $km->matkul;
                    $kodeBaru = $km->kode_matkul ?: ($m?->kode ?? '');
                    $namaBaru = $km->nama_matkul ?: ($m?->nama ?? '');
                    $sksBaru = (int) ($km->sks ?: ($m?->sks ?? 1));

                    KonversiNilai::create([
                        'id_mahasiswa' => $validated['id_mahasiswa'],
                        'id_kurikulum' => $validated['id_kurikulum'],
                        'id_jenis_konversi' => $validated['id_jenis_konversi'],
                        'id_nilai' => $row['id_nilai'] ?? null,
                        'kode_mk_lama' => $row['kode_mk_lama'],
                        'nama_mk_lama' => $row['nama_mk_lama'],
                        'sks_lama' => (int) $row['sks_lama'],
                        'nilai_lama' => strtoupper($row['nilai_lama']),
                        'kode_mk_baru' => $kodeBaru,
                        'nama_mk_baru' => $namaBaru,
                        'sks_baru' => $sksBaru,
                        'nilai_baru' => strtoupper($row['nilai_baru']),
                        'keterangan' => $row['keterangan'] ?? null,
                        'is_approved' => true,
                        'created_by' => $createdBy,
                    ]);
                    $count++;
                }

                return $count;
            });

            return response()->json([
                'message' => 'Konversi nilai berhasil disimpan.',
                'created_count' => $created,
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
                return response()->json([
                    'message' => 'Data bentrok dengan konversi yang sudah ada (kombinasi mahasiswa, kode MK lama, dan kode MK baru harus unik).',
                ], 422);
            }

            return response()->json([
                'message' => 'Gagal menyimpan konversi nilai.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal menyimpan konversi nilai.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeKonversiNilaiForProdi(KonversiNilai $k): array
    {
        $m = $k->mahasiswa;
        $kur = $k->kurikulum;

        return [
            'id' => $k->id,
            'is_approved' => (bool) $k->is_approved,
            'id_nilai' => $k->id_nilai ? (int) $k->id_nilai : null,
            'mahasiswa' => $m ? [
                'id' => $m->id,
                'nim' => $m->nim,
                'nama' => $m->nama,
                'prodi' => $m->prodi ? ['id' => $m->prodi->id, 'nama' => $m->prodi->nama, 'kode' => $m->prodi->kode] : null,
                'semester_masuk' => $m->semester_masuk ? [
                    'id' => $m->semester_masuk->id,
                    'kode' => $m->semester_masuk->kode,
                    'nama' => $m->semester_masuk->nama,
                ] : null,
            ] : null,
            'kurikulum' => $kur ? [
                'id' => $kur->id,
                'kode' => $kur->kode,
                'nama' => $kur->nama,
                'tahun_berlaku' => $kur->tahunBerlaku ? [
                    'id' => $kur->tahunBerlaku->id,
                    'kode' => $kur->tahunBerlaku->kode,
                    'nama' => $kur->tahunBerlaku->nama,
                ] : null,
            ] : null,
            'jenis_konversi' => $k->jenisKonversi ? [
                'id' => $k->jenisKonversi->id,
                'nama' => $k->jenisKonversi->nama,
            ] : null,
            'kode_mk_lama' => $k->kode_mk_lama,
            'nama_mk_lama' => $k->nama_mk_lama,
            'sks_lama' => (int) $k->sks_lama,
            'nilai_lama' => $k->nilai_lama,
            'kode_mk_baru' => $k->kode_mk_baru,
            'nama_mk_baru' => $k->nama_mk_baru,
            'sks_baru' => (int) $k->sks_baru,
            'nilai_baru' => $k->nilai_baru,
            'keterangan' => $k->keterangan,
            'created_at' => $k->created_at?->toIso8601String(),
        ];
    }

}
