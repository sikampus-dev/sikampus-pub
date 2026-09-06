<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Sisi pembaca dari kebijakan grace period lisensi yang ditulis oleh Sikampus Platform
 * (repo sikampus-web) ke tabel `settings` tenant ini, lewat App\Services\
 * TenantLicenseStatusWriter di sana — dijalankan harian oleh command
 * `licensing:send-expiry-reminders` di portal. Lihat App\Http\Middleware\
 * EnsureSubscriptionActive (blokir akses saat 'suspended') dan
 * App\Providers\AppServiceProvider::boot() (banner peringatan saat 'grace').
 *
 * Kontrak 4 key yang ditulis portal (APA ADANYA, tidak boleh ditebak/diubah sepihak di sini
 * kalau skemanya berubah — sama seperti app_license_key yang sudah lebih dulu ada):
 *   app_license_status         'active'|'grace'|'suspended'|'lifetime'
 *   app_license_expires_at     'YYYY-MM-DD' atau kosong
 *   app_license_grace_ends_at  'YYYY-MM-DD' atau kosong
 *   app_license_message        teks Bahasa Indonesia siap-tampil (kosong kalau status 'active'/'lifetime')
 *
 * Default 'active' untuk status yang TIDAK ADA (bukan cuma kosong) SENGAJA — mencakup tenant
 * lama yang belum pernah disentuh portal (dideploy sebelum fitur ini ada), dan instalasi
 * self-hosted yang tidak pernah terhubung ke Sikampus Cloud sama sekali. Ketiadaan data TIDAK
 * BOLEH memblokir siapa pun; hanya status 'suspended' yang eksplisit yang memblokir.
 */
class SubscriptionStatus
{
    private const KEYS = [
        'app_license_status',
        'app_license_expires_at',
        'app_license_grace_ends_at',
        'app_license_message',
    ];

    private function __construct(
        private readonly string $status,
        private readonly ?string $expiresAt,
        private readonly ?string $graceEndsAt,
        private readonly ?string $message,
    ) {}

    public static function load(): self
    {
        $values = self::readSettings();

        return new self(
            status: $values->get('app_license_status') ?: 'active',
            expiresAt: $values->get('app_license_expires_at') ?: null,
            graceEndsAt: $values->get('app_license_grace_ends_at') ?: null,
            message: $values->get('app_license_message') ?: null,
        );
    }

    /**
     * @return Collection<string, string>
     */
    private static function readSettings(): Collection
    {
        try {
            return Setting::whereIn('key', self::KEYS)->pluck('value', 'key');
        } catch (Throwable) {
            // Tabel settings belum ada (mis. sebelum migrate pertama) -- bukan alasan gagal,
            // sama seperti App\Services\Update\ReleaseChecker::licenseKey().
            return collect();
        }
    }

    public function isGrace(): bool
    {
        return $this->status === 'grace';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function expiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function graceEndsAt(): ?string
    {
        return $this->graceEndsAt;
    }
}
