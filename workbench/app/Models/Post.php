<?php

namespace Workbench\App\Models;

use Aloware\Auditable\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A plain Auditable model that audits every attribute (the default behaviour).
 */
class Post extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $guarded = [];

    public static ?LengthAwarePaginator $post_loaded = null;

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Records what the controller handed back, so the hook can be asserted on.
     */
    public static function postLoadAudits(LengthAwarePaginator $data): void
    {
        static::$post_loaded = $data;
    }
}
