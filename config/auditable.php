<?php

return [
    /**
     * Route prefix for auditable endpoints.
     */
    'route_prefix' => '/api',

    /**
     * Route middleware for auditable endpoints.
     */
    'route_middleware' => [
        'auth:api',
    ],

    /**
     * Name of the database table that holds the authenticatable users.
     */
    'user_table' => 'users',

    /**
     * Fully qualified name of the authenticatable user Model.
     */
    'user_model' => App\Models\User::class,

    /**
     * Name of the database table that holds the generated audits.
     */
    'audits_table' => 'audits',

    /**
     * Default for auditing touch changes (i.e. isolated changes to the updated_at attribute of an Auditable Model).
     */
    'audit_touch' => false,

    /**
     * Default amount of items to load per page in Audits lists.
     */
    'per_page' => 10,

    /**
     * Define aliases for Auditable models, with the alias as the key and the fully-qualified Class name as its value.
     *
     * Example: [
     *     'user' => App\Models\User,
     *     'role' => App\Models\Role,
     *     // ...
     * ]
     */
    'models' => [
        'line' => App\Models\Campaign::class,
    ],

    /**
     * Define excluded attributes for Auditable models
     *
     */
    'excluded_attributes' => [
        'updated_at'
    ],
];