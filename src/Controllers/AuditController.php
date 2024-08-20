<?php

namespace Aloware\Auditable\Controllers;

use Aloware\Auditable\Enums\EventType;
use Exception;
use Illuminate\Http\Request;
use App\Models\RingGroup;
use App\Models\User;

class AuditController
{
    public function index(Request $request, string $model, $id)
    {
        $model_class = config('auditable.models')[$model] ?? null;

        if (!$model_class) {
            throw new Exception("Auditable model alias $model is not defined");
        }

        $user = $request->get('user_id');
        $type = $request->get('type');
        $label = $request->get('label');
        $attribute = $request->get('attribute');
        $relation = $request->get('relation');
        $from = $request->get('from');
        $to = $request->get('to');

        /** @var \Illuminate\Database\Eloquent\Builder $builder */
        $builder = $model_class::findOrFail($id)->audits();

        $data = $builder
            ->orderByDesc('id')
            ->with([
                'user' => fn ($q) => $q->withTrashed()->withoutGlobalScopes()->select('id', 'first_name', 'last_name'),
                'auditable' => fn ($q) => $q->withTrashed()->withoutGlobalScopes(),
                'related' => fn ($q) => $q->withTrashed()->withoutGlobalScopes()
            ])
            ->when($user, fn ($query) => $query->modifiedByUser($user))
            ->when($type, fn ($query) => $query->byType(EventType::strToEventType($type)))
            ->when($label, fn ($query) => $query->byLabel($label))
            ->when($attribute && is_null($relation), fn ($query) => $query->withModified($attribute))
            ->when($relation, fn ($query) => $query->withModifiedRelation($relation, $attribute))
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->paginate(config('auditable.per_page'));

        $data->transform(function ($audit) {
            $this->transformRingGroups($audit);
            $this->transformUsers($audit);
            return $audit;
        });

        return response()->json($data);
    }

    private function transformRingGroups($audit)
    {
        if (isset($audit->changes['ring_group_id'])) {
            $ringGroupIds = $audit->changes['ring_group_id'];

            if (!is_array($ringGroupIds)) {
                $ringGroupIds = [$ringGroupIds];
            }

            $ringGroupIds = array_filter($ringGroupIds, fn($id) => !is_null($id));

            $ringGroups = RingGroup::whereIn('id', $ringGroupIds)->pluck('name', 'id');

            $changes = $audit->getAttribute('changes');

            $changes['ring_group_id'] = array_map(function($id) use ($ringGroups) {
                return $ringGroups[$id] . " (#$id)" ?? "Unknown RingGroup #$id";
            }, $ringGroupIds);

            $audit->setAttribute('changes', $changes);
        }
    }

    private function transformUsers($audit)
    {
        if (isset($audit->changes['user_id'])) {
            $usersIds = $audit->changes['user_id'];

            if (!is_array($usersIds)) {
                $usersIds = [$usersIds];
            }

            $usersIds = array_filter($usersIds, fn($id) => !is_null($id));
            $users    = User::whereIn('id', $usersIds)->get(['id', 'first_name', 'last_name']);

            $users = $users->mapWithKeys(function($user) {
                return [$user->id => $user->first_name . ' ' . $user->last_name];
            });

            $changes = $audit->getAttribute('changes');

            $changes['user_id'] = array_map(function($id) use ($users) {
                return $users[$id] . " (#$id)" ?? "Unknown User #$id";
            }, $usersIds);

            $audit->setAttribute('changes', $changes);
        }
    }
}
