<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $table = 'clients';

    protected $primaryKey = 'client_id';

    protected $fillable = [
        'user_id', 'company_name', 'contact_person', 'email', 'phone',
        'address', 'client_type', 'payment_terms', 'is_vat_registered', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_vat_registered' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
