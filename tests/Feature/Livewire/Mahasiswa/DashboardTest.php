<?php

use App\Livewire\Mahasiswa\Dashboard;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Ktm;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Nilai;
use App\Models\Pengumuman;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;

function dashboardMahasiswaUser(array $mahasiswaAttributes = []): array
{
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(array_merge(['id_user' => $user->id], $mahasiswaAttributes));

    return [$user, $mahasiswa];
}

function nilaiKrsUntukDashboard(Mahasiswa $mahasiswa, Semester $semester, array $nilaiAttrs = []): void
{
    $matkul = Matkul::factory()->create(['sks' => 3]);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create(['id_kurikulum_matkul' => $kurikulumMatkul->id, 'id_semester' => $semester->id]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id, 'approved_at' => now()]);
    Nilai::factory()->create(array_merge(['id_krs' => $krs->id, 'is_final' => true], $nilaiAttrs));
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('mahasiswa.dashboard'))->assertRedirect(route('login'));
});

it('forbids a non-mahasiswa user', function () {
    $dosen = User::factory()->create(['role' => 'dosen']);

    $this->actingAs($dosen)->get(route('mahasiswa.dashboard'))->assertForbidden();
});

it('renders the dashboard with the linked mahasiswa academic info', function () {
    [$user, $mahasiswa] = dashboardMahasiswaUser(['nim' => '2099001', 'nama' => 'Mahasiswa Uji']);

    $this->actingAs($user)->get(route('mahasiswa.dashboard'))
        ->assertOk()
        ->assertSee('2099001')
        ->assertSee('Kartu Rencana Studi')
        ->assertSee('Kartu Tanda Mahasiswa');
});

it('shows the active dosen wali name, hiding an inactive assignment', function () {
    [$user, $mahasiswa] = dashboardMahasiswaUser();
    $dosenAktif = Dosen::factory()->create(['nama' => 'Dosen Wali Aktif']);
    $dosenNonaktif = Dosen::factory()->create(['nama' => 'Dosen Wali Lama']);
    DosenWali::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_dosen' => $dosenAktif->id, 'status' => 'active']);
    DosenWali::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_dosen' => $dosenNonaktif->id, 'status' => 'inactive']);

    $this->actingAs($user)->get(route('mahasiswa.dashboard'))
        ->assertOk()
        ->assertSee('Dosen Wali Aktif')
        ->assertDontSee('Dosen Wali Lama');
});

it('computes ip per semester only from approved krs with final grades', function () {
    [$user, $mahasiswa] = dashboardMahasiswaUser();
    $semester = Semester::factory()->create(['kode' => '20241', 'nama' => 'Ganjil 2024/2025']);
    nilaiKrsUntukDashboard($mahasiswa, $semester, ['angka_mutu' => 4, 'is_final' => true]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('20241');
});

it('lists active pengumuman aimed at mahasiswa and opens the detail modal from the lihat button', function () {
    [$user] = dashboardMahasiswaUser();
    // Isi sengaja > 50 karakter: daftar hanya menampilkan potongannya, jadi teks penuh di bawah
    // benar-benar membuktikan modalnya terbuka, bukan sekadar terbaca dari kartu daftar.
    $isiPenuh = 'Isi lengkap pengumuman uji yang panjangnya lebih dari lima puluh karakter.';
    $pengumuman = Pengumuman::factory()->create([
        'judul' => 'Pengumuman Uji',
        'isi' => $isiPenuh,
        'audien' => 'mahasiswa',
        'prioritas' => 'high',
        'tanggal_mulai' => now()->subDay(),
        'tanggal_selesai' => now()->addDay(),
    ]);
    Pengumuman::factory()->create(['audien' => 'dosen', 'tanggal_mulai' => now()->subDay(), 'tanggal_selesai' => now()->addDay()]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Pengumuman Uji')
        ->assertSee('Lihat detail')
        ->assertDontSee($isiPenuh)
        ->call('showPengumuman', $pengumuman->id)
        ->assertSet('selectedPengumumanId', $pengumuman->id)
        ->assertSee($isiPenuh);
});

it('keeps the pengumuman modal open across re-renders and closes it only when asked', function () {
    // Regresi: modal dulu dibuka lewat <dialog>.showModal() dari onclick sementara isinya diisi
    // state Livewire. Render ulang berikutnya mengganti elemen <dialog> dan status modal native
    // hilang, jadi modal tertutup sendiri sedetik setelah dibuka. Sekarang statusnya hanya
    // ditentukan selectedPengumumanId, jadi render ulang tidak boleh menutupnya.
    [$user] = dashboardMahasiswaUser();
    $isiPenuh = 'Isi pengumuman yang harus tetap terlihat setelah komponen dirender ulang berkali-kali.';
    $pengumuman = Pengumuman::factory()->create([
        'judul' => 'Pengumuman Tetap',
        'isi' => $isiPenuh,
        'audien' => 'mahasiswa',
        'tanggal_mulai' => now()->subDay(),
        'tanggal_selesai' => now()->addDay(),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('showPengumuman', $pengumuman->id)
        ->assertSee($isiPenuh)
        // Render ulang tanpa menyentuh modal — dulu inilah yang menutupnya sendiri.
        ->call('$refresh')
        ->assertSet('selectedPengumumanId', $pengumuman->id)
        ->assertSee($isiPenuh)
        ->call('closePengumuman')
        ->assertSet('selectedPengumumanId', null)
        ->assertDontSee($isiPenuh);
});

it('shows a ktm preview with a manage link when the mahasiswa already has one', function () {
    [$user, $mahasiswa] = dashboardMahasiswaUser();
    Ktm::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'file' => 'ktm/foto/dummy.png', 'status' => 'active']);

    $this->actingAs($user)->get(route('mahasiswa.dashboard'))
        ->assertOk()
        ->assertSee('Kelola KTM');
});

it('shows a call to action to create a ktm when the mahasiswa has none', function () {
    [$user] = dashboardMahasiswaUser();

    $this->actingAs($user)->get(route('mahasiswa.dashboard'))
        ->assertOk()
        ->assertSee('Buat KTM');
});

it('orders the ip per semester chart by kode, oldest first, regardless of semester id order', function () {
    [$user, $mahasiswa] = dashboardMahasiswaUser();

    // Semester paling lama dibuat terakhir sehingga id-nya paling besar; sort per id akan
    // menaruhnya di ujung kanan grafik, padahal seharusnya paling kiri.
    $baru = Semester::factory()->create(['kode' => '20252', 'nama' => 'Genap 2025']);
    $tengah = Semester::factory()->create(['kode' => '20241', 'nama' => 'Ganjil 2024']);
    $lama = Semester::factory()->create(['kode' => '20232', 'nama' => 'Genap 2023']);
    expect($lama->id)->toBeGreaterThan($baru->id);

    foreach ([$lama, $tengah, $baru] as $semester) {
        nilaiKrsUntukDashboard($mahasiswa, $semester, ['angka_mutu' => 4, 'is_final' => true]);
    }

    $chart = Livewire::actingAs($user)->test(Dashboard::class)->instance()->ipPerSemester();

    expect(array_map(fn ($row) => $row['semester']->kode, $chart))->toBe(['20232', '20241', '20252']);
});
