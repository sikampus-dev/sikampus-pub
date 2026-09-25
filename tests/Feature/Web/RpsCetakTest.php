<?php

use App\Models\Dosen;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\Rps;
use App\Models\RpsCpl;
use App\Models\RpsCpmk;
use App\Models\RpsPembelajaran;
use App\Models\RpsSubcpmk;
use App\Models\User;

it('redirects unauthenticated users to the login page', function () {
    $kelas = Kelas::factory()->create();

    $this->get(route('dosen.rps.pdf', $kelas->id))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);
    $kelas = Kelas::factory()->create();

    $this->actingAs($mahasiswa)->get(route('dosen.rps.pdf', $kelas->id))->assertForbidden();
});

it('forbids a dosen who teaches the kelas but is not pic', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create();
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => false]);

    $this->actingAs($dosenUser)->get(route('dosen.rps.pdf', $kelas->id))->assertForbidden();
});

it('streams a PDF download for the pic dosen even when the kelas has no rps yet', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create(['kode' => 'KLS-RPS01']);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);

    $response = $this->actingAs($dosenUser)->get(route('dosen.rps.pdf', $kelas->id));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('RPS_');
    expect($response->headers->get('Content-Disposition'))->toContain('KLS-RPS01');
});

it('streams a PDF including cpl, cpmk, sub-cpmk, and rencana pembelajaran once the rps is filled in', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create();
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);

    $rps = Rps::create([
        'id_kelas' => $kelas->id,
        'deskripsi_matkul' => 'Mata kuliah pengantar pemrograman.',
        'model_pembelajaran' => 'Blended learning.',
        'media_perangkat_lunak' => 'IDE, compiler.',
        'media_perangkat_keras' => 'Laptop, proyektor.',
        'pustaka_utama' => 'Buku A.',
        'pustaka_pendukung' => 'Buku B.',
        'created_by' => 'Dr. Budi Santoso',
    ]);
    RpsCpl::create(['id_rps' => $rps->id, 'cpl' => 'Mampu berpikir komputasional']);
    $cpmk = RpsCpmk::create(['id_rps' => $rps->id, 'cpmk' => 'Mampu menerapkan struktur data']);
    RpsSubcpmk::create(['id_cpmk' => $cpmk->id, 'subcpmk' => 'Mampu mengimplementasikan linked list']);
    RpsPembelajaran::create([
        'id_rps' => $rps->id,
        'urutan_pertemuan' => 1,
        'materi' => 'Pengenalan array',
        'bobot' => 10,
    ]);

    $response = $this->actingAs($dosenUser)->get(route('dosen.rps.pdf', $kelas->id));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
});

it('forbids a nonexistent kelas with a 403 because the pic check runs before the kelas lookup', function () {
    $dosenUser = dosenUser();

    // Sama persis dengan JadwalDosenController::downloadRpsPdf: dosenIsPicForKelasRps() dicek
    // lebih dulu (tidak ada baris kelas_dosen untuk kelas yang tidak ada -> gagal), sebelum
    // Kelas::find() sempat mengembalikan 404 — jadi hasilnya 403, bukan 404.
    $this->actingAs($dosenUser)->get(route('dosen.rps.pdf', 999999))->assertStatus(403);
});
