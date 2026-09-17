<?php

use App\Livewire\Admin\Ktm\Form;
use App\Livewire\Admin\Ktm\Index;
use App\Models\Ktm;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Simpan gambar template KTM sungguhan (bukan sekadar file kosong) ke disk 'public' yang
 * di-fake, lalu daftarkan lewat Setting supaya KtmImageGenerator bisa membacanya sebagai
 * gambar valid saat generate/regenerate dipanggil di dalam test.
 */
function seedKtmTemplate(): void
{
    $image = imagecreatetruecolor(200, 114);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    $path = 'ktm/templates/test-template.png';
    Storage::disk('public')->put($path, $contents);

    Setting::create([
        'key' => 'ktm_template',
        'value' => $path,
        'description' => 'Template gambar KTM (admin)',
        'order' => 0,
    ]);
}

beforeEach(function () {
    Storage::fake('public');
});

it('renders the index page with data and template tabs', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create(['nama' => 'Budi Santoso']);
    Ktm::factory()->for(Mahasiswa::factory()->state(['nama' => 'Budi Santoso']), 'mahasiswa')->create();

    $this->actingAs($admin)
        ->get(route('admin.administrasi.ktm'))
        ->assertOk()
        ->assertSee('Data KTM')
        ->assertSee('Template KTM')
        ->assertSee('Budi Santoso');
});

it('uploads a ktm template image', function () {
    $admin = adminUser();

    $image = imagecreatetruecolor(50, 50);
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);
    $tmpPath = tempnam(sys_get_temp_dir(), 'ktm_tpl_').'.png';
    file_put_contents($tmpPath, $contents);
    $file = UploadedFile::fake()->createWithContent('template.png', file_get_contents($tmpPath));

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('activeTab', 'template')
        ->set('templateFile', $file)
        ->assertHasNoErrors();

    $setting = Setting::where('key', 'ktm_template')->firstOrFail();
    Storage::disk('public')->assertExists($setting->value);
});

it('creates a ktm for a mahasiswa and generates the image', function () {
    seedKtmTemplate();
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Citra Lestari', 'nim' => '2024555001']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id, $mahasiswa->nim.' - '.$mahasiswa->nama)
        ->set('nomor_ktm', 'KTM-001')
        ->call('save')
        ->assertRedirect(route('admin.administrasi.ktm'));

    $ktm = Ktm::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    expect($ktm->nomor_ktm)->toBe('KTM-001');
    expect($ktm->status)->toBe('active');
    expect($ktm->file)->not->toBeNull();
    Storage::disk('public')->assertExists($ktm->file);
});

it('rejects creating a second ktm for a mahasiswa that already has one', function () {
    seedKtmTemplate();
    $admin = adminUser();
    $existing = Ktm::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $existing->id_mahasiswa, 'dup')
        ->call('save')
        ->assertHasErrors(['id_mahasiswa' => 'unique']);
});

it('shows an error when creating a ktm without a template configured', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id, 'x')
        ->call('save')
        ->assertHasErrors(['id_mahasiswa']);

    expect(Ktm::count())->toBe(0);
});

it('regenerates the ktm image', function () {
    seedKtmTemplate();
    $admin = adminUser();
    $ktm = Ktm::factory()->create(['file' => 'ktm/old-file.png']);
    Storage::disk('public')->put('ktm/old-file.png', 'old-content');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmRegenerate', $ktm->id)
        ->call('regenerate');

    $ktm->refresh();
    expect($ktm->file)->not->toBe('ktm/old-file.png');
    Storage::disk('public')->assertExists($ktm->file);
    Storage::disk('public')->assertMissing('ktm/old-file.png');
});

it('updates nomor_ktm and status without touching the file', function () {
    $admin = adminUser();
    $ktm = Ktm::factory()->create(['nomor_ktm' => 'OLD', 'status' => 'active', 'file' => 'ktm/keep.png']);
    Storage::disk('public')->put('ktm/keep.png', 'keep');

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $ktm->id])
        ->set('nomor_ktm', 'NEW-001')
        ->set('status', 'inactive')
        ->call('save')
        ->assertRedirect(route('admin.administrasi.ktm'));

    $ktm->refresh();
    expect($ktm->nomor_ktm)->toBe('NEW-001');
    expect($ktm->status)->toBe('inactive');
    expect($ktm->file)->toBe('ktm/keep.png');
});

it('deletes a ktm and its file', function () {
    $admin = adminUser();
    $ktm = Ktm::factory()->create(['file' => 'ktm/to-delete.png']);
    Storage::disk('public')->put('ktm/to-delete.png', 'x');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $ktm->id)
        ->call('delete');

    expect(Ktm::find($ktm->id))->toBeNull();
    Storage::disk('public')->assertMissing('ktm/to-delete.png');
});

it('admin dengan scope prodi hanya melihat ktm dari prodinya sendiri', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $ktmA = Ktm::factory()->for(Mahasiswa::factory()->state(['nama' => 'Dalam Scope', 'id_prodi' => $prodiA->id]), 'mahasiswa')->create();
    Ktm::factory()->for(Mahasiswa::factory()->state(['nama' => 'Luar Scope', 'id_prodi' => $prodiB->id]), 'mahasiswa')->create();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Dalam Scope')
        ->assertDontSee('Luar Scope');

    expect($ktmA->mahasiswa->id_prodi)->toBe($prodiA->id);
});

it('admin dengan scope prodi tidak bisa menghapus ktm di luar scope-nya lewat id langsung', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $ktmB = Ktm::factory()->for(Mahasiswa::factory()->state(['id_prodi' => $prodiB->id]), 'mahasiswa')->create();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $ktmB->id)
        ->call('delete')
        ->assertStatus(403);

    expect(Ktm::find($ktmB->id))->not->toBeNull();
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('admin.administrasi.ktm'))->assertRedirect(route('login'));
});

it('saves ktm header style settings', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('activeTab', 'header')
        ->set('headerAlign', 'left')
        ->set('headerTitleColor', '#ff0000')
        ->set('headerTitleSize', 30)
        ->set('headerUnivColor', '#00ff00')
        ->set('headerUnivSize', 40)
        ->call('saveHeaderSettings')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'ktm_header_align')->value('value'))->toBe('left');
    expect(Setting::where('key', 'ktm_header_title_color')->value('value'))->toBe('ff0000');
    expect(Setting::where('key', 'ktm_header_title_size')->value('value'))->toBe('30');
    expect(Setting::where('key', 'ktm_header_univ_color')->value('value'))->toBe('00ff00');
    expect(Setting::where('key', 'ktm_header_univ_size')->value('value'))->toBe('40');
});

it('rejects an invalid header color when saving header settings', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('activeTab', 'header')
        ->set('headerTitleColor', 'not-a-color')
        ->call('saveHeaderSettings')
        ->assertHasErrors(['headerTitleColor']);

    expect(Setting::where('key', 'ktm_header_title_color')->exists())->toBeFalse();
});

it('generates a ktm image with a long name that wraps instead of throwing', function () {
    seedKtmTemplate();
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create([
        'nama' => 'Mahasiswa Dengan Nama Yang Sangat Sangat Panjang Sekali Untuk Menguji Pembungkusan Baris',
        'nim' => '2024555099',
    ]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id, $mahasiswa->nim.' - '.$mahasiswa->nama)
        ->call('save')
        ->assertRedirect(route('admin.administrasi.ktm'));

    $ktm = Ktm::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    expect($ktm->file)->not->toBeNull();
    Storage::disk('public')->assertExists($ktm->file);
});

it('generates a ktm image using custom header alignment, color, and size settings', function () {
    seedKtmTemplate();
    Setting::create(['key' => 'ktm_header_align', 'value' => 'center', 'description' => 't', 'order' => 0]);
    Setting::create(['key' => 'ktm_header_title_color', 'value' => 'ff0000', 'description' => 't', 'order' => 0]);
    Setting::create(['key' => 'ktm_header_univ_color', 'value' => '0000ff', 'description' => 't', 'order' => 0]);
    Setting::create(['key' => 'ktm_header_title_size', 'value' => '20', 'description' => 't', 'order' => 0]);
    Setting::create(['key' => 'ktm_header_univ_size', 'value' => '30', 'description' => 't', 'order' => 0]);

    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Test Header Style', 'nim' => '2024555088']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id, $mahasiswa->nim.' - '.$mahasiswa->nama)
        ->call('save')
        ->assertRedirect(route('admin.administrasi.ktm'));

    $ktm = Ktm::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    Storage::disk('public')->assertExists($ktm->file);
});
