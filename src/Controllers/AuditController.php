<?php

namespace Aloware\Auditable\Controllers;

use Aloware\Auditable\Enums\EventType;
use Exception;
use Illuminate\Http\Request;

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

        return response()->json($data);
    }
}