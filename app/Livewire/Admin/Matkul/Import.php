<?php

namespace App\Livewire\Admin\Matkul;

use App\Models\JenisMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Import extends Component
{
    use WithFileUploads;

    public $file = null;

    public bool $processing = false;

    public ?array $result = null;

    protected function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ];
    }

    /**
     * Sama persis dengan MatkulController::import — kode unik per prodi (kode+id_prodi bisa
     * berulang lintas prodi), duplikat dalam file dan yang sudah ada di database dilewati (skip).
     */
    public function import(): void
    {
        $this->result = null;
        $this->processing = true;
        $this->validate();

        $user = Auth::user();
        $allowedProdiIds = ($user && $user->hasScopeRestriction()) ? $user->getAllowedProdiIds() : null;

        try {
            $spreadsheet = IOFactory::load($this->file->getRealPath());
        } catch (\Throwable $e) {
            $this->processing = false;
            $this->addError('file', 'Gagal membaca file Excel. Pastikan format .xlsx/.xls valid; hindari rumus error (#NAME?, #REF!). Salin data ke template lalu tempel sebagai nilai saja jika perlu. Detail: '.$e->getMessage());

            return;
        }

        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (count($rows) < 2) {
            $this->processing = false;
            $this->addError('file', 'File Excel kosong atau tidak valid.');
            $this->reset('file');

            return;
        }

        array_shift($rows);

        $errors = [];
        $successCount = 0;
        $skipCount = 0;
        $processedRows = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2;

                if (empty(array_filter($row))) {
                    continue;
                }

                $data = [
                    'kode' => $row[0] ?? null,
                    'nama' => $row[1] ?? null,
                    'nama_en' => $row[2] ?? null,
                    'deskripsi' => $row[3] ?? null,
                    'sks' => $row[4] ?? null,
                    'semester' => $row[5] ?? null,
                    'prodi_kode' => $row[6] ?? null,
                    'jenis_matkul_kode' => $row[7] ?? null,
                    'status' => $row[8] ?? 'active',
                ];

                if (empty($data['kode'])) {
                    $errors[] = "Baris {$rowNumber}: Kode wajib diisi.";

                    continue;
                }

                if (empty($data['nama'])) {
                    $errors[] = "Baris {$rowNumber}: Nama wajib diisi.";

                    continue;
                }

                $id_prodi = null;
                if (! empty($data['prodi_kode'])) {
                    $prodi_kode = trim((string) $data['prodi_kode']);
                    $prodi = Prodi::where('kode', $prodi_kode)->first();
                    if (! $prodi) {
                        $errors[] = "Baris {$rowNumber}: Kode Prodi '{$prodi_kode}' tidak ditemukan di sistem.";
                        $skipCount++;

                        continue;
                    }
                    $id_prodi = $prodi->id;
                }

                if (Matkul::where('kode', $data['kode'])->where('id_prodi', $id_prodi)->exists()) {
                    $errors[] = "Baris {$rowNumber}: Kode '{$data['kode']}' sudah ada untuk prodi ini di sistem.";
                    $skipCount++;

                    continue;
                }

                foreach ($processedRows as $processed) {
                    if ($data['kode'] === $processed['kode'] && $id_prodi === $processed['id_prodi']) {
                        $errors[] = "Baris {$rowNumber}: Kode '{$data['kode']}' duplikat dalam file untuk prodi yang sama.";
                        $skipCount++;

                        continue 2;
                    }
                }

                if ($allowedProdiIds !== null) {
                    if ($id_prodi === null) {
                        $errors[] = "Baris {$rowNumber}: Program studi wajib diisi dan harus dalam scope Anda.";
                        $skipCount++;

                        continue;
                    }
                    if (! in_array((int) $id_prodi, $allowedProdiIds, true)) {
                        $errors[] = "Baris {$rowNumber}: Anda tidak memiliki akses ke program studi ini.";
                        $skipCount++;

                        continue;
                    }
                }

                $id_jenis_matkul = null;
                if (! empty($data['jenis_matkul_kode'])) {
                    $jenisMatkul = JenisMatkul::where('kode', trim((string) $data['jenis_matkul_kode']))->first();
                    if ($jenisMatkul) {
                        $id_jenis_matkul = $jenisMatkul->id;
                    }
                }

                $sks = null;
                if (! empty($data['sks'])) {
                    $sks = is_numeric($data['sks']) ? (int) $data['sks'] : null;
                    if ($sks !== null && $sks < 1) {
                        $errors[] = "Baris {$rowNumber}: SKS harus lebih dari 0.";
                        $skipCount++;

                        continue;
                    }
                }

                $semester = null;
                if (! empty($data['semester'])) {
                    $semester = is_numeric($data['semester']) ? (int) $data['semester'] : null;
                    if ($semester !== null && ($semester < 1 || $semester > 14)) {
                        $errors[] = "Baris {$rowNumber}: Semester harus antara 1-14.";
                        $skipCount++;

                        continue;
                    }
                }

                $status = 'active';
                if (! empty($data['status'])) {
                    $status = strtolower(trim((string) $data['status']));
                    if (! in_array($status, ['active', 'inactive'], true)) {
                        $errors[] = "Baris {$rowNumber}: Status harus 'active' atau 'inactive'.";
                        $skipCount++;

                        continue;
                    }
                }

                Matkul::create([
                    'kode' => trim((string) $data['kode']),
                    'nama' => trim((string) $data['nama']),
                    'nama_en' => ! empty($data['nama_en']) ? trim((string) $data['nama_en']) : null,
                    'deskripsi' => ! empty($data['deskripsi']) ? trim((string) $data['deskripsi']) : null,
                    'sks' => $sks,
                    'semester' => $semester,
                    'id_prodi' => $id_prodi,
                    'id_jenis_matkul' => $id_jenis_matkul,
                    'status' => $status,
                ]);

                $successCount++;
                $processedRows[] = ['kode' => $data['kode'], 'id_prodi' => $id_prodi];
            }

            DB::commit();

            $this->result = [
                'success_count' => $successCount,
                'skip_count' => $skipCount,
                'errors' => $errors,
            ];
            $this->reset('file');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import mata kuliah gagal', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addError('file', 'Terjadi kesalahan saat mengimport data: '.$e->getMessage());
        }

        $this->processing = false;
    }

    public function render()
    {
        return view('livewire.admin.matkul.import')->extends('layouts.web');
    }
}
