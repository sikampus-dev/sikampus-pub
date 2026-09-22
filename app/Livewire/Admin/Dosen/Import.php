<?php

namespace App\Livewire\Admin\Dosen;

use App\Models\Agama;
use App\Models\Dosen;
use App\Models\Kota;
use App\Models\Negara;
use App\Models\Provinsi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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
     * Sama persis dengan DosenController::import — kolom, urutan, dan aturan unik
     * (Kode Dosen/NIP bentrok → baris dilewati; Email/NIDN bentrok → disimpan kosong,
     * dosen tetap dibuat) disalin apa adanya.
     */
    public function import(): void
    {
        $this->result = null;
        $this->processing = true;
        $this->validate();

        try {
            $rows = $this->readWorksheetRows($this->file->getRealPath());
        } catch (\Throwable $e) {
            $this->processing = false;
            $this->addError('file', 'Gagal membaca file Excel. Pastikan format .xlsx/.xls valid; hindari rumus error (#NAME?, #REF!). Salin data ke template lalu tempel sebagai nilai saja jika perlu. Detail: '.$e->getMessage());

            return;
        }

        if (count($rows) < 2) {
            $this->processing = false;
            $this->addError('file', 'File Excel kosong atau tidak valid. Minimal harus ada baris header dan satu baris data.');
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
                    'nama' => isset($row[0]) ? trim((string) $row[0]) : null,
                    'email' => isset($row[1]) ? trim((string) $row[1]) : null,
                    'kode_dosen' => isset($row[2]) ? trim((string) $row[2]) : null,
                    'nip' => isset($row[3]) ? trim((string) $row[3]) : null,
                    'nidn' => isset($row[4]) ? trim((string) $row[4]) : null,
                    'gelar_depan' => isset($row[5]) ? trim((string) $row[5]) : null,
                    'gelar_belakang' => isset($row[6]) ? trim((string) $row[6]) : null,
                    'jenis_kelamin' => isset($row[7]) ? trim((string) $row[7]) : null,
                    'tanggal_lahir' => isset($row[8]) ? trim((string) $row[8]) : null,
                    'tempat_lahir' => isset($row[9]) ? trim((string) $row[9]) : null,
                    'provinsi_nama' => isset($row[10]) ? trim((string) $row[10]) : null,
                    'kode_pos' => isset($row[11]) ? trim((string) $row[11]) : null,
                    'negara_nama' => isset($row[12]) ? trim((string) $row[12]) : null,
                    'agama_nama' => isset($row[13]) ? trim((string) $row[13]) : null,
                    'no_hp' => isset($row[14]) ? trim((string) $row[14]) : null,
                    'alamat' => isset($row[15]) ? trim((string) $row[15]) : null,
                ];

                if (empty($data['nama'])) {
                    $errors[] = "Baris {$rowNumber}: Nama wajib diisi.";
                    $skipCount++;

                    continue;
                }

                $uniqueChecks = [];
                if (! empty($data['kode_dosen'])) {
                    if (Dosen::where('kode_dosen', $data['kode_dosen'])->exists()) {
                        $errors[] = "Baris {$rowNumber}: Kode Dosen '{$data['kode_dosen']}' sudah ada di sistem.";
                        $skipCount++;

                        continue;
                    }
                    $uniqueChecks['kode_dosen'] = $data['kode_dosen'];
                }

                if (! empty($data['nip'])) {
                    if (Dosen::where('nip', $data['nip'])->exists()) {
                        $errors[] = "Baris {$rowNumber}: NIP '{$data['nip']}' sudah ada di sistem.";
                        $skipCount++;

                        continue;
                    }
                    $uniqueChecks['nip'] = $data['nip'] ?? null;
                }

                if (! empty($data['nidn'])) {
                    if (Dosen::where('nidn', $data['nidn'])->exists()) {
                        $data['nidn'] = null;
                    } else {
                        $uniqueChecks['nidn'] = $data['nidn'] ?? null;
                    }
                }

                if (! empty($data['email'])) {
                    if (Dosen::where('email', $data['email'])->exists()) {
                        $data['email'] = null;
                    } else {
                        $uniqueChecks['email'] = $data['email'] ?? null;
                    }
                }

                foreach ($processedRows as $processed) {
                    if (! empty($data['kode_dosen']) && ! empty($processed['kode_dosen']) && $data['kode_dosen'] === $processed['kode_dosen']) {
                        $errors[] = "Baris {$rowNumber}: Kode Dosen '{$data['kode_dosen']}' duplikat dalam file.";
                        $skipCount++;

                        continue 2;
                    }
                    if (! empty($data['nip']) && ! empty($processed['nip']) && $data['nip'] === $processed['nip']) {
                        $errors[] = "Baris {$rowNumber}: NIP '{$data['nip']}' duplikat dalam file.";
                        $skipCount++;

                        continue 2;
                    }
                    if (! empty($data['nidn']) && ! empty($processed['nidn']) && $data['nidn'] === $processed['nidn']) {
                        $errors[] = "Baris {$rowNumber}: NIDN '{$data['nidn']}' duplikat dalam file.";
                        $skipCount++;

                        continue 2;
                    }
                }

                $id_negara = null;
                $id_provinsi = null;
                $id_kota = null;
                $id_agama = null;

                if (! empty($data['negara_nama'])) {
                    $negara = Negara::where('nama', 'like', '%'.$data['negara_nama'].'%')->first();
                    if ($negara) {
                        $id_negara = $negara->id;
                    }
                }

                if (! empty($data['provinsi_nama'])) {
                    $provinsiQuery = Provinsi::query();
                    if ($id_negara) {
                        $provinsiQuery->where('id_negara', $id_negara);
                    }
                    $provinsi = $provinsiQuery->where('nama', 'like', '%'.$data['provinsi_nama'].'%')->first();
                    if ($provinsi) {
                        $id_provinsi = $provinsi->id;
                    }
                }

                if (! empty($data['tempat_lahir'])) {
                    $kotaQuery = Kota::query();
                    if ($id_provinsi) {
                        $kotaQuery->where('id_provinsi', $id_provinsi);
                    }
                    $kota = $kotaQuery->where('nama', 'like', '%'.$data['tempat_lahir'].'%')->first();
                    if ($kota) {
                        $id_kota = $kota->id;
                    }
                }

                if (! empty($data['agama_nama'])) {
                    $agama = Agama::where('nama', 'like', '%'.$data['agama_nama'].'%')->first();
                    if ($agama) {
                        $id_agama = $agama->id;
                    }
                }

                if (! empty($data['jenis_kelamin']) && ! in_array(strtoupper($data['jenis_kelamin']), ['L', 'P'])) {
                    $jenis_kelamin = null;
                } else {
                    $jenis_kelamin = ! empty($data['jenis_kelamin']) ? strtoupper($data['jenis_kelamin']) : null;
                }

                $dosenData = [
                    'nama' => $data['nama'],
                    'email' => $data['email'] ?: null,
                    'kode_dosen' => $data['kode_dosen'] ?: null,
                    'nip' => $data['nip'] ?: null,
                    'nidn' => $data['nidn'] ?: null,
                    'gelar_depan' => $data['gelar_depan'] ?: null,
                    'gelar_belakang' => $data['gelar_belakang'] ?: null,
                    'jenis_kelamin' => $jenis_kelamin,
                    'tanggal_lahir' => $data['tanggal_lahir'] ?: null,
                    'tempat_lahir' => $data['tempat_lahir'] ?: null,
                    'no_hp' => $data['no_hp'] ?: null,
                    'alamat' => $data['alamat'] ?: null,
                    'kode_pos' => $data['kode_pos'] ?: null,
                    'agama' => $id_agama,
                    'id_kota' => $id_kota,
                    'id_provinsi' => $id_provinsi,
                    'id_negara' => $id_negara,
                ];

                Dosen::create($dosenData);
                $successCount++;
                $processedRows[] = $uniqueChecks;
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
            Log::error('Import dosen gagal', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->addError('file', 'Terjadi kesalahan saat mengimport data: '.$e->getMessage());
        }

        $this->processing = false;
    }

    /**
     * Sama dengan DosenController::readDosenImportWorksheetRows — perhitungan rumus Excel
     * dimatikan agar sel error (#NAME?, #REF!, dll.) tidak memicu exception saat dikonversi
     * ke array.
     *
     * @return array<int, array<int, mixed>>
     */
    private function readWorksheetRows(string $path): array
    {
        if (! is_readable($path)) {
            throw new \RuntimeException('Berkas tidak dapat dibaca.');
        }

        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getActiveSheet();

        $highestRow = (int) $worksheet->getHighestDataRow();
        if ($highestRow < 1) {
            return [];
        }

        // Template impor: 16 kolom (indeks 0–15), sama seperti DosenController::downloadTemplate.
        $maxColIndex = 16;
        $lastColLetter = Coordinate::stringFromColumnIndex($maxColIndex);
        $cappedRow = min($highestRow, 100000);
        $range = 'A1:'.$lastColLetter.$cappedRow;

        $rows = $worksheet->rangeToArray($range, null, false, false, false);

        return array_values($rows);
    }

    public function render()
    {
        return view('livewire.admin.dosen.import')->extends('layouts.web');
    }
}
