<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'industry', 'website', 'address', 'status'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }

    public function mcpServers()
    {
        return $this->hasMany(McpServer::class);
    }
}
