<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletAccount extends Model
{
    protected $table = 'wallets';

    protected $fillable = ['branch_id', 'code', 'balance'];

    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }
}
