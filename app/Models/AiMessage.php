<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ai_chat_id', 'branch_id', 'user_id', 'role', 'content'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
