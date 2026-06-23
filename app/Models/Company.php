<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['name', 'status'];

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
