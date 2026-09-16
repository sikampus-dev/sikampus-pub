<?php

use App\Livewire\Admin\JadwalUjian\Form;
use App\Models\Kelas;
use App\Models\Ruangan;
use App\Models\Ujian;
use Livewire\Livewire;

/**
 * Unique `ujian_unique` (id_kelas, id_semester, jenis_ujian) TIDAK menyertakan deleted_at,
 * sedangkan Ujian memakai SoftDeletes — baris terhapus tetap menduduki kombinasinya. Cek duplikat
 * lewat Ujian::query() biasa tidak melihatnya, lolos, lalu menabrak constraint sebagai 500.
 */
function ujianTerhapus(Kelas $kelas, string $jenis = 'UTS', array $atribut = []): Ujian
{
    $ujian = Ujian::create(array_merge([
        'id_kelas' => $kelas->id,
        'id_semester' => $kelas->id_semester,
        'jenis_ujian' => $jenis,
    ], $atribut));
    $ujian->update(['deleted_by' => 'Admin Lama']);
    $ujian->delete();

    return $ujian;
}

it('menawarkan pulihkan atau hapus permanen saat bentrok dengan jadwal terhapus', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $terhapus = ujianTerhapus($kelas);

    Livewire::actingAs($admin)->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('jenis_ujian', 'UTS')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('duplikatTerhapusId', $terhapus->id)
        ->assertSee('Jadwal ujian yang terhapus memakai kombinasi ini')
        ->assertSee('Pulihkan jadwal lama')
        ->assertSee('Hapus permanen');

    // Belum ada yang tersimpan sebelum admin memutuskan.
    expect(Ujian::count())->toBe(0);
});

it('memulihkan jadwal lama apa adanya dan mengabaikan isian form', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $ruanganLama = Ruangan::factory()->create();
    $ruanganBaru = Ruangan::factory()->create();
    $terhapus = ujianTerhapus($kelas, 'UTS', [
        'id_ruangan' => $ruanganLama->id,
        'tanggal_mulai' => '2026-01-10 08:00:00',
    ]);

    Livewire::actingAs($admin)->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('jenis_ujian', 'UTS')
        ->set('id_ruangan', $ruanganBaru->id)
        ->set('tanggal_mulai', '2026-01-12T09:00')
        ->call('save')
        ->call('pulihkanDuplikat');

    $pulih = Ujian::findOrFail($terhapus->id);
    expect($pulih->trashed())->toBeFalse()
        ->and($pulih->deleted_by)->toBeNull()
        // Isian form sengaja diabaikan — "pulihkan" mengembalikan jadwal lama utuh.
        ->and((int) $pulih->id_ruangan)->toBe($ruanganLama->id)
        ->and($pulih->tanggal_mulai->format('Y-m-d H:i'))->toBe('2026-01-10 08:00')
        ->and(Ujian::count())->toBe(1);
});

it('menghapus permanen jadwal lama lalu menyimpan isian form sebagai jadwal baru', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $ruanganBaru = Ruangan::factory()->create();
    $terhapus = ujianTerhapus($kelas, 'UTS', ['tanggal_mulai' => '2026-01-10 08:00:00']);

    Livewire::actingAs($admin)->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('jenis_ujian', 'UTS')
        ->set('id_ruangan', $ruanganBaru->id)
        ->set('tanggal_mulai', '2026-01-12T09:00')
        ->call('save')
        ->call('hapusPermanenDuplikat');

    expect(Ujian::withTrashed()->find($terhapus->id))->toBeNull();

    $baru = Ujian::where('id_kelas', $kelas->id)->firstOrFail();
    expect((int) $baru->id_ruangan)->toBe($ruanganBaru->id)
        ->and($baru->tanggal_mulai->format('Y-m-d H:i'))->toBe('2026-01-12 09:00');
});

it('menawarkan hal yang sama saat form edit diubah ke kombinasi milik jadwal terhapus', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $terhapus = ujianTerhapus($kelas, 'UAS');
    $hidup = Ujian::create([
        'id_kelas' => $kelas->id,
        'id_semester' => $kelas->id_semester,
        'jenis_ujian' => 'UTS',
    ]);

    Livewire::actingAs($admin)->test(Form::class, ['id' => $hidup->id])
        ->set('jenis_ujian', 'UAS')
        ->call('save')
        ->assertSet('duplikatTerhapusId', $terhapus->id);

    // Baris yang sedang diedit tidak ikut berubah sebelum admin memutuskan.
    expect($hidup->fresh()->jenis_ujian)->toBe('UTS');
});

it('tetap menolak dengan pesan biasa saat bentrok dengan jadwal yang masih hidup', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    Ujian::create(['id_kelas' => $kelas->id, 'id_semester' => $kelas->id_semester, 'jenis_ujian' => 'UTS']);

    Livewire::actingAs($admin)->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('jenis_ujian', 'UTS')
        ->call('save')
        ->assertHasErrors('id_kelas')
        // Bentrok dengan jadwal hidup tidak menawarkan pulihkan/hapus permanen.
        ->assertSet('duplikatTerhapusId', null);
});

it('menolak aksi pada id duplikat yang dikarang dari klien', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    Livewire::actingAs($admin)->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('duplikatTerhapusId', 999999)
        ->call('pulihkanDuplikat')
        ->assertHasErrors('id_kelas')
        ->assertSet('duplikatTerhapusId', null);
});

it('menjawab 409 beserta id jadwal terhapus lewat API, bukan 500', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $terhapus = ujianTerhapus($kelas);

    $this->actingAs($admin)->postJson('/api/ujian', [
        'id_kelas' => $kelas->id,
        'jenis_ujian' => 'UTS',
    ])
        ->assertStatus(409)
        ->assertJsonPath('duplikat_terhapus.id', $terhapus->id)
        ->assertJsonPath('duplikat_terhapus.deleted_by', 'Admin Lama');
});

it('tetap menjawab 422 lewat API saat bentrok dengan jadwal yang masih hidup', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    Ujian::create(['id_kelas' => $kelas->id, 'id_semester' => $kelas->id_semester, 'jenis_ujian' => 'UTS']);

    $this->actingAs($admin)->postJson('/api/ujian', [
        'id_kelas' => $kelas->id,
        'jenis_ujian' => 'UTS',
    ])->assertStatus(422)->assertJsonMissingPath('duplikat_terhapus');
});

it('menjawab 409 lewat API update, bukan 500', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $terhapus = ujianTerhapus($kelas, 'UAS');
    $hidup = Ujian::create([
        'id_kelas' => $kelas->id,
        'id_semester' => $kelas->id_semester,
        'jenis_ujian' => 'UTS',
    ]);

    $this->actingAs($admin)->putJson("/api/ujian/{$hidup->id}", ['jenis_ujian' => 'UAS'])
        ->assertStatus(409)
        ->assertJsonPath('duplikat_terhapus.id', $terhapus->id);
});
