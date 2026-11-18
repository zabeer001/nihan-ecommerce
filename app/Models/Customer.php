<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'address'];
    protected $hidden = ['normalized_phone'];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $customer->normalized_phone = self::normalizePhone($customer->phone);
        });
    }

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($digits, '00880')) $digits = substr($digits, 2);
        if (preg_match('/^8801[3-9][0-9]{8}$/', $digits)) $digits = '0'.substr($digits, 3);
        return $digits !== '' ? $digits : mb_strtolower(trim($phone));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
