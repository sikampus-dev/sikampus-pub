<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\MatkulPrasyarat;
use App\Models\Setting;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cetak RPS (PDF, diunduh) untuk satu kelas dimana dosen yang login adalah PIC. Dipanggil dari
 * tombol "Unduh PDF" di App\Livewire\Dosen\Rps\Index.
 *
 * Logika di sini SENGAJA menyalin ulang JadwalDosenController::downloadRpsPdf (API) apa adanya,
 * termasuk seluruh helper privatnya — bukan diekstrak jadi shared service. Repo ini memang
 * menganut "salin, jangan share" antar pintu masuk yang beda (lihat skill siak-livewire-module,
 * dan pola yang sama di KrsCetakController/JurnalPerkuliahanCetakController). Nama perguruan
 * tinggi & logo diambil dari Setting (app_univ_name/app_univ_logo) — sumber yang sama dipakai
 * layouts.* lewat AppServiceProvider — dan prodi/dosen pengampu dari kelas itu sendiri, sehingga
 * otomatis mengikuti identitas prodi & dosen pemilik RPS, bukan nilai statis.
 */
class RpsCetakController extends Controller
{
    public function show(Request $request, int $kelasId): StreamedResponse
    {
        $user = Auth::user();
        $dosen = $user ? Dosen::where('id_user', $user->id)->first() : null;
        if (! $dosen) {
            abort(404, 'Data dosen tidak ditemukan');
        }

        if (! $this->dosenIsPicForKelasRps($dosen, $kelasId)) {
            abort(403, 'Anda tidak berhak mengakses RPS kelas ini');
        }

        $kelas = Kelas::with([
            'kurikulumMatkul.matkul.jenisMatkul',
            'kurikulumMatkul.matkul.matkulPrasyaratLinks.matkulPrasyarat',
            'prodi.jenjang',
            'semester',
            'kelompokKelas',
            'dosenPic',
            'rps.rpsCpl',
            'rps.rpsCpmk.rpsSubcpmk',
            'rps.rpsPembelajaran',
        ])
            ->whereNull('deleted_at')
            ->find($kelasId);

        if (! $kelas) {
            abort(404, 'Kelas tidak ditemukan');
        }

        $rps = $kelas->rps;

        $univSettings = Setting::query()
            ->whereIn('key', ['app_univ_name', 'app_univ_logo'])
            ->pluck('value', 'key');

        $namaUnivSetting = trim((string) ($univSettings->get('app_univ_name') ?? ''));
        $institusi = $namaUnivSetting !== ''
            ? mb_strtoupper($namaUnivSetting, 'UTF-8')
            : mb_strtoupper((string) config('app.name', 'SIAK'), 'UTF-8');

        $logoForPdf = $this->rpsPdfResolveLogoSrc($univSettings->get('app_univ_logo'));

        $km = $kelas->kurikulumMatkul;
        $m = $km?->matkul;
        $namaMatkul = trim((string) (($km?->nama_matkul ?: $m?->nama) ?? '—'));
        $kodeMatkul = trim((string) (($km?->kode_matkul ?: $m?->kode) ?? ''));
        if ($kodeMatkul === '') {
            $kodeMatkul = '—';
        }
        $sksVal = (float) ($km?->sks ?? $m?->sks ?? 0);
        $sksStr = $sksVal > 0 ? (string) $sksVal : '—';
        $workloadMenit = $sksVal > 0 ? (string) (int) round($sksVal * 45) : '—';

        $jenisMkNama = $m?->jenisMatkul?->nama;
        $kelompokMk = ($jenisMkNama !== null && trim((string) $jenisMkNama) !== '')
            ? trim((string) $jenisMkNama)
            : '—';

        $semesterMk = '—';
        if ($km && $km->semester_rekomendasi !== null) {
            $semesterMk = (string) $km->semester_rekomendasi;
        }

        $semAkademik = $kelas->semester;
        $semAkademikStr = $semAkademik
            ? trim((string) (($semAkademik->nama ?? '').' ('.($semAkademik->kode ?? '').')'))
            : '—';

        $kodeKelas = $kelas->kode !== null && trim((string) $kelas->kode) !== '' ? (string) $kelas->kode : '—';
        $kelompokKelasNama = $kelas->kelompokKelas?->nama;
        $kelompokKelasStr = $kelompokKelasNama !== null && trim((string) $kelompokKelasNama) !== ''
            ? trim((string) $kelompokKelasNama)
            : '—';

        $prodiNama = $kelas->prodi?->nama ?? '—';
        $jenjangNama = $kelas->prodi?->jenjang?->nama ?? '';

        $prasyaratTampil = '—';
        $idMatkulInduk = $m?->id ?? ($km?->id_matkul !== null ? (int) $km->id_matkul : null);
        if ($idMatkulInduk) {
            if ($m) {
                $m->loadMissing('matkulPrasyaratLinks.matkulPrasyarat');
                $linkRows = $m->matkulPrasyaratLinks;
            } else {
                $linkRows = MatkulPrasyarat::query()
                    ->where('id_matkul', $idMatkulInduk)
                    ->whereNull('deleted_at')
                    ->with('matkulPrasyarat')
                    ->orderBy('id')
                    ->get();
            }
            $parts = [];
            foreach ($linkRows as $link) {
                $pr = $link->matkulPrasyarat;
                if (! $pr) {
                    continue;
                }
                $kc = trim((string) ($pr->kode ?? ''));
                $nm = trim((string) ($pr->nama ?? ''));
                if ($kc !== '' && $nm !== '') {
                    $parts[] = $kc.' — '.$nm;
                } elseif ($nm !== '') {
                    $parts[] = $nm;
                } elseif ($kc !== '') {
                    $parts[] = $kc;
                }
            }
            if ($parts !== []) {
                $prasyaratTampil = implode(', ', $parts);
            }
        }

        Carbon::setLocale('id');
        $tanggalTerbitIdentitas = '—';
        if ($rps && $rps->tanggal_penyusunan) {
            try {
                $tanggalTerbitIdentitas = $rps->tanggal_penyusunan->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $tanggalTerbitIdentitas = $rps->tanggal_penyusunan->format('Y-m-d H:i:s');
            }
        } elseif ($rps && $rps->updated_at) {
            try {
                $tanggalTerbitIdentitas = $rps->updated_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $tanggalTerbitIdentitas = $rps->updated_at->format('Y-m-d H:i:s');
            }
        }

        $dosenPengampu = $kelas->dosenPic?->nama ?? '—';
        $dibuatOleh = $rps && $rps->created_by ? (string) $rps->created_by : $dosenPengampu;
        $diperiksaOleh = $rps && $rps->verified_by ? (string) $rps->verified_by : '—';
        $disetujuiOleh = $rps && $rps->approved_by ? (string) $rps->approved_by : '—';

        $css = <<<'CSS'
@page { margin: 12mm 14mm; }
html, body { height: auto; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; line-height: 1.4; }
h1 { text-align: center; font-size: 12pt; margin: 0 0 4px 0; page-break-after: avoid; }
h2 { font-size: 10pt; margin: 14px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 2px; page-break-after: avoid; }
.sub { text-align: center; font-size: 9pt; margin: 0 0 12px 0; font-weight: bold; }
table.grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
table.grid th, table.grid td { border: 1px solid #222; padding: 4px 5px; vertical-align: top; }
table.grid th { background: #e8e8e8; font-size: 7.5pt; font-weight: bold; text-align: center; }
td.lbl { font-weight: bold; width: 22%; background: #f5f5f5; font-size: 8pt; }
.sig td { height: 36px; font-size: 8pt; }
.p {
  text-align: justify;
  margin: 0 0 6px 0;
  white-space: normal;
  word-wrap: break-word;
  overflow-wrap: break-word;
  word-break: break-word;
  page-break-inside: auto;
  orphans: 2;
  widows: 2;
}
div.p { page-break-inside: auto; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; }
ul.cpl { margin: 4px 0 8px 18px; padding: 0; }
ul.cpl li {
  margin-bottom: 4px;
  page-break-inside: auto;
  word-wrap: break-word;
  overflow-wrap: break-word;
  word-break: break-word;
}
table.plan { width: 100%; border-collapse: collapse; font-size: 6.5pt; table-layout: fixed; }
table.plan th, table.plan td { border: 1px solid #222; padding: 3px 2px; vertical-align: top; word-wrap: break-word; }
table.plan th { background: #e0e0e0; font-weight: bold; text-align: center; }
.break { page-break-after: always; }
.note { font-size: 8pt; color: #444; font-style: italic; }
table.rps-kop { width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #222; page-break-inside: avoid; }
table.rps-kop td { border: 1px solid #222; vertical-align: middle; padding: 8px 6px; }
td.rps-kop-logo { width: 24%; text-align: center; }
.rps-kop-img { max-height: 78px; max-width: 100%; display: block; margin: 0 auto; object-fit: contain; }
td.rps-kop-title { width: 76%; text-align: center; padding: 10px 12px; }
.rps-kop-univ { font-size: 13pt; font-weight: bold; text-transform: uppercase; line-height: 1.25; }
.rps-kop-rule { border-bottom: 1px solid #222; margin: 8px auto 8px auto; width: 90%; }
.rps-kop-doc { font-size: 11pt; font-weight: bold; text-transform: uppercase; }
.rps-section-title { font-size: 10pt; font-weight: bold; text-transform: uppercase; margin: 8px 0 5px 0; page-break-after: avoid; }
table.rps-identitas { width: 100%; border-collapse: collapse; margin-bottom: 8px; table-layout: fixed; page-break-inside: avoid; }
table.rps-identitas th, table.rps-identitas td { border: 1px solid #222; padding: 5px 3px; text-align: center; vertical-align: middle; font-size: 7pt; word-wrap: break-word; }
table.rps-identitas th { background: #e8e8e8; font-weight: bold; }
.rps-kelas-meta { font-size: 7.5pt; color: #333; margin: 0 0 10px 0; text-align: center; line-height: 1.35; }
table.rps-sig { width: 100%; border-collapse: collapse; margin-bottom: 10px; page-break-inside: avoid; }
table.rps-sig th, table.rps-sig td { border: 1px solid #222; }
table.rps-sig th { background: #e8e8e8; font-size: 8pt; font-weight: bold; text-align: center; padding: 5px 4px; vertical-align: middle; }
table.rps-sig td.rps-sig-cell { height: 46mm; vertical-align: bottom; text-align: center; font-size: 8.5pt; padding: 6px 5px 8px 5px; line-height: 1.35; }
CSS;

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'.$css.'</style></head><body>';

        $html .= '<table class="rps-kop"><tr>';
        $html .= '<td class="rps-kop-logo">';
        if ($logoForPdf['src'] !== '') {
            $html .= '<img src="'.e($logoForPdf['src']).'" alt="" class="rps-kop-img" />';
        } else {
            $html .= ' ';
        }
        $html .= '</td>';
        $html .= '<td class="rps-kop-title">';
        $html .= '<div class="rps-kop-univ">'.e($institusi).'</div>';
        $html .= '<div class="rps-kop-rule"></div>';
        $html .= '<div class="rps-kop-doc">RENCANA PEMBELAJARAN SEMESTER (RPS)</div>';
        $html .= '</td></tr></table>';

        $html .= '<div class="rps-section-title">IDENTITAS MATA KULIAH</div>';
        $html .= '<table class="grid rps-identitas"><thead><tr>';
        $html .= '<th>Nama<br/>Mata Kuliah</th>';
        $html .= '<th>Kode<br/>Mata Kuliah</th>';
        $html .= '<th>Sks</th>';
        $html .= '<th>Workload</th>';
        $html .= '<th>Kelompok<br/>Matakuliah</th>';
        $html .= '<th>Semester</th>';
        $html .= '<th>Matakuliah<br/>Pra-Syarat</th>';
        $html .= '<th>Tanggal Terbit</th>';
        $html .= '</tr></thead><tbody><tr>';
        $html .= '<td>'.e($namaMatkul).'</td>';
        $html .= '<td>'.e($kodeMatkul).'</td>';
        $html .= '<td>'.e($sksStr).'</td>';
        $html .= '<td>'.e($workloadMenit).'</td>';
        $html .= '<td>'.e($kelompokMk).'</td>';
        $html .= '<td>'.e($semesterMk).'</td>';
        $html .= '<td>'.e($prasyaratTampil).'</td>';
        $html .= '<td>'.e($tanggalTerbitIdentitas).'</td>';
        $html .= '</tr></tbody></table>';

        $sigNamaKolom1 = $dibuatOleh;
        if ($dosenPengampu !== '—' && trim($dosenPengampu) !== trim($dibuatOleh)) {
            $sigNamaKolom1 .= "\n".$dosenPengampu;
        }
        $html .= '<table class="grid rps-sig"><thead><tr>';
        $html .= '<th>Dibuat Oleh<br/>Dosen Pengampu</th>';
        $html .= '<th>Diperiksa Oleh<br/>TPK Program Studi</th>';
        $html .= '<th>Disetujui Oleh<br/>Ketua Program Studi</th>';
        $html .= '</tr></thead><tbody><tr>';
        $html .= '<td class="rps-sig-cell">'.nl2br(e($sigNamaKolom1), false).'</td>';
        $html .= '<td class="rps-sig-cell">'.e($diperiksaOleh).'</td>';
        $html .= '<td class="rps-sig-cell">'.e($disetujuiOleh).'</td>';
        $html .= '</tr></tbody></table>';

        $html .= '<div class="break"></div>';

        if (! $rps) {
            $html .= '<p class="note">Belum ada data RPS untuk kelas ini. Simpan bagian deskripsi RPS di aplikasi terlebih dahulu.</p></body></html>';
        } else {
            $html .= '<h2>Deskripsi mata kuliah dan CPL</h2>';
            $html .= $this->rpsPdfHtmlProsaFromPlain($rps->deskripsi_matkul);

            $html .= '<h2>CPL yang dibebankan pada mata kuliah</h2>';
            $cplRows = $rps->rpsCpl;
            if ($cplRows->isEmpty()) {
                $html .= '<p class="p">—</p>';
            } else {
                $html .= '<ol class="cpl">';
                foreach ($cplRows as $idx => $c) {
                    $t = $this->rpsPdfPlainText($c->cpl);
                    $html .= '<li><strong>'.($idx + 1).'</strong> '.($t !== '' ? nl2br(e($t), false) : '—').'</li>';
                }
                $html .= '</ol>';
            }

            $html .= '<h2>Capaian pembelajaran mata kuliah (CPMK)</h2>';
            $cpmkRows = $rps->rpsCpmk;
            if ($cpmkRows->isEmpty()) {
                $html .= '<p class="p">—</p>';
            } else {
                $html .= '<ol class="cpl">';
                foreach ($cpmkRows as $idx => $c) {
                    $t = $this->rpsPdfPlainText($c->cpmk);
                    $html .= '<li><strong>CPMK-'.($idx + 1).'</strong> '.($t !== '' ? nl2br(e($t), false) : '—').'</li>';
                }
                $html .= '</ol>';
            }

            $html .= '<h2>Sub-CPMK</h2>';
            if ($cpmkRows->isEmpty()) {
                $html .= '<p class="p">—</p>';
            } else {
                $html .= '<table class="grid"><thead><tr><th style="width:8%">No.</th><th style="width:14%">CPMK</th><th>Sub-CPMK</th></tr></thead><tbody>';
                $no = 0;
                foreach ($cpmkRows as $ci => $c) {
                    $cpmkLabel = 'CPMK-'.($ci + 1);
                    $subs = $c->rpsSubcpmk;
                    if ($subs->isEmpty()) {
                        $no++;
                        $html .= '<tr><td style="text-align:center">'.$no.'</td><td>'.e($cpmkLabel).'</td><td>—</td></tr>';
                    } else {
                        foreach ($subs as $s) {
                            $no++;
                            $st = $this->rpsPdfPlainText($s->subcpmk);
                            $html .= '<tr><td style="text-align:center">'.$no.'</td><td>'.e($cpmkLabel).'</td><td>'
                                .($st !== '' ? nl2br(e($st), false) : '—').'</td></tr>';
                        }
                    }
                }
                $html .= '</tbody></table>';
            }

            $html .= '<h2>Materi perkuliahan (ringkas)</h2>';
            $html .= '<div class="p">'.$this->rpsPdfCell($rps->materi_kuliah).'</div>';

            $html .= '<h2>Strategi dan langkah pembelajaran</h2>';
            $html .= '<div class="p">'.$this->rpsPdfCell($rps->model_pembelajaran).'</div>';

            $html .= '<h2>Media pembelajaran</h2>';
            $html .= '<p class="p"><strong>Perangkat lunak:</strong><br>'.$this->rpsPdfCell($rps->media_perangkat_lunak).'</p>';
            $html .= '<p class="p"><strong>Perangkat keras:</strong><br>'.$this->rpsPdfCell($rps->media_perangkat_keras).'</p>';

            $html .= '<h2>Referensi</h2>';
            $html .= '<p class="p"><strong>Utama:</strong><br>'.$this->rpsPdfCell($rps->pustaka_utama).'</p>';
            $html .= '<p class="p"><strong>Pendukung:</strong><br>'.$this->rpsPdfCell($rps->pustaka_pendukung).'</p>';

            $html .= '<div class="break"></div>';
            $html .= '<h2>Rencana pembelajaran</h2>';
            $html .= '<p class="note">Kolom disesuaikan dengan data RPS di sistem: indikator capaian, materi, bentuk asesmen, pembelajaran sinkron/asinkron, dan bobot penilaian.</p>';
            $html .= '<table class="plan"><thead><tr>';
            $html .= '<th style="width:4%">Minggu ke</th>';
            $html .= '<th style="width:14%">Sub-CPMK</th>';
            $html .= '<th style="width:16%">Indikator capaian / penilaian</th>';
            $html .= '<th style="width:16%">Materi perkuliahan</th>';
            $html .= '<th style="width:12%">Bentuk &amp; kriteria penilaian</th>';
            $html .= '<th style="width:12%">Pembelajaran sinkron</th>';
            $html .= '<th style="width:12%">Pembelajaran asinkron</th>';
            $html .= '<th style="width:6%">Bobot (%)</th>';
            $html .= '</tr></thead><tbody>';

            $pbl = $rps->rpsPembelajaran;
            if ($pbl->isEmpty()) {
                $html .= '<tr><td colspan="8" style="text-align:center">Belum ada baris rencana pembelajaran.</td></tr>';
            } else {
                foreach ($pbl as $row) {
                    $minggu = $row->urutan_pertemuan !== null ? (string) $row->urutan_pertemuan : '—';
                    $bobot = $row->bobot !== null ? (string) $row->bobot : '—';
                    $html .= '<tr>';
                    $html .= '<td style="text-align:center">'.e($minggu).'</td>';
                    $html .= '<td>'.$this->rpsPdfCell($row->sub_cpmk).'</td>';
                    $html .= '<td>'.$this->rpsPdfCell($row->indikator_penilaian).'</td>';
                    $html .= '<td>'.$this->rpsPdfCell($row->materi ?? $row->materi_en).'</td>';
                    $html .= '<td>'.$this->rpsPdfCell($row->bentuk_kriteria_penilaian).'</td>';
                    $html .= '<td>'.$this->rpsPdfCell($row->pembelajaran_sinkron).'</td>';
                    $html .= '<td>'.$this->rpsPdfCell($row->pembelajaran_asinkron).'</td>';
                    $html .= '<td style="text-align:center">'.e($bobot).'</td>';
                    $html .= '</tr>';
                }
            }
            $html .= '</tbody></table>';
            $html .= '</body></html>';
        }

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', $logoForPdf['remote']);
        $options->set('chroot', realpath(base_path()) ?: base_path());
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $kodeMkFile = trim((string) (($km?->kode_matkul ?: $m?->kode) ?? ''));
        if ($kodeMkFile === '') {
            $kodeMkFile = 'NA';
        }
        $kodeKelasFile = ($kelas->kode !== null && trim((string) $kelas->kode) !== '')
            ? trim((string) $kelas->kode)
            : 'NA';
        $safeKode = preg_replace('/[^A-Za-z0-9._-]+/', '_', $kodeMkFile.'_'.$kodeKelasFile);
        $safeKode = trim((string) $safeKode, '._-') ?: 'NA_NA';
        $filename = 'RPS_'.$safeKode.'_'.now()->timezone(config('app.timezone'))->format('Y-m-d').'.pdf';

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Sama persis dengan JadwalDosenController::dosenIsPicForKelasRps.
     */
    private function dosenIsPicForKelasRps(Dosen $dosen, int $kelasId): bool
    {
        return KelasDosen::where('id_dosen', $dosen->id)
            ->where('id_kelas', $kelasId)
            ->where('is_pic', true)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Sama persis dengan JadwalDosenController::rpsPdfResolveLogoSrc.
     *
     * @return array{src: string, remote: bool}
     */
    private function rpsPdfResolveLogoSrc(?string $logoValue): array
    {
        if ($logoValue === null || trim($logoValue) === '') {
            return ['src' => '', 'remote' => false];
        }

        $v = trim($logoValue);
        if (str_starts_with($v, 'data:image')) {
            return ['src' => $v, 'remote' => false];
        }

        $tryDataUri = function (string $fullPath): ?string {
            if ($fullPath === '' || ! is_file($fullPath) || ! is_readable($fullPath)) {
                return null;
            }
            $mime = @mime_content_type($fullPath) ?: 'image/png';
            if (! str_starts_with((string) $mime, 'image/')) {
                return null;
            }
            $raw = @file_get_contents($fullPath);
            if ($raw === false) {
                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode($raw);
        };

        if (preg_match('~/storage/([^\s?#]+)~', $v, $m)) {
            $rel = $m[1];
            foreach ([
                public_path('storage/'.$rel),
                storage_path('app/public/'.$rel),
            ] as $full) {
                $d = $tryDataUri($full);
                if ($d !== null) {
                    return ['src' => $d, 'remote' => false];
                }
            }
        }

        $pathPart = parse_url($v, PHP_URL_PATH);
        if (is_string($pathPart) && str_starts_with($pathPart, '/storage/')) {
            $rel = ltrim(substr($pathPart, strlen('/storage/')), '/');
            foreach ([
                public_path('storage/'.$rel),
                storage_path('app/public/'.$rel),
            ] as $full) {
                $d = $tryDataUri($full);
                if ($d !== null) {
                    return ['src' => $d, 'remote' => false];
                }
            }
        }

        if (str_starts_with($v, '/') && is_file(public_path(ltrim($v, '/')))) {
            $d = $tryDataUri(public_path(ltrim($v, '/')));
            if ($d !== null) {
                return ['src' => $d, 'remote' => false];
            }
        }

        if (str_starts_with($v, 'http://') || str_starts_with($v, 'https://')) {
            return ['src' => $v, 'remote' => true];
        }

        return ['src' => '', 'remote' => false];
    }

    /**
     * Sama persis dengan JadwalDosenController::rpsPdfPlainText — ubah teks/HTML dari editor RPS
     * menjadi teks polos untuk PDF.
     */
    private function rpsPdfPlainText(?string $htmlOrText): string
    {
        if ($htmlOrText === null || trim($htmlOrText) === '') {
            return '';
        }
        $s = (string) $htmlOrText;
        $s = str_replace(['<br>', '<br/>', '<br />'], "\n", $s);
        $s = preg_replace('/<\/p>\s*/i', "\n", $s);
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $s));
    }

    /**
     * Sama persis dengan JadwalDosenController::rpsPdfCell — sel tabel: teks polos + nl2br +
     * escape.
     */
    private function rpsPdfCell(?string $htmlOrText): string
    {
        $plain = $this->rpsPdfPlainText($htmlOrText);

        return $plain === '' ? '—' : nl2br(e($plain), false);
    }

    /**
     * Sama persis dengan JadwalDosenController::rpsPdfSplitLongParagraph — pecah paragraf sangat
     * panjang di spasi terakhir sebelum batas, agar Dompdf tidak memotong konten.
     *
     * @return list<string>
     */
    private function rpsPdfSplitLongParagraph(string $text, int $maxLen = 2800): array
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) <= $maxLen) {
            return $text === '' ? [] : [$text];
        }

        $parts = [];
        $remain = $text;
        while (mb_strlen($remain) > $maxLen) {
            $slice = mb_substr($remain, 0, $maxLen);
            $breakPos = mb_strrpos($slice, ' ');
            if ($breakPos === false || $breakPos < (int) ($maxLen / 3)) {
                $breakPos = $maxLen;
            }
            $parts[] = trim(mb_substr($remain, 0, $breakPos));
            $remain = trim(mb_substr($remain, $breakPos));
        }
        if ($remain !== '') {
            $parts[] = $remain;
        }

        return $parts;
    }

    /**
     * Sama persis dengan JadwalDosenController::rpsPdfHtmlProsaFromPlain — HTML beberapa <p> dari
     * teks prosa agar Dompdf bisa memutus halaman (hindari satu blok panjang).
     */
    private function rpsPdfHtmlProsaFromPlain(?string $htmlOrText): string
    {
        $plain = $this->rpsPdfPlainText($htmlOrText);
        if ($plain === '') {
            return '<p class="p">—</p>';
        }

        $chunks = preg_split('/\n+/', $plain, -1, PREG_SPLIT_NO_EMPTY);
        if ($chunks === false) {
            $chunks = [$plain];
        }

        $out = '';
        foreach ($chunks as $chunk) {
            $t = trim((string) $chunk);
            if ($t === '') {
                continue;
            }
            foreach ($this->rpsPdfSplitLongParagraph($t) as $piece) {
                if ($piece === '') {
                    continue;
                }
                $out .= '<p class="p">'.nl2br(e($piece), false).'</p>';
            }
        }

        return $out !== '' ? $out : '<p class="p">—</p>';
    }
}
