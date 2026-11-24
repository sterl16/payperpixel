<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditTransaction extends Model
{
    public const PURCHASE = 'purchase';
    public const HOLD = 'hold';
    public const RELEASE = 'release';
    public const CHARGE = 'charge';

    protected $fillable = ['user_id', 'generation_id', 'type', 'amount_nanos'];
}
