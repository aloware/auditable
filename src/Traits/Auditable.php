<?php

namespace Aloware\Auditable\Traits;

use Aloware\Auditable\Enums\EventType;
use Aloware\Auditable\Models\Audit;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

trait Auditable
{
    /**
     * Boot the auditable trait.
     *
     * Registers handlers to process auto-auditing CRUD events of Auditable models.
     */
    public static function bootAuditable(): void
    {
        static::created(
            fn (Model $model) => self::selfAudit($model, EventType::MODEL_CREATED)
        );

        static::updated(
            fn (Model $model) => self::selfAudit($model, EventType::MODEL_UPDATED)
        );

        static::deleted(
            fn (Model $model) => self::selfAudit($model, EventType::MODEL_DELETED)
        );
    }

    /**
     * Define the relationship from an Auditable model to its Audits.
     */
    public function audits(): MorphMany
    {
        return $this->morphMany(Audit::class, 'auditable');
    }

    /**
     * By default, the trait will audit and log changes to any and all
     * attributes present in the parent model.
     *
     * To override the default behavior, pick one of two options:
     *
     * 1. an @auditable array property in the model
     * 2. override this method for full control; return a list of the
     *    attributes to track
     */
    public function auditableAttributes(): array
    {
        return $this->auditable ?? array_keys($this->getAttributes());
    }

    public function auditRelation(EventType $event_type, Model $related, string $label = 'relation-audit'): ?Audit
    {
        if (!in_array($event_type, [EventType::RELATION_CREATED, EventType::RELATION_UPDATED, EventType::RELATION_DELETED])) {
            throw new Exception('Invalid Relation Event for Audit');
        }

        try {
            $changes = $this->createAuditableChangesList($event_type, $related);

            return $this->audits()->create([
                'event_type' => $event_type,
                'related_type' => get_class($related),
                'related_id' => $related->getKey(),
                'changes' => $changes,
                'label' => $label,
                'index' => array_keys($changes),
                'user_id' => Auth::user()?->getKey(),
            ]);
        } catch (Throwable $e) {
            Log::error('[Auditable] Audit failed: ' . $e->getMessage(), [
                'model' => get_class($this),
                'model_id' => $this->getKey(),
                'event_type' => $event_type->value,
                'exception' => $e,
            ]);
        }
    }

    public function audit(string $property, $before, $after, string $label = 'custom-audit', bool $property_must_exist = true): ?Audit
    {
        if ($property_must_exist && !array_key_exists($property, $this->getAttributes())) {
            throw new Exception(
                "Cannot audit invalid property $property in model " . get_class($this)
            );
        }

        return $this->createAudit(EventType::CUSTOM_EVENT, [
            $property => [$before, $after],
        ], $label);
    }

    protected function createSelfAudit(EventType $event_type): void
    {
        $changes = $this->createAuditableChangesList($event_type, $this);

        if (!empty($changes)) {
            $this->createAudit($event_type, $changes, 'self-audit');
        }
    }

    private function createAudit(EventType $event_type, array $changes, ?string $label = null): ?Audit
    {
        try {
            return $this->audits()->create([
                'event_type' => $event_type,
                'changes' => $changes,
                'label' => $label,
                'index' => array_keys($changes),
                'user_id' => Auth::user()?->getKey(),
            ]);
        } catch (Throwable $e) {
            Log::error('[Auditable] Audit failed: ' . $e->getMessage(), [
                'model' => get_class($this),
                'model_id' => $this->getKey(),
                'event_type' => $event_type->value,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Build the changes list. The shape depends on the event type:
     * - Creation / Added Relation: [ attribute1 => [original, changed], attribute2 => ...]
     * - Updated / Updated Relation: [ attribute1 => value1, attribute2 => ...]
     * - Deleted / Removed Relation: [ attribute1 => oldValue1, attribute2 => ...]
     */
    private function createAuditableChangesList(EventType $event_type, Model $model): array
    {
        // Consider only auditable fields (all attributes for non-Auditable relations)
        $is_model_auditable = method_exists($model, 'auditableAttributes') && is_callable([$model, 'auditableAttributes']);

        $auditable_attributes_as_keys = array_flip(
            $is_model_auditable ? $model->auditableAttributes() : array_keys($model->getAttributes())
        );

        $attributes = array_intersect_key($model->getAttributes(), $auditable_attributes_as_keys);
        $modified = array_intersect_key($model->getDirty(), $auditable_attributes_as_keys);
        $original = array_intersect_key($model->getRawOriginal(), $auditable_attributes_as_keys);

        if ($this->eventIsTouch($modified) && $this->ignoreTouchEvent()) {
            return [];
        }

        switch ($event_type) {
            case EventType::MODEL_CREATED:
            case EventType::RELATION_CREATED:
                $changes = $attributes;
                break;

            case EventType::MODEL_DELETED:
            case EventType::RELATION_DELETED:
                $changes = $original;
                break;

            case EventType::MODEL_UPDATED:
            case EventType::RELATION_UPDATED:
                foreach ($modified as $attribute => $value) {
                    $changes[$attribute] = [$original[$attribute] ?? null, $value];
                }
                break;
        }

        return $changes ?? [];
    }

    private function eventIsTouch(array $modified): bool
    {
        return array_keys($modified) === ['updated_at'];
    }

    private function ignoreTouchEvent(): bool
    {
        return !($this->auditTouch ?? config('auditable.audit_touch'));
    }

    private static function selfAudit(Model $model, EventType $event_type): void
    {
        try {
            $model->createSelfAudit($event_type);
        } catch (Throwable $e) {
            Log::error('[Auditable] Audit failed: ' . $e->getMessage(), [
                'model' => get_class($model),
                'model_id' => $model->getKey(),
                'event_type' => $event_type->value,
                'exception' => $e,
            ]);
        }
    }
}
