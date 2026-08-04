<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A non-Auditable related model, used to exercise relation audits.
 *
 * Uses SoftDeletes because AuditController eager-loads the `related`
 * relation with `withTrashed()`.
 */
class Tag extends Model
{
    use SoftDeletes;

    protected $guarded = [];
}
