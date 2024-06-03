## Aloware Auditable

### Temporary Note

The main UI Vue Component will be included in the following version (for TEL-360). You may skip any
documentation referring to the UI in this file, for now.

### Install

```
# Install Composer package
composer require aloware/auditable

# Publish Auditable config file (config/auditable.php)
# Select the option for "Provider: Aloware\Auditable\ServiceProvider"
php artisan vendor:publish
# Check the config file, make changes if needed

# Create the `audits` table
php artisan migrate
```

### Basic Usage

To make Eloquent Models auditable, simply add the Auditable Trait to the Model:

```php
namespace App\Models;

use Aloware\Auditable\Traits;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use Auditable;

    // ...
}
```

By default, this will audit all changes to any property of the model on Create, Update and soft/hard Delete. To customize which fields should be auditable, to audit relation changes, or store custom audits, check the section on `Advanced Usage`.

### Configuration

The default configuration should be enough for standard Laravel applications. If you need to customize it, read on for a quick explanation of those config fields that may not be obvious (check the config file for the full list).

```
/**
 * Main audit endpoint will be `://your.site/api/audits/{model}/{model_id}`
 */
'route_prefix' => '/api',

/**
 * Route middleware applied to auditable endpoints.
 */
'route_middleware' => [
    'api',
],

/**
 * Change this if you're using a non-standard table name for the Authenticatable model.
 */
'users_table' => 'users',

/**
 * Model touches may add a lot of unnecessary noise, so they're ignored by default. Set it to true
 * if you prefer to audit them.
 * NOTE: a touch is defined as an atomic change to the `updated_at` attribute.
 */
'audit_touch' => false,

/**
 * Pagination setting for the Auditable index page's default UI.
 */
'per_page' => 20,

/**
 * Aliases are used by the Auditable default UI, and are used by the main endpoint:
 *
 * Example:
 *
 *   'models' => ['role' => App\Models\Role]
 *
 * The `model_alias` passed to the `audits-table` default Vue component would be `role` and the endpoint to
 * fetch the Role Model's audits would be `://your.site/{route_prefix}/audits/role/{role_id}`
 */
'models' => [
    'role' => App\Models\Role::class,
],

### The Audit Model

The Audit model contains the following fields:

- bigint `id`: auto-generated
- morph columns:
  - string `auditable_type`: the fully-qualified class name of the audited Model
  - bigint `auditable_id`: the id of the audited Model
- enum `event_type`: see the EventType Enum for possible values
- longtext `changes`: summary of audited changes (more info below)
- string `label`: an optional label to identify this type of audit (`self-audit` when automatically generated)
- json `index`: this is an array of affected attributes' names present in `changes`, to simplify filtering/searching
- integer `user_id`: the ID of the authenticated user who performed the audited change
- standard timestamps:
  - timestamp `created_at`
  - timestamp `updated_at`

**The `changes` Audit attribute**

This field is a JSON string with one of two possible formats:

- On Create and Delete, it's a mapping of auditable attributes to their corresponding values
  - Example: ['id' => 5, 'created_at' => '2024-05-01 00:01:02', ...]
  - For create, it stores the attribute values right AFTER creation
  - For delete, it stores the attribute values right BEFORE deletion (for both hard- and soft-deletes)

- On Update, the format of the `changes` JSON is a compact "diff":
  - Example: ['role_id' => [5, 3], 'active' => [true, false]]
  - The example above means that this audit registered 2 changes:
    - The Model's `role_id` attribute changed from 5 to 3
    - The Model's `active` attribute changed from true to false

In general, you don't need to be concerned with the internals of the Audit's `changes`, as long as you use
the package's default UI, which already handles interpreting the field's format.

### Advanced Usage

The Auditable Trait exposes the following API:

- Auditable@audits(): MorphMany
  - This is the polymorphic relation allowing to retrieve a Model's audits from the Model
  - Example: Role::first()->audits // Returns a list of Audit instances for the Role Model

- Auditable@auditableAttributes(): array
  - By default, all of the Model's attributes are auditable
  - You may customize which attributes should be audited, in one of two possible ways:
    - Create a property `auditable` in the Model, which is an array of attribute names
    - Or, if you need more control or there is logic required to define what's auditable:
      - Override this method in your Model to restrict audits to only certain attributes
      - The overridden methods must also return an array of attribute names to be audited

- Auditable@auditRelation(EventType $event_type, Model $related, string $label = 'relation-audit'): ?Audit
  - Use one of the `RELATION_*` Event Types from the Enum `\Aloware\Auditable\Enums\EventType`
  - Self-audits only cover the Model's attributes. In case of relations where the Foreign Key leaves in another table,
    audits will not happen automatically. Instead, we use this method to record them.
  - The `$related` argument takes the related Model, in the state we want it to be recorded (its attributes will be
    persisted in the newly created Audit)
  - The label is optional; if omitted, it'll default to 'relation-audit'
  - The method will return a new Audit instance on success, or null on failure (the failure will be logged)

- Auditable@audit(string $property, $before, $after, string $label = 'custom-audit', bool $property_must_exist = true): ?Audit
  - You may use this method for custom audits, i.e. audits which are not automatically generated
    - Example: $role->audit('accessed', null, 2, 'access-control', false)
    - The generated Audit will look as if an attribute called 'accessed' had changed from `null` to `2`
    - Notice that yo need to pass `false` for `$property_must_exist` if you are using a `$property` name
      which is not an actual attribute of the Model.
  - This may be helpful to audit attributes that you excluded from the `auditableProperties` in order to handle them on
    a one-by-one basis
  - The method will return a new Audit instance on success, or null on failure (the failure will be logged)
