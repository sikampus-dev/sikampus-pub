<?php

namespace App\Livewire\Admin\KalenderAkademik;

use App\Livewire\Admin\KalenderAkademik\Concerns\ForwardsIndexState;
use App\Models\KalenderAkademik;
use App\Models\Semester;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use ForwardsIndexState;

    public ?int $eventId = null;

    public string $nama = '';

    public string $kategori = '';

    public string $id_semester = '';

    public string $tanggal_mulai = '';

    public string $tanggal_selesai = '';

    public string $deskripsi = '';

    public function mount(?int $id = null): void
    {
        $this->eventId = $id;
        $this->resolveBackUrl();

        if ($id === null) {
            return;
        }

        $event = KalenderAkademik::findOrFail($id);

        $this->nama = $event->nama;
        $this->kategori = $event->kategori;
        $this->id_semester = (string) ($event->id_semester ?? '');
        $this->tanggal_mulai = $event->tanggal_mulai?->format('Y-m-d\TH:i') ?? '';
        $this->tanggal_selesai = $event->tanggal_selesai?->format('Y-m-d\TH:i') ?? '';
        $this->deskripsi = (string) $event->deskripsi;
    }

    protected function rules(): array
    {
        // id_semester terikat <select> sebagai string ('' berarti "semua semester / global"),
        // jadi rule 'integer'/'exists' hanya dipasang kalau memang diisi — 'nullable' saja tidak
        // cukup karena '' bukan null dan akan gagal di rule 'integer'.
        $idSemesterRules = $this->id_semester === '' ? ['nullable'] : ['required', 'integer', 'exists:semester,id'];

        return [
            'nama' => ['required', 'string', 'max:255'],
            'kategori' => ['required', 'string', Rule::in(array_keys(KalenderAkademik::KATEGORI_OPTIONS))],
            'id_semester' => $idSemesterRules,
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'deskripsi' => ['nullable', 'string'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        $validated['id_semester'] = $validated['id_semester'] !== null && $validated['id_semester'] !== ''
            ? (int) $validated['id_semester']
            : null;
        $validated['deskripsi'] = $validated['deskripsi'] !== '' ? $validated['deskripsi'] : null;

        if ($this->eventId) {
            $validated['updated_by'] = auth()->id();
            KalenderAkademik::findOrFail($this->eventId)->update($validated);
        } else {
            $validated['created_by'] = auth()->id();
            KalenderAkademik::create($validated);
        }

        session()->flash('status', 'Event kalender akademik berhasil disimpan.');

        return redirect()->to($this->backUrl);
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.kalender-akademik.form', [
            'kategoriOptions' => KalenderAkademik::KATEGORI_OPTIONS,
            'semesterOptions' => Semester::whereNull('deleted_at')->orderByDesc('kode')->get(['id', 'kode', 'nama'])
                ->mapWithKeys(fn ($s) => [(string) $s->id => "{$s->nama} ({$s->kode})"])->all(),
        ])->extends('layouts.web');
    }
}
