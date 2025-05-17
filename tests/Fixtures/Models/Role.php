<?php

namespace Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Validation rules for creating a role.
     *
     * @var array
     */
    public static $rules = [
        'store' => [
            'name' => 'required|string|max:255|unique:roles',
            'description' => 'nullable|string',
        ],
        'update' => [
            'name' => 'required|string|max:255|unique:roles,name,{id}',
            'description' => 'nullable|string',
        ],
    ];

    /**
     * The users that belong to the role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Scope a query to only include roles with a specific name.
     */
    public function scopeWithName($query, $name)
    {
        return $query->where('name', $name);
    }
}