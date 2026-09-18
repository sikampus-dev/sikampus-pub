<?php

use App\Livewire\Mahasiswa\Biodata\Form as BiodataForm;
use App\Models\Jenjang;
use App\Models\Kota;
use App\Models\Mahasiswa;
use App\Models\Pekerjaan;
use App\Models\Pendidikan;
use App\Models\Penghasilan;
use App\Models\Prodi;
use App\Models\StatusAkademik;
use App\Models\User;
use Livewire\Livewire;

function biodataMahasiswaUser(array $mahasiswaAttributes = []): array
{
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(array_merge(['id_user' => $user->id], $mahasiswaAttributes));

    return [$user, $mahasiswa];
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('mahasiswa.biodata'))->assertRedirect(route('login'));
    $this->get(route('mahasiswa.biodata.edit'))->assertRedirect(route('login'));
});

it('forbids a non-mahasiswa user', function () {
    $dosen = User::factory()->create(['role' => 'dosen']);

    $this->actingAs($dosen)->get(route('mahasiswa.biodata'))->assertForbidden();
    $this->actingAs($dosen)->get(route('mahasiswa.biodata.edit'))->assertForbidden();
});

it('shows the full biodata of the logged in mahasiswa, including related reference data', function () {
    $jenjang = Jenjang::factory()->create(['nama' => 'Sarjana']);
    $prodi = Prodi::factory()->create(['nama' => 'Teknik Informatika', 'id_jenjang' => $jenjang->id]);
    $statusAkademik = StatusAkademik::factory()->create(['nama' => 'Aktif Kuliah']);
    $kota = Kota::factory()->create(['nama' => 'Kota Bogor']);

    [$user] = biodataMahasiswaUser([
        'nim' => '2099001',
        'nama' => 'Mahasiswa Uji',
        'id_prodi' => $prodi->id,
        'id_status_akademik' => $statusAkademik->id,
        'id_kota' => $kota->id,
        'sekolah_asal' => 'SMAN 1 Bogor',
        'ayah' => 'Bapak Uji',
        'ibu' => 'Ibu Uji',
    ]);

    $this->actingAs($user)->get(route('mahasiswa.biodata'))
        ->assertOk()
        ->assertSee('2099001')
        ->assertSee('Mahasiswa Uji')
        ->assertSee('Teknik Informatika')
        ->assertSee('Sarjana')
        ->assertSee('Aktif Kuliah')
        ->assertSee('Kota Bogor')
        ->assertSee('SMAN 1 Bogor')
        ->assertSee('Bapak Uji')
        ->assertSee('Ibu Uji')
        ->assertSee('Edit Biodata');
});

it('prefills the edit form from the linked mahasiswa record', function () {
    [$user] = biodataMahasiswaUser([
        'nim' => '2099002',
        'nama' => 'Mahasiswa Edit',
        'jenis_kelamin' => 'P',
        'tanggal_lahir' => '2003-04-05',
        'kelurahan' => 'Sukamaju',
    ]);

    Livewire::actingAs($user)
        ->test(BiodataForm::class)
        ->assertSet('nim', '2099002')
        ->assertSet('nama', 'Mahasiswa Edit')
        ->assertSet('jenis_kelamin', 'P')
        ->assertSet('tanggal_lahir', '2003-04-05')
        ->assertSet('kelurahan', 'Sukamaju');
});

it('saves the full biodata and redirects back to the biodata page', function () {
    $kota = Kota::factory()->create();
    [$user, $mahasiswa] = biodataMahasiswaUser();

    Livewire::actingAs($user)
        ->test(BiodataForm::class)
        ->set('nama', 'Nama Biodata Baru')
        ->set('email', 'biodata-baru@example.test')
        ->set('no_wa', '08120001')
        ->set('handphone', '08120002')
        ->set('jenis_kelamin', 'L')
        ->set('id_tempat_lahir', 'Bogor')
        ->set('tanggal_lahir', '2002-01-31')
        ->set('no_ktp', '3201010101020003')
        ->set('alamat', 'Jl. Merdeka No. 10')
        ->set('rt', '004')
        ->set('rw', '007')
        ->set('dusun', 'Dusun Satu')
        ->set('kelurahan', 'Kelurahan Dua')
        ->set('id_kecamatan', 'Kecamatan Tiga')
        ->set('kode_pos', '16110')
        ->set('id_kota', (string) $kota->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('mahasiswa.biodata'));

    $mahasiswa->refresh();
    expect($mahasiswa->nama)->toBe('Nama Biodata Baru');
    expect($mahasiswa->email)->toBe('biodata-baru@example.test');
    expect($mahasiswa->jenis_kelamin)->toBe('L');
    expect($mahasiswa->id_tempat_lahir)->toBe('Bogor');
    expect($mahasiswa->tanggal_lahir->format('Y-m-d'))->toBe('2002-01-31');
    expect($mahasiswa->no_ktp)->toBe('3201010101020003');
    expect($mahasiswa->alamat)->toBe('Jl. Merdeka No. 10');
    expect($mahasiswa->rt)->toBe('004');
    expect($mahasiswa->kelurahan)->toBe('Kelurahan Dua');
    expect($mahasiswa->id_kecamatan)->toBe('Kecamatan Tiga');
    expect($mahasiswa->kode_pos)->toBe('16110');
    expect((int) $mahasiswa->id_kota)->toBe($kota->id);
});

it('stores an emptied optional field as null instead of an empty string', function () {
    [$user, $mahasiswa] = biodataMahasiswaUser(['dusun' => 'Dusun Lama', 'no_ktp' => '123']);

    Livewire::actingAs($user)
        ->test(BiodataForm::class)
        ->set('dusun', '')
        ->set('no_ktp', '')
        ->call('save')
        ->assertHasNoErrors();

    $mahasiswa->refresh();
    expect($mahasiswa->dusun)->toBeNull();
    expect($mahasiswa->no_ktp)->toBeNull();
});

it('requires a nama and rejects an invalid jenis kelamin', function () {
    [$user] = biodataMahasiswaUser();

    Livewire::actingAs($user)
        ->test(BiodataForm::class)
        ->set('nama', '')
        ->set('jenis_kelamin', 'X')
        ->call('save')
        ->assertHasErrors(['nama', 'jenis_kelamin']);
});

it('rejects an email already used by another mahasiswa', function () {
    Mahasiswa::factory()->create(['email' => 'sudah-dipakai@example.test']);
    [$user] = biodataMahasiswaUser();

    Livewire::actingAs($user)
        ->test(BiodataForm::class)
        ->set('email', 'sudah-dipakai@example.test')
        ->call('save')
        ->assertHasErrors(['email']);
});

it('prefills and saves the sekolah asal and nis fields', function () {
    [$user, $mahasiswa] = biodataMahasiswaUser(['sekolah_asal' => 'SMAN 2 Lama', 'nis' => '9911']);

    Livewire::actingAs($user)
        ->test(BiodataForm::class)
        ->assertSet('sekolah_asal', 'SMAN 2 Lama')
        ->assertSet('nis', '9911')
        ->set('sekolah_asal', 'SMAN 5 Baru')
        ->set('nis', '20250099')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('mahasiswa.biodata'));

    $mahasiswa->refresh();
    expect($mahasiswa->sekolah_asal)->toBe('SMAN 5 Baru');
    expect($mahasiswa->nis)->toBe('20250099');
});

it('rejects a sekolah asal or nis longer than the column allows', function () {
    [$user] = biodataMahasiswaUser();

    Livewire::actingAs($user)
        ->test(BiodataForm::class)
        ->set('sekolah_asal', str_repeat('a', 256))
        ->set('nis', str_repeat('9', 51))
        ->call('save')
        ->assertHasErrors(['sekolah_asal', 'nis']);
});

it('saves orang tua and wali data, including the parent name fields', function () {
    $pendidikan = Pendidikan::create(['nama' => 'SMA']);
    $pekerjaan = Pekerjaan::create(['nama' => 'Wiraswasta']);
    $penghasilan = Penghasilan::create(['nama' => 'Rp2-5 juta']);
    [$user, $mahasiswa] = biodataMahasiswaUser();

    Livewire::actingAs($user)
        ->test(BiodataForm::class)
        ->set('ayah', 'Bapak Uji')
        ->set('nik_ayah', '3201010101700001')
        ->set('tgl_lahir_ayah', '1970-01-01')
        ->set('id_pddk_ayah', (string) $pendidikan->id)
        ->set('id_pekerjaan_ayah', (string) $pekerjaan->id)
        ->set('id_penghasilan_ayah', (string) $penghasilan->id)
        ->set('ibu', 'Ibu Uji')
        ->set('nik_ibu', '3201010101750002')
        ->set('tgl_lahir_ibu', '1975-02-02')
        ->set('wali', 'Wali Uji')
        ->set('nik_wali', '3201010101650003')
        ->set('tgl_lahir_wali', '1965-03-03')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('mahasiswa.biodata'));

    $mahasiswa->refresh();
    expect($mahasiswa->ayah)->toBe('Bapak Uji');
    expect($mahasiswa->nik_ayah)->toBe('3201010101700001');
    expect($mahasiswa->tgl_lahir_ayah->format('Y-m-d'))->toBe('1970-01-01');
    expect($mahasiswa->id_pddk_ayah)->toBe($pendidikan->id);
    expect($mahasiswa->id_pekerjaan_ayah)->toBe($pekerjaan->id);
    expect($mahasiswa->id_penghasilan_ayah)->toBe($penghasilan->id);
    expect($mahasiswa->ibu)->toBe('Ibu Uji');
    expect($mahasiswa->tgl_lahir_ibu->format('Y-m-d'))->toBe('1975-02-02');
    expect($mahasiswa->wali)->toBe('Wali Uji');
    expect($mahasiswa->tgl_lahir_wali->format('Y-m-d'))->toBe('1965-03-03');
});

it('prefills the orang tua and wali fields on the edit form', function () {
    $pendidikan = Pendidikan::create(['nama' => 'SMA']);
    [$user] = biodataMahasiswaUser([
        'ayah' => 'Ayah Lama',
        'ibu' => 'Ibu Lama',
        'tgl_lahir_ayah' => '1968-07-09',
        'id_pddk_ayah' => $pendidikan->id,
    ]);

    Livewire::actingAs($user)
        ->test(BiodataForm::class)
        ->assertSet('ayah', 'Ayah Lama')
        ->assertSet('ibu', 'Ibu Lama')
        ->assertSet('tgl_lahir_ayah', '1968-07-09')
        ->assertSet('id_pddk_ayah', (string) $pendidikan->id);
});

it('clears an emptied orang tua dropdown back to null', function () {
    $pendidikan = Pendidikan::create(['nama' => 'SMA']);
    [$user, $mahasiswa] = biodataMahasiswaUser(['id_pddk_ayah' => $pendidikan->id, 'ayah' => 'Ayah Lama']);

    Livewire::actingAs($user)
        ->test(BiodataForm::class)
        ->set('id_pddk_ayah', '')
        ->set('ayah', '')
        ->call('save')
        ->assertHasNoErrors();

    $mahasiswa->refresh();
    expect($mahasiswa->id_pddk_ayah)->toBeNull();
    expect($mahasiswa->ayah)->toBeNull();
});

it('keeps its own email valid on resubmit', function () {
    [$user] = biodataMahasiswaUser(['email' => 'punya-sendiri@example.test']);

    Livewire::actingAs($user)
        ->test(BiodataForm::class)
        ->set('nama', 'Nama Tetap')
        ->call('save')
        ->assertHasNoErrors();
});
