<?php

use App\Services\Update\InstallationInspector;

// App\Services\Update\InstallationInspector::type() -- HARUS baca config('sikampus.managed'),
// bukan env('SIKAMPUS_MANAGED', ...) langsung, supaya tetap benar setelah `config:cache`
// (env() di luar berkas config kembali null begitu config di-cache).

it('mendeteksi tipe managed dari config, bukan langsung dari env()', function () {
    config(['sikampus.managed' => true]);

    expect((new InstallationInspector)->type())->toBe(InstallationInspector::TYPE_MANAGED);
});

it('tidak mendeteksi tipe managed ketika config sikampus.managed false', function () {
    config(['sikampus.managed' => false]);

    expect((new InstallationInspector)->type())->not->toBe(InstallationInspector::TYPE_MANAGED);
});
