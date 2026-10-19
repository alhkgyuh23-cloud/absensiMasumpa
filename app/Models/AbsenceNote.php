<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceNote extends Model
{
    protected $fillable = [
        'student_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'jenis',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Cek apakah tanggal tertentu berada di dalam rentang sakit/izin ini.
     */
    public function mencakupTanggal(\Carbon\Carbon $tanggal): bool
    {
        return $tanggal->toDateString() >= $this->tanggal_mulai->toDateString()
        && $tanggal->toDateString() <= $this->tanggal_selesai->toDateString();
    }
}