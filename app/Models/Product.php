<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];

    protected $casts = ['base_price' => 'integer', 'is_active' => 'boolean'];

    public function materials() { return $this->hasMany(ProductMaterial::class); }
    public function colors()    { return $this->hasMany(ProductColor::class); }
    public function sizes()     { return $this->hasMany(ProductSize::class); }
}
