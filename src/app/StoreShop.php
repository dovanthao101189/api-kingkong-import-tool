<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreShop extends Model
{
    use SoftDeletes;

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'customer_id' => 'integer'
    ];

    protected $fillable = [
        'type_shop',
        'store_name',
        'store_front',
        'api_key',
        'secret_key',
        'customer_id',
        'created_at',
        'updated_at',
    ];

    protected $table = 'store_shops';
}
