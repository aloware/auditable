<?php

namespace Workbench\App\Models;

use Aloware\Auditable\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An Auditable model that restricts auditing to an explicit attribute list
 * and vetoes individual changes, exercising both override hooks.
 */
class Article extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $guarded = [];

    /**
     * Only these attributes are audited (consumed by auditableAttributes()).
     */
    protected array $auditable = ['title', 'body'];

    /**
     * Audit touch-only events, overriding the config default.
     */
    protected bool $auditTouch = true;

    protected function shouldAuditChange($attribute, $old, $new): bool
    {
        return $new !== 'skip-me';
    }
}
