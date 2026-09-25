<?php

use App\Livewire\Prodi\Mahasiswa\Index;
use App\Livewire\Prodi\Mahasiswa\Show;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Kelas;
use App\Models\KelompokKelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Nilai;
use App\Models\Pembayaran;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StatusAkademik;
use App\Models\Tagihan;
use Livewire\Livewire;

it('lists only mahasiswa within the kaprodi/sekprodi scope', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    Mahasiswa::factory()->create(['id_prodi' => $prodiA->id, 'nama' => 'Mahasiswa Prodi A']);
    Mahasiswa::factory()->create(['id_prodi' => $prodiB->id, 'nama' => 'Mahasiswa Prodi B']);

    $kaprodi = kaprodiUser($prodiA);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->assertSee('Mahasiswa Prodi A')
        ->assertDontSee('Mahasiswa Prodi B');
});

it('filters by search, semester masuk, kelompok kelas, and status akademik', function () {
    $prodi = Prodi::factory()->create();
    $semesterA = Semester::factory()->create();
    $semesterB = Semester::factory()->create();
    $kelompokA = KelompokKelas::factory()->create(['nama' => 'Kelompok Alpha']);
    $kelompokB = KelompokKelas::factory()->create(['nama' => 'Kelompok Beta']);
    $statusA = StatusAkademik::factory()->create(['nama' => 'Aktif']);
    $statusB = StatusAkademik::factory()->create(['nama' => 'Cuti']);

    Mahasiswa::factory()->create([
        'id_prodi' => $prodi->id, 'nama' => 'Mahasiswa Satu', 'nim' => '2024001',
        'id_semester_masuk' => $semesterA->id, 'id_kelompok_kelas' => $kelompokA->id, 'id_status_akademik' => $statusA->id,
    ]);
    Mahasiswa::factory()->create([
        'id_prodi' => $prodi->id, 'nama' => 'Mahasiswa Dua', 'nim' => '2024002',
        'id_semester_masuk' => $semesterB->id, 'id_kelompok_kelas' => $kelompokB->id, 'id_status_akademik' => $statusB->id,
    ]);
    $kaprodi = kaprodiUser($prodi);

    $component = Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->set('search', '2024001')
        ->assertSee('Mahasiswa Satu')
        ->assertDontSee('Mahasiswa Dua');

    $component
        ->set('search', '')
        ->set('filterSemesterMasuk', (string) $semesterB->id)
        ->assertSee('Mahasiswa Dua')
        ->assertDontSee('Mahasiswa Satu');

    $component
        ->set('filterSemesterMasuk', '')
        ->set('filterKelompokKelas', (string) $kelompokA->id)
        ->assertSee('Mahasiswa Satu')
        ->assertDontSee('Mahasiswa Dua');

    $component
        ->set('filterKelompokKelas', '')
        ->set('filterStatusAkademik', (string) $statusB->id)
        ->assertSee('Mahasiswa Dua')
        ->assertDontSee('Mahasiswa Satu');
});

it('has no create, edit, or delete actions available (read-only portal)', function () {
    $prodi = Prodi::factory()->create();
    Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $kaprodi = kaprodiUser($prodi);

    $html = $this->actingAs($kaprodi)->get(route('prodi.mahasiswa'))->getContent();

    expect($html)->not->toContain('wire:click="confirmDelete');
    expect($html)->not->toContain('Tambah Mahasiswa');
});

it('shows mahasiswa detail with biodata and dosen wali within scope', function () {
    $prodi = Prodi::factory()->create();
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Citra Lestari', 'nim' => '2024077']);
    $dosen = Dosen::factory()->create(['nama' => 'Dr. Wali Pembimbing', 'nidn' => '0011223344']);
    DosenWali::factory()->create(['id_mahasiswa' => $mhs->id, 'id_dosen' => $dosen->id, 'status' => 'active']);
    $kaprodi = kaprodiUser($prodi);

    $this->actingAs($kaprodi)
        ->get(route('prodi.mahasiswa.show', $mhs->id))
        ->assertOk()
        ->assertSee('Citra Lestari')
        ->assertSee('2024077')
        ->assertSee('Dr. Wali Pembimbing')
        ->assertSee('0011223344');
});

it('shows "belum ada dosen wali" when no active dosen wali is assigned', function () {
    $prodi = Prodi::factory()->create();
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $kaprodi = kaprodiUser($prodi);

    $this->actingAs($kaprodi)
        ->get(route('prodi.mahasiswa.show', $mhs->id))
        ->assertSee('Belum ada dosen wali');
});

it('forbids viewing a mahasiswa outside the kaprodi/sekprodi scope with a 403 (mahasiswa exists, just not in scope)', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mhsB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $kaprodi = kaprodiUser($prodiA);

    $this->actingAs($kaprodi)
        ->get(route('prodi.mahasiswa.show', $mhsB->id))
        ->assertStatus(403);
});

it('returns a 404 for a mahasiswa id that does not exist at all', function () {
    $prodi = Prodi::factory()->create();
    $kaprodi = kaprodiUser($prodi);

    $this->actingAs($kaprodi)
        ->get(route('prodi.mahasiswa.show', 999999))
        ->assertStatus(404);
});

it('groups nilai by semester, computes ip, and only counts approved krs', function () {
    $prodi = Prodi::factory()->create();
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $semester = Semester::factory()->create(['kode' => '20241', 'nama' => '2024 Ganjil']);
    $matkulA = Matkul::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Kalkulus', 'sks' => 3]);
    $matkulB = Matkul::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Fisika', 'sks' => 2]);
    $kmA = KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id, 'sks' => 3]);
    $kmB = KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id, 'sks' => 2]);
    $kelasA = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $kmA->id, 'id_semester' => $semester->id]);
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $kmB->id, 'id_semester' => $semester->id]);
    $krsApproved = Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelasA->id, 'approved_at' => now()]);
    Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelasB->id, 'approved_at' => null]);
    Nilai::factory()->create(['id_krs' => $krsApproved->id, 'sks' => 3, 'angka_mutu' => 4, 'huruf_mutu' => 'A']);
    $kaprodi = kaprodiUser($prodi);

    $component = Livewire::actingAs($kaprodi)->test(Show::class, ['id' => $mhs->id])->call('setTab', 'nilai');

    $groups = $component->instance()->nilaiBySemester();
    expect($groups)->toHaveCount(1);
    $group = $groups->first();
    expect($group['nilai_list'])->toHaveCount(1); // krs yang belum di-acc tidak diikutkan
    expect($group['total_sks'])->toBe(3);
    expect($group['ip'])->toBe(4.0);

    $component->assertSee('Kalkulus')->assertDontSee('Fisika');
});

it('groups tagihan by semester and only sums approved pembayaran', function () {
    $prodi = Prodi::factory()->create();
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $semester = Semester::factory()->create(['kode' => '20241', 'nama' => '2024 Ganjil']);
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mhs->id, 'id_semester' => $semester->id, 'total' => 1000000, 'no_tagihan' => 'TGH-TEST01']);
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 400000, 'approved_at' => now()]);
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 300000, 'approved_at' => null]);
    $kaprodi = kaprodiUser($prodi);

    $component = Livewire::actingAs($kaprodi)->test(Show::class, ['id' => $mhs->id])->call('setTab', 'tagihan');

    $groups = $component->instance()->tagihanBySemester();
    expect($groups)->toHaveCount(1);
    $group = $groups->first();
    expect($group['total_tagihan_semester'])->toBe(1000000.0);
    expect($group['total_pembayaran_semester'])->toBe(400000.0); // pembayaran belum approve tidak dihitung
    expect($group['tagihan_list'][0]['sisa_tagihan'])->toBe(600000.0);

    $component->assertSee('TGH-TEST01');
});

it('has no edit or delete actions on the detail page', function () {
    $prodi = Prodi::factory()->create();
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $kaprodi = kaprodiUser($prodi);

    $html = $this->actingAs($kaprodi)->get(route('prodi.mahasiswa.show', $mhs->id))->getContent();

    expect($html)->not->toContain('wire:click="deleteMahasiswa"');
    expect($html)->not->toContain('wire:click="confirmDeleteMahasiswa"');

    $component = Livewire::actingAs($kaprodi)->test(Show::class, ['id' => $mhs->id]);
    expect(method_exists($component->instance(), 'deleteMahasiswa'))->toBeFalse();
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('prodi.mahasiswa'))->assertRedirect(route('login'));
});

it('orders tagihan and nilai semester groups by kode, newest first, regardless of semester id order', function () {
    $prodi = Prodi::factory()->create();
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);

    // Semester paling lama dibuat terakhir sehingga id-nya paling besar; sort per id akan salah.
    $baru = Semester::factory()->create(['kode' => '20252', 'nama' => '2025 Genap']);
    $tengah = Semester::factory()->create(['kode' => '20241', 'nama' => '2024 Ganjil']);
    $lama = Semester::factory()->create(['kode' => '20232', 'nama' => '2023 Genap']);
    expect($lama->id)->toBeGreaterThan($baru->id);

    foreach ([$lama, $tengah, $baru] as $i => $semester) {
        Tagihan::factory()->create([
            'id_mahasiswa' => $mhs->id,
            'id_semester' => $semester->id,
            'total' => 1000000,
            'no_tagihan' => 'TGH-URUT'.$i,
        ]);

        $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id, 'sks' => 3]);
        $km = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id, 'sks' => 3]);
        $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $km->id, 'id_semester' => $semester->id]);
        $krs = Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id, 'approved_at' => now()]);
        Nilai::factory()->create(['id_krs' => $krs->id, 'angka_mutu' => 4, 'huruf_mutu' => 'A', 'is_final' => true]);
    }

    $component = Livewire::actingAs(kaprodiUser($prodi))->test(Show::class, ['id' => $mhs->id]);

    $kodeTagihan = collect($component->instance()->tagihanBySemester())->pluck('semester.kode')->all();
    expect($kodeTagihan)->toBe(['20252', '20241', '20232']);

    $kodeNilai = collect($component->instance()->nilaiBySemester())->pluck('semester.kode')->all();
    expect($kodeNilai)->toBe(['20252', '20241', '20232']);
});
