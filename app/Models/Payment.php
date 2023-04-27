<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $guarded=['id', 'created_at'];

    public function getAmountAttribute($value)
{
    return number_format($value /100, 2);
}

    public function user()
    {
        return $this->belongsTo(User::class);
    }


}
