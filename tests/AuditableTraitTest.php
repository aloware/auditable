<?php

namespace Aloware\Auditable\Tests;

use Aloware\Auditable\Enums\EventType;
use Aloware\Auditable\Models\Audit;
use Exception;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\Article;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class AuditableTraitTest extends TestCase
{
    #[Test]
    public function it_audits_model_creation_with_the_full_attribute_set(): void
    {
        $post = $this->makePost();

        $audit = $post->audits()->sole();

        $this->assertSame(EventType::MODEL_CREATED, $audit->event_type);
        $this->assertSame(Post::class, $audit->auditable_type);
        $this->assertSame($post->getKey(), $audit->auditable_id);
        $this->assertSame('self-audit', $audit->label);
        $this->assertSame('Original title', $audit->changes['title']);
        $this->assertSame('Original body', $audit->changes['body']);
        $this->assertContains('title', $audit->index);
    }

    #[Test]
    public function it_audits_an_update_as_a_before_and_after_pair(): void
    {
        $post = $this->makePost();

        $post->update(['title' => 'Updated title']);

        $audit = $post->audits()->where('event_type', EventType::MODEL_UPDATED->value)->sole();

        $this->assertSame(['Original title', 'Updated title'], $audit->changes['title']);
        $this->assertSame(['title'], $audit->index);
    }

    #[Test]
    public function it_records_only_the_attributes_that_actually_changed(): void
    {
        $post = $this->makePost();

        $post->update(['title' => 'Updated title']);

        $audit = $post->audits()->where('event_type', EventType::MODEL_UPDATED->value)->sole();

        $this->assertArrayNotHasKey('body', $audit->changes);
    }

    #[Test]
    public function it_audits_deletion_with_the_original_values(): void
    {
        $post = $this->makePost();

        $post->delete();

        $audit = $post->audits()->where('event_type', EventType::MODEL_DELETED->value)->sole();

        $this->assertSame('Original title', $audit->changes['title']);
    }

    #[Test]
    public function it_excludes_configured_attributes_from_update_audits(): void
    {
        config()->set('auditable.excluded_attributes', ['body']);

        $post = $this->makePost();
        $post->update(['title' => 'New', 'body' => 'Changed body']);

        $audit = $post->audits()->where('event_type', EventType::MODEL_UPDATED->value)->sole();

        $this->assertArrayHasKey('title', $audit->changes);
        $this->assertArrayNotHasKey('body', $audit->changes);
    }

    #[Test]
    public function it_ignores_touch_only_events_by_default(): void
    {
        $post = $this->makePost();
        $created = $post->audits()->count();

        $this->travel(1)->second();
        $post->touch();

        $this->assertTrue($post->wasChanged('updated_at'), 'the touch must be a real change');
        $this->assertSame($created, $post->audits()->count());
    }

    #[Test]
    public function it_audits_touch_events_when_opted_in_via_config(): void
    {
        // Both switches are needed: audit_touch enables it, and updated_at must
        // not be stripped from the change set before it is inspected.
        config()->set('auditable.audit_touch', true);
        config()->set('auditable.excluded_attributes', []);

        $post = $this->makePost();
        $before = $post->audits()->count();

        // Timestamps are second-precision, so the touch must land in a later
        // second to register as a change at all.
        $this->travel(1)->second();
        $post->touch();

        $this->assertSame($before + 1, $post->audits()->count());
    }

    #[Test]
    public function audit_touch_has_no_effect_while_updated_at_is_an_excluded_attribute(): void
    {
        // Pins a config trap: `excluded_attributes` ships containing
        // `updated_at`, which strips the only change a touch produces, so
        // `audit_touch => true` alone never records anything.
        config()->set('auditable.audit_touch', true);
        config()->set('auditable.excluded_attributes', ['updated_at']);

        $post = $this->makePost();
        $before = $post->audits()->count();

        $this->travel(1)->second();
        $post->touch();

        $this->assertTrue($post->wasChanged('updated_at'), 'the touch must be a real change');
        $this->assertSame($before, $post->audits()->count());
    }

    #[Test]
    public function a_model_restricting_auditable_attributes_never_audits_touches(): void
    {
        // Article declares $auditable = ['title', 'body'], so updated_at is
        // filtered out before the touch check runs, despite $auditTouch = true.
        config()->set('auditable.excluded_attributes', []);

        $article = Article::create(['title' => 'T', 'body' => 'B']);
        $before = $article->audits()->count();

        $this->travel(1)->second();
        $article->touch();

        $this->assertTrue($article->wasChanged('updated_at'), 'the touch must be a real change');
        $this->assertSame($before, $article->audits()->count());
    }

    #[Test]
    public function it_limits_auditing_to_the_declared_auditable_attributes(): void
    {
        $article = Article::create([
            'title' => 'T',
            'body' => 'B',
            'internal_note' => 'secret',
        ]);

        $audit = $article->audits()->sole();

        $this->assertArrayHasKey('title', $audit->changes);
        $this->assertArrayNotHasKey('internal_note', $audit->changes);
    }

    #[Test]
    public function it_honours_should_audit_change_vetoes(): void
    {
        $article = Article::create(['title' => 'T', 'body' => 'B']);

        $article->update(['title' => 'skip-me', 'body' => 'kept']);

        $audit = $article->audits()->where('event_type', EventType::MODEL_UPDATED->value)->sole();

        $this->assertArrayNotHasKey('title', $audit->changes);
        $this->assertArrayHasKey('body', $audit->changes);
    }

    #[Test]
    public function it_attributes_the_audit_to_the_authenticated_user(): void
    {
        $user = User::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        Auth::setUser($user);

        $post = $this->makePost();

        $this->assertSame($user->getKey(), $post->audits()->sole()->user_id);
    }

    #[Test]
    public function it_leaves_the_user_null_when_unauthenticated(): void
    {
        $post = $this->makePost();

        $this->assertNull($post->audits()->sole()->user_id);
    }

    #[Test]
    public function it_records_a_custom_audit_for_an_existing_property(): void
    {
        $post = $this->makePost();

        $audit = $post->audit('title', 'before', 'after', 'my-label');

        $this->assertInstanceOf(Audit::class, $audit);
        $this->assertSame(EventType::CUSTOM_EVENT, $audit->event_type);
        $this->assertSame('my-label', $audit->label);
        $this->assertSame(['before', 'after'], $audit->changes['title']);
        $this->assertSame(EventType::CUSTOM_EVENT, $audit->fresh()->event_type);
    }

    #[Test]
    public function event_type_is_the_same_enum_before_and_after_reloading(): void
    {
        // Audit casts event_type to the enum, so a written instance and the same
        // row read back agree on both type and value.
        $audit = $this->makePost()->audit('title', 'a', 'b');

        $this->assertInstanceOf(EventType::class, $audit->event_type);
        $this->assertInstanceOf(EventType::class, $audit->fresh()->event_type);
        $this->assertSame($audit->event_type, $audit->fresh()->event_type);
    }

    #[Test]
    public function event_type_still_serialises_to_its_backing_string(): void
    {
        // The cast must not change the API payload the Vue component consumes.
        $audit = $this->makePost()->audits()->sole();

        $this->assertSame('model_created', $audit->toArray()['event_type']);
        $this->assertSame('model_created', json_decode($audit->toJson(), true)['event_type']);
    }

    #[Test]
    public function event_type_is_persisted_as_its_backing_string(): void
    {
        $this->makePost();

        $this->assertSame(
            'model_created',
            \Illuminate\Support\Facades\DB::table('audits')->value('event_type')
        );
    }

    #[Test]
    public function it_rejects_a_custom_audit_for_an_unknown_property(): void
    {
        $post = $this->makePost();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot audit invalid property nope');

        $post->audit('nope', 'a', 'b');
    }

    #[Test]
    public function it_allows_an_unknown_property_when_existence_is_not_required(): void
    {
        $post = $this->makePost();

        $audit = $post->audit('virtual', 'a', 'b', 'custom-audit', false);

        $this->assertSame(['a', 'b'], $audit->changes['virtual']);
    }

    #[Test]
    public function it_audits_an_added_relation(): void
    {
        $post = $this->makePost();
        $tag = $this->makeTag();

        $audit = $post->auditRelation(EventType::RELATION_CREATED, $tag, 'tag-added');

        $this->assertSame(EventType::RELATION_CREATED, $audit->event_type);
        $this->assertSame($tag::class, $audit->related_type);
        $this->assertSame($tag->getKey(), $audit->related_id);
        $this->assertSame('tag-added', $audit->label);
        $this->assertSame('laravel', $audit->changes['name']);
    }

    #[Test]
    public function it_rejects_a_non_relation_event_type_for_relation_audits(): void
    {
        $post = $this->makePost();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Relation Event for Audit');

        $post->auditRelation(EventType::MODEL_CREATED, $this->makeTag());
    }

    #[Test]
    public function it_defaults_auditable_attributes_to_every_loaded_attribute(): void
    {
        $post = $this->makePost();

        $this->assertContains('title', $post->auditableAttributes());
        $this->assertContains('body', $post->auditableAttributes());

        // Only loaded attributes are considered, so a column left to its
        // database default is absent until the model is refreshed.
        $this->assertNotContains('published', $post->auditableAttributes());
        $this->assertContains('published', $post->fresh()->auditableAttributes());
    }

    #[Test]
    public function post_load_audits_is_a_no_op_by_default(): void
    {
        // The trait's default implementation must accept a paginator and do nothing.
        $post = $this->makePost();

        $this->assertNull(Article::postLoadAudits($post->audits()->paginate()));
    }
}
