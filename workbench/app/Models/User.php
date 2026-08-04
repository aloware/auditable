<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * The authenticatable model audits are attributed to.
 *
 * Uses SoftDeletes because AuditController eager-loads the `user` relation
 * with `withTrashed()`.
 */
class User extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = true;
}
