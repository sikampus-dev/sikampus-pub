<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use App\Models\Concerns\MencatatPelaku;
use App\Services\StatusPembayaranTagihan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tagihan extends Model
{
    use AturanHapusBerantai, HasFactory, MencatatPelaku, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = ['tagihanRinci', 'pembayaranBelumDisetujui'];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [
        'pembayaranDisetujui' => 'pembayaran yang sudah disetujui',
    ];

    protected $table = 'tagihan';

    protected $fillable = ['id_mahasiswa', 'id_semester', 'no_tagihan', 'total', 'tahap', 'status', 'tanggal_tagihan', 'tanggal_jatuh_tempo', 'tanggal_pembayaran', 'keterangan'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_mahasiswa' => 'integer',
        'id_semester' => 'integer',
        'no_tagihan' => 'string',
        'total' => 'decimal:2',
        'tahap' => 'integer',
        'tanggal_tagihan' => 'datetime',
        'tanggal_jatuh_tempo' => 'datetime',
        'tanggal_pembayaran' => 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    public function tagihanRinci()
    {
        return $this->hasMany(TagihanRinci::class, 'id_tagihan');
    }

    public function pembayaran()
    {
        return $this->hasMany(Pembayaran::class, 'id_tagihan');
    }

    /**
     * Apakah kolom `status` boleh disetel 'paid' — yaitu akumulasi pembayaran yang SUDAH
     * DISETUJUI menutup total tagihan.
     *
     * Aturan ini dulu disalin ke delapan tempat (store/approve/update/destroy/import di
     * controller, plus Form dan Show di panel Livewire) dan dua di antaranya menjumlahkan
     * seluruh pembayaran tanpa menyaring `approved_at` — satu bukti bayar yang diunggah
     * mahasiswa dan belum diverifikasi bisa membuat tagihan berstatus lunas. Sekarang satu
     * metode ini yang dipakai semuanya.
     *
     * Ambangnya memakai StatusPembayaranTagihan::TOLERANSI supaya kolom `status` dan label di
     * layar tidak pernah berbeda di titik pembulatan.
     *
     * Keringanan biaya sengaja TIDAK ikut dihitung di sini: kolom `status` hanya diperbarui saat
     * ada peristiwa pembayaran, sementara keringanan bisa dicabut kapan saja tanpa menyentuh
     * tagihan — memasukkannya ke kolom yang dipersist akan menghasilkan status basi. Kredit
     * keringanan tetap diperhitungkan pada status turunan yang ditampilkan ke pengguna
     * (lihat KeringananBiayaKreditService).
     */
    public function lunasMenurutPembayaranDisetujui(): bool
    {
        $totalDisetujui = (float) Pembayaran::approvedQueryForTagihan((int) $this->id)->sum('nominal');

        return $totalDisetujui + StatusPembayaranTagihan::TOLERANSI >= (float) $this->total;
    }

    /**
     * Dipisah dua supaya aturan hapusnya berbeda: pembayaran yang sudah disetujui adalah uang yang
     * benar-benar diterima (menahan penghapusan tagihan), sedangkan yang belum disetujui hanya
     * pengajuan yang tidak berarti tanpa tagihannya (ikut terhapus bersama tagihan).
     */
    public function pembayaranDisetujui()
    {
        return $this->hasMany(Pembayaran::class, 'id_tagihan')->whereNotNull('approved_at');
    }

    public function pembayaranBelumDisetujui()
    {
        return $this->hasMany(Pembayaran::class, 'id_tagihan')->whereNull('approved_at');
    }
}
