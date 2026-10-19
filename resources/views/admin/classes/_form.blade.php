@csrf

<div>
    <label class="block text-sm font-medium text-slate-700">Tingkat</label>
    <select name="tingkat" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        @foreach ([10, 11, 12] as $t)
            <option value="{{ $t }}" @selected(old('tingkat', $schoolClass->tingkat ?? '') == $t)>{{ $t }}</option>
        @endforeach
    </select>
</div>

<div class="mt-4">
    <label class="block text-sm font-medium text-slate-700">Rombel</label>
    <input type="text" name="rombel" maxlength="5" placeholder="A atau ICP"
        value="{{ old('rombel', $schoolClass->rombel ?? '') }}"
        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
    <p class="mt-1 text-xs text-slate-400">Huruf rombel, contoh: A, B, C, D, E, F</p>
</div>

<button type="submit"
    class="mt-6 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
    Simpan
</button>