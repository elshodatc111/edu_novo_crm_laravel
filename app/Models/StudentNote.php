<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** v8 B8: xodimlar o'rtasidagi ichki eslatmalar (o'quvchi profilidagi bitta "Eslatma" maydonidan farqli). */
class StudentNote extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'student_id', 'user_id', 'body'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
