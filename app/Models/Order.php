<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'total_price'    => 'integer',
        'shirt_arrived'  => 'boolean',
        'dtf_arrived'    => 'boolean',
        'ready_to_press' => 'boolean',
    ];

    public function items()  { return $this->hasMany(OrderItem::class); }
    public function stages() { return $this->hasMany(ProductionStage::class)->orderBy('seq'); }
    public function logs()   { return $this->hasMany(ActivityLog::class)->orderByDesc('created_at'); }
}
