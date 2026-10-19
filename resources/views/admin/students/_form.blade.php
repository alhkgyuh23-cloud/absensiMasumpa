@csrf

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-slate-700">Nama Siswa</label>
        <input type="text" name="name" value="{{ old('name', $student->name ?? '') }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">NIS (opsional)</label>
        <input type="text" name="nis" value="{{ old('nis', $student->nis ?? '') }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        @error('nis') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Kode Kartu</label>
        <input type="text" name="card_id" value="{{ old('card_id', $student->card_id ?? '') }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono">
        @error('card_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Kelas</label>
        <select name="class_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected(old('class_id', $student->class_id ?? '') == $class->id)>
                    {{ $class->name }}
                </option>
            @endforeach
        </select>
        @error('class_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<label class="mt-4 flex items-center gap-2 text-sm text-slate-600">
    <input type="checkbox" name="is_active" value="1"
        @checked(old('is_active', $student->is_active ?? true)) class="rounded border-slate-300">
    Kartu aktif
</label>

<hr class="my-6 border-slate-200">

<h2 class="text-sm font-semibold text-slate-700">Kontak Orang Tua (untuk notifikasi WhatsApp)</h2>

<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-slate-700">Nama Orang Tua/Wali</label>
        <input type="text" name="parent_name" value="{{ old('parent_name', $primaryContact->name ?? '') }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        @error('parent_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Hubungan (opsional)</label>
        <input type="text" name="parent_relationship" placeholder="Ayah / Ibu / Wali"
            value="{{ old('parent_relationship', $primaryContact->relationship ?? '') }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Nomor WhatsApp</label>
        <input type="text" name="parent_wa_number" placeholder="628xxxxxxxxxx"
            value="{{ old('parent_wa_number', $primaryContact->wa_number ?? '') }}"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono">
        @error('parent_wa_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<button type="submit"
    class="mt-6 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
    Simpan
</button>