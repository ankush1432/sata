<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivationCodeBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'activation_plan_id',
        'plan_id',
        'created_by_admin_id',
        'quantity',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (ActivationCodeBatch $batch) {
            if (empty($batch->activation_plan_id) && !empty($batch->plan_id)) {
                $batch->activation_plan_id = $batch->plan_id;
            } elseif (empty($batch->plan_id) && !empty($batch->activation_plan_id)) {
                $batch->plan_id = $batch->activation_plan_id;
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ActivationPlan::class, 'activation_plan_id')
            ->withDefault(function () {
                if ($this->plan_id) {
                    return ActivationPlan::find($this->plan_id);
                }
                return null;
            });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ActivationCode::class, 'batch_id');
    }
}
