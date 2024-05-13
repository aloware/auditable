<?php

namespace Aloware\Auditable\Enums;

enum EventType: string
{
    case MODEL_CREATED = 'model_created';
    case MODEL_UPDATED = 'model_updated';
    case MODEL_DELETED = 'model_deleted';

    case RELATION_CREATED = 'relation_created';
    case RELATION_UPDATED = 'relation_updated';
    case RELATION_DELETED = 'relation_deleted';

    case CUSTOM_EVENT = 'custom_event';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}