@csrf

{{-- Filter kelas: hanya alat bantu, tidak ikut dikirim ke server (tanpa atribut name) --}}
<div>
    <label for="class_filter" class="block text-sm font-medium text-slate-700">Kelas</label>
    <select id="class_filter" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">Semua kelas</option>
        @foreach ($classes as $class)
            @if ($class->students->isNotEmpty())
                <option value="{{ $class->id }}">{{ $class->name }}</option>
            @endif
        @endforeach
    </select>
    <p class="mt-1 text-xs text-slate-400">Pilih kelas untuk mempersempit daftar siswa.</p>
</div>

<div class="mt-4">
    <label for="student_id" class="block text-sm font-medium text-slate-700">Siswa</label>
    <select name="student_id" id="student_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">— Pilih siswa —</option>
        @foreach ($classes as $class)
            @if ($class->students->isNotEmpty())
                <optgroup label="{{ $class->name }}" data-class-id="{{ $class->id }}">
                    @foreach ($class->students as $student)
                        <option value="{{ $student->id }}"
                            @selected(old('student_id', $note->student_id ?? '') == $student->id)>
                            {{ $student->name }} ({{ $class->name }})
                        </option>
                    @endforeach
                </optgroup>
            @endif
        @endforeach
    </select>
    @error('student_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>

<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-slate-700">Dari Tanggal</label>
        <input type="date" name="tanggal_mulai"
            value="{{ old('tanggal_mulai', isset($note) ? $note->tanggal_mulai->format('Y-m-d') : '') }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        @error('tanggal_mulai') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Sampai Tanggal</label>
        <input type="date" name="tanggal_selesai"
            value="{{ old('tanggal_selesai', isset($note) ? $note->tanggal_selesai->format('Y-m-d') : '') }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        @error('tanggal_selesai') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-4">
    <label class="block text-sm font-medium text-slate-700">Jenis</label>
    <select name="jenis" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="sakit" @selected(old('jenis', $note->jenis ?? '') == 'sakit')>Sakit</option>
        <option value="izin" @selected(old('jenis', $note->jenis ?? '') == 'izin')>Izin</option>
    </select>
    @error('jenis') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>

<div class="mt-4">
    <label class="block text-sm font-medium text-slate-700">Keterangan (opsional)</label>
    <input type="text" name="keterangan" placeholder="Misal: demam, acara keluarga"
        value="{{ old('keterangan', $note->keterangan ?? '') }}"
        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    @error('keterangan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>

<button type="submit"
    class="mt-6 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
    Simpan
</button>