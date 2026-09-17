<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleRate extends Model
{
    protected $table = 'vehicle_rates';

    protected $primaryKey = 'vehicle_id';

    public $timestamps = false;

    protected $fillable = ['vehicle_type', 'label', 'description', 'base_rate', 'is_active'];
}
