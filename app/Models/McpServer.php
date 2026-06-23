<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpServer extends Model
{
    protected $fillable = ['company_id', 'name', 'url'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
