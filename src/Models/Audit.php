<?php

namespace Aloware\Auditable\Models;

use Aloware\Auditable\Enums\EventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Class Audit
 *
 * @OA\Schema(
 *     schema="Audit",
 *     @OA\Xml(name="Audit"),
 *     description="Audit is a model for keeping traces of Model changes.",
 *     @OA\Schema(type="object"),
 *     required={"auditable_type", "auditable_id"},
 *  )
 */
class Audit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'changes' => 'json',
        'index' => 'json',
    ];

    /**
     * Set the relation to the parent Auditable to which this audit refers.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Set the relation to an audited (added/removed) related model.
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auditable.user_model'));
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('auditable.audits_table');
    }

    public function scopeByModel(Builder $builder, string $model): void
    {
        $builder->where('auditable_type', $model);
    }

    public function scopeByType(Builder $builder, EventType $event_type): void
    {
        $builder->where('event_type', $event_type);
    }

    public function scopeByLabel(Builder $builder, string $label): void
    {
        $builder->where('label', $label);
    }

    public function scopeWithModified(Builder $builder, string $attribute): void
    {
        $builder
            ->whereIn('event_type', [
                EventType::MODEL_CREATED,
                EventType::MODEL_UPDATED,
                EventType::MODEL_DELETED,
            ])
            ->whereJsonContains('index', $attribute);
    }

    public function scopeWithModifiedRelation(Builder $builder, ?string $relation = null, ?string $attribute = null): void
    {
        $builder
            ->whereIn('event_type', [
                EventType::RELATION_CREATED,
                EventType::RELATION_UPDATED,
                EventType::RELATION_DELETED,
            ])
            ->when($relation, fn ($query) => $query->where('related_type', $relation))
            ->when($attribute, fn ($query) => $query->whereJsonContains('index', $attribute));
    }

    public function scopeModifiedByUser(Builder $builder, Model|int $user): void
    {
        $user = $user instanceof Model ? $user->getKey() : $user;

        $builder->where('user_id', $user);
    }
}
