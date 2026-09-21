<?php

namespace App\Livewire\Admin\Nilai;

use App\Livewire\Admin\Nilai\Concerns\ForwardsIndexState;
use App\Models\Krs;
use App\Models\Nilai;
use App\Models\NilaiRevisi;
use App\Models\RentangNilai;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Form extends Component
{
    use ForwardsIndexState;

    /** Tujuan Batal/breadcrumb/redirect: detail nilai mahasiswa, membawa state Index. */
    public string $detailUrl = '';

    public int $mahasiswaId;

    public int $idKrs;

    public ?int $nilaiId = null;

    // ---- Info read-only, di-mount sekali (tidak berubah selama form dibuka) ----
    public string $mahasiswaNim = '';

    public string $mahasiswaNama = '';

    public string $mahasiswaProdiNama = '';

    public string $mahasiswaKelompokKelas = '';

    public string $matkulLabel = '';

    public string $semesterLabel = '';

    public ?int $idJenjang = null;

    // ---- Field form ----
    public string $sks = '';

    public string $huruf_mutu = '';

    public string $angka_mutu = '';

    public bool $is_final = false;

    public string $keterangan_revisi = '';

    public function mount(int $id, int $idKrs): void
    {
        $this->mahasiswaId = $id;
        $this->idKrs = $idKrs;
        $this->resolveBackUrl();
        $this->detailUrl = $this->urlDenganState(route('admin.akademik.nilai.show', $id));

        $krs = Krs::with([
            'mahasiswa.prodi',
            'mahasiswa.kelompok_kelas',
            'kelas.kurikulumMatkul.matkul',
            'kelas.semester',
        ])->findOrFail($idKrs);

        if ((int) $krs->id_mahasiswa !== $id) {
            abort(404);
        }

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            $prodiId = $krs->mahasiswa->id_prodi ?? null;
            if ($allowedProdiIds !== null && (! $prodiId || ! in_array((int) $prodiId, $allowedProdiIds, true))) {
                abort(403, 'Anda tidak memiliki akses ke data nilai ini.');
            }
        }

        $mahasiswa = $krs->mahasiswa;
        $matkul = $krs->kelas->kurikulumMatkul->matkul ?? null;

        $this->mahasiswaNim = $mahasiswa->nim ?? '';
        $this->mahasiswaNama = $mahasiswa->nama ?? '';
        $this->mahasiswaProdiNama = $mahasiswa->prodi
            ? $mahasiswa->prodi->nama.($mahasiswa->prodi->kode ? ' ('.$mahasiswa->prodi->kode.')' : '')
            : '—';
        $this->mahasiswaKelompokKelas = $mahasiswa->kelompok_kelas->nama ?? '—';
        $this->matkulLabel = $matkul
            ? ($matkul->kode ? $matkul->kode.' - ' : '').$matkul->nama
            : '—';
        $this->semesterLabel = $krs->kelas->semester
            ? $krs->kelas->semester->nama.' ('.$krs->kelas->semester->kode.')'
            : '—';
        $this->idJenjang = $mahasiswa->prodi->id_jenjang ?? null;

        $defaultSks = $matkul->sks ?? 0;
        $this->sks = (string) $defaultSks;

        $nilai = Nilai::where('id_krs', $idKrs)->whereNull('deleted_at')->first();
        if ($nilai) {
            $this->nilaiId = $nilai->id;
            $this->sks = $nilai->sks !== null ? (string) $nilai->sks : (string) $defaultSks;
            $this->angka_mutu = $nilai->angka_mutu !== null ? (string) $nilai->angka_mutu : '';
            $this->huruf_mutu = $nilai->huruf_mutu ?? '';
            $this->is_final = (bool) $nilai->is_final;
        }
    }

    /**
     * Opsi huruf mutu berasal dari master rentang nilai untuk jenjang prodi mahasiswa — sama
     * seperti dropdown "Huruf Mutu" di halaman edit nilai frontend.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function rentangOptions(): array
    {
        if (! $this->idJenjang) {
            return [];
        }

        return RentangNilai::where('id_jenjang', $this->idJenjang)
            ->whereNull('deleted_at')
            ->orderByDesc('nilai_tinggi')
            ->get()
            ->mapWithKeys(function ($rn) {
                $huruf = strtoupper((string) $rn->nilai_huruf);
                $label = $huruf.' (angka mutu '.$rn->nilai_angka.'; rentang '.$rn->nilai_rendah.'–'.$rn->nilai_tinggi.')';

                return [$huruf => $label];
            })
            ->all();
    }

    /**
     * Sama seperti selectHurufMutu di halaman edit nilai frontend — angka mutu terisi otomatis
     * dari master rentang nilai, tapi pengguna tetap bisa mengubahnya manual sesudahnya.
     */
    public function updatedHurufMutu(string $value): void
    {
        if ($value === '') {
            return;
        }

        $huruf = strtoupper(trim($value));
        $row = RentangNilai::where('id_jenjang', $this->idJenjang)
            ->whereNull('deleted_at')
            ->get()
            ->first(fn ($rn) => strtoupper(trim((string) $rn->nilai_huruf)) === $huruf);

        if ($row) {
            $this->angka_mutu = (string) $row->nilai_angka;
        }
    }

    protected function rules(): array
    {
        return [
            'sks' => ['nullable', 'integer', 'min:0', 'max:255'],
            'angka_mutu' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'huruf_mutu' => ['nullable', 'string', 'max:10'],
            'keterangan_revisi' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Sama persis dengan NilaiController::store/update (dipilih berdasarkan ada tidaknya nilai
     * untuk id_krs ini) — termasuk pemulihan baris nilai yang soft-deleted karena id_krs unik.
     */
    public function save()
    {
        $validated = $this->validate();
        $validated['sks'] = $validated['sks'] !== null && $validated['sks'] !== '' ? (int) $validated['sks'] : null;
        $validated['angka_mutu'] = $validated['angka_mutu'] !== null && $validated['angka_mutu'] !== '' ? (float) $validated['angka_mutu'] : null;
        $validated['huruf_mutu'] = $validated['huruf_mutu'] !== '' ? strtoupper($validated['huruf_mutu']) : null;
        $validated['is_final'] = $this->is_final;

        DB::transaction(function () use ($validated): void {
            $nilai = $this->nilaiId ? Nilai::find($this->nilaiId) : null;

            if (! $nilai) {
                $nilai = Nilai::onlyTrashed()->where('id_krs', $this->idKrs)->first();
                if ($nilai) {
                    $nilai->restore();
                    $nilai->deleted_by = null;
                }
            }

            if ($nilai) {
                $nilai->fill([
                    'id_krs' => $this->idKrs,
                    'sks' => $validated['sks'],
                    'angka_mutu' => $validated['angka_mutu'],
                    'huruf_mutu' => $validated['huruf_mutu'],
                    'is_final' => $validated['is_final'],
                ]);
                $nilai->save();
            } else {
                $nilai = Nilai::create([
                    'id_krs' => $this->idKrs,
                    'sks' => $validated['sks'],
                    'angka_mutu' => $validated['angka_mutu'],
                    'huruf_mutu' => $validated['huruf_mutu'],
                    'is_final' => $validated['is_final'],
                ]);
            }

            $this->upsertNilaiRevisi($nilai);
        });

        session()->flash('status', $this->nilaiId ? 'Data nilai diperbarui.' : 'Data nilai dibuat.');

        return redirect($this->detailUrl);
    }

    /**
     * Sama persis dengan NilaiController::upsertNilaiRevisiForAdmin.
     */
    private function upsertNilaiRevisi(Nilai $nilai): void
    {
        $hurufRaw = $nilai->huruf_mutu;
        if ($hurufRaw === null || $hurufRaw === '') {
            return;
        }

        $huruf = strtoupper(trim((string) $hurufRaw));
        $user = Auth::user();
        $by = $user ? ($user->name ?? (string) $user->id) : 'system';
        $keterangan = $this->keterangan_revisi !== '' ? $this->keterangan_revisi : null;

        $rev = NilaiRevisi::withTrashed()
            ->where('id_krs', $nilai->id_krs)
            ->where('huruf_mutu', $huruf)
            ->first();

        if ($rev) {
            if ($rev->trashed()) {
                $rev->restore();
            }
            $rev->angka_mutu = $nilai->angka_mutu;
            $rev->keterangan = $keterangan;
            $rev->updated_by = $by;
            $rev->save();
        } else {
            NilaiRevisi::create([
                'id_krs' => $nilai->id_krs,
                'huruf_mutu' => $huruf,
                'angka_mutu' => $nilai->angka_mutu,
                'keterangan' => $keterangan,
                'created_by' => $by,
            ]);
        }

        $count = NilaiRevisi::where('id_krs', $nilai->id_krs)->whereNull('deleted_at')->count();
        $nilai->revisi = $count;
        $nilai->saveQuietly();
    }

    public function render()
    {
        return view('livewire.admin.nilai.form')->extends('layouts.web');
    }
}
