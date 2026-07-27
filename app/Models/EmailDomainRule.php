<?php

namespace App\Models;

use App\Models\RBAC\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmailDomainRule extends Model
{
    protected $fillable = [
        'domain',
        'role_id',
        'is_active',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (EmailDomainRule $rule) {
            $rule->domain = Str::lower(trim($rule->domain, " \t\n\r\0\x0B@"));
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
