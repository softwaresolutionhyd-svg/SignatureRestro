<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class RoomPersonType extends Model
{
    use BelongsToCompany;

    protected $connection = 'tenant';

    protected $table = 'room_person_types';

    protected $fillable = ['company_id', 'name', 'sort_order', 'active'];

    protected $casts = ['active' => 'bool', 'sort_order' => 'int'];

    public function isInUse(): bool
    {
        return RoomRate::query()->where('person_type', $this->name)->exists()
            || RoomBooking::query()->where('person_type', $this->name)->exists();
    }
}
