<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = ['tingkat', 'rombel', 'name'];

    protected static function booted(): void
    {
        // Nama kelas dibuat otomatis: "X - A", "XI - B", "XII - F"
        static::saving(function (SchoolClass $class) {
            $romawi = self::toRomawi((int) $class->tingkat);
            $rombel = strtoupper($class->rombel);

            $class->rombel = $rombel;
            $class->name = "{$romawi} - {$rombel}";
        });
    }

    public static function toRomawi(int $tingkat): string
    {
        return match ($tingkat) {
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
            default => (string) $tingkat,
        };
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }
}