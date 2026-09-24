<?php

namespace App\Livewire\Mahasiswa\Krs;

use App\Models\Krs;
use App\Models\Mahasiswa;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    #[Locked]
    public int $mahasiswaId;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;
    }

    /**
     * Sama persis dengan KrsController::getKrsBySemester.
     */
    #[Computed]
    public function krsBySemester(): array
    {
        $krsList = Krs::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.semester',
            'kelas.dosenPic',
        ])
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        $krsBySemester = [];

        foreach ($krsList as $krs) {
            $semester = $krs->kelas->semester;
            if (! $semester) {
                continue;
            }

            $semesterId = $semester->id;
            if (! isset($krsBySemester[$semesterId])) {
                $krsBySemester[$semesterId] = [
                    'semester' => $semester,
                    'krs' => [],
                    'total_sks_diajukan' => 0,
                    'total_sks_diacc' => 0,
                ];
            }

            $sks = $krs->kelas->kurikulumMatkul->matkul->sks ?? 0;
            $krsBySemester[$semesterId]['total_sks_diajukan'] += $sks;
            if ($krs->approved_at) {
                $krsBySemester[$semesterId]['total_sks_diacc'] += $sks;
            }

            $krsBySemester[$semesterId]['krs'][] = $krs;
        }

        // Terbaru dulu berdasarkan kode semester ('20241', '20242', ...), BUKAN id: id hanya
        // urutan pembuatan baris, dan semester lama bisa diinput belakangan sehingga dapat id
        // lebih besar (mis. '20232' ber-id 5 padahal '20241' ber-id 1) — sort per id membuat
        // semester lama itu naik ke atas. Sama dengan KrsController::getKrsBySemester.
        usort($krsBySemester, fn ($a, $b) => $b['semester']->kode <=> $a['semester']->kode);

        return array_values($krsBySemester);
    }

    /**
     * Sama persis dengan KrsController::exportKrsPdf.
     */
    public function exportPdf()
    {
        $mahasiswa = Mahasiswa::findOrFail($this->mahasiswaId);
        $krsBySemester = $this->krsBySemester;

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; }
        .title { text-align: center; font-size: 14pt; font-weight: bold; margin-bottom: 4px; }
        .subtitle { text-align: center; margin-bottom: 16px; }
        .section { margin-bottom: 14px; }
        .section-title { font-size: 11pt; font-weight: bold; margin-bottom: 6px; border-bottom: 1px solid #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background-color: #4472C4; color: white; padding: 6px; border: 1px solid #000; text-align: center; }
        td { padding: 5px; border: 1px solid #000; }
        td.num { text-align: center; }
        .footer { margin-top: 20px; text-align: right; font-size: 9pt; }
        </style></head><body>';

        $html .= '<div class="title">KARTU RENCANA STUDI (KRS)</div>';
        $html .= '<div class="subtitle">'.htmlspecialchars($mahasiswa->nim.' - '.$mahasiswa->nama).'</div>';

        foreach ($krsBySemester as $group) {
            $html .= '<div class="section">';
            $html .= '<div class="section-title">'.htmlspecialchars($group['semester']->nama.' ('.$group['semester']->kode.')')
                .' — Total SKS: '.(int) $group['total_sks_diacc'].' / '.(int) $group['total_sks_diajukan'].'</div>';
            $html .= '<table><thead><tr>';
            $html .= '<th style="width:8%">No</th><th style="width:12%">Kode</th><th style="width:32%">Mata Kuliah</th>';
            $html .= '<th style="width:8%">SKS</th><th style="width:25%">Dosen</th><th style="width:15%">Status</th><th style="width:20%">Tgl Disetujui</th>';
            $html .= '</tr></thead><tbody>';

            $no = 1;
            foreach ($group['krs'] as $krs) {
                $matkul = $krs->kelas->kurikulumMatkul->matkul ?? null;
                $statusText = $krs->approved_at ? 'Disetujui' : 'Menunggu';
                $approvedAt = $krs->approved_at ? $krs->approved_at->format('d/m/Y') : '-';
                $html .= '<tr>';
                $html .= '<td class="num">'.$no.'</td>';
                $html .= '<td>'.htmlspecialchars($matkul->kode ?? '-').'</td>';
                $html .= '<td>'.htmlspecialchars($matkul->nama ?? '-').'</td>';
                $html .= '<td class="num">'.(int) ($matkul->sks ?? 0).'</td>';
                $html .= '<td>'.htmlspecialchars($krs->kelas->dosenPic->nama ?? '-').'</td>';
                $html .= '<td class="num">'.htmlspecialchars($statusText).'</td>';
                $html .= '<td>'.htmlspecialchars($approvedAt).'</td>';
                $html .= '</tr>';
                $no++;
            }
            $html .= '</tbody></table></div>';
        }

        if (empty($krsBySemester)) {
            $html .= '<p>Tidak ada data KRS.</p>';
        }

        $html .= '<div class="footer">Dicetak: '.now()->format('d/m/Y H:i').'</div></body></html>';

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'KRS_'.preg_replace('/\s+/', '_', $mahasiswa->nim).'_'.now()->format('Y-m-d').'.pdf';

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function render()
    {
        return view('livewire.mahasiswa.krs.index')->extends('layouts.mahasiswa');
    }
}
