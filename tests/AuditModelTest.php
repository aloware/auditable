<?php

namespace Aloware\Auditable\Tests;

use Aloware\Auditable\Enums\EventType;
use Aloware\Auditable\Models\Audit;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class AuditModelTest extends TestCase
{
    #[Test]
    public function it_reads_its_table_name_from_config(): void
    {
        $this->assertSame('audits', (new Audit)->getTable());

        config()->set('auditable.audits_table', 'custom_audits');

        $this->assertSame('custom_audits', (new Audit)->getTable());
    }

    #[Test]
    public function it_casts_changes_and_index_to_json(): void
    {
        $post = $this->makePost();

        $audit = $post->audits()->sole();

        $this->assertIsArray($audit->changes);
        $this->assertIsArray($audit->index);
    }

    #[Test]
    public function it_resolves_the_auditable_morph_relation(): void
    {
        $post = $this->makePost();

        $audit = $post->audits()->sole();

        $this->assertTrue($audit->auditable->is($post));
    }

    #[Test]
    public function it_resolves_the_user_relation_from_the_configured_model(): void
    {
        $user = User::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $this->actingAs($user);

        $audit = $this->makePost()->audits()->sole();

        $this->assertInstanceOf(User::class, $audit->user);
        $this->assertSame('Ada', $audit->user->first_name);
    }

    #[Test]
    public function it_resolves_the_related_morph_relation(): void
    {
        $post = $this->makePost();
        $tag = $this->makeTag();
        $post->auditRelation(EventType::RELATION_CREATED, $tag);

        $audit = Audit::whereNotNull('related_id')->sole();

        $this->assertTrue($audit->related->is($tag));
    }

    #[Test]
    public function by_model_scope_filters_on_the_auditable_type(): void
    {
        $this->makePost();

        $this->assertSame(1, Audit::query()->byModel(Post::class)->count());
        $this->assertSame(0, Audit::query()->byModel('App\Models\Nope')->count());
    }

    #[Test]
    public function by_type_scope_filters_on_the_event_type(): void
    {
        $post = $this->makePost();
        $post->update(['title' => 'Changed']);

        $this->assertSame(1, Audit::query()->byType(EventType::MODEL_CREATED)->count());
        $this->assertSame(1, Audit::query()->byType(EventType::MODEL_UPDATED)->count());
        $this->assertSame(0, Audit::query()->byType(EventType::MODEL_DELETED)->count());
    }

    #[Test]
    public function by_label_scope_filters_on_the_label(): void
    {
        $post = $this->makePost();
        $post->audit('title', 'a', 'b', 'my-label');

        $this->assertSame(1, Audit::query()->byLabel('my-label')->count());
        $this->assertSame(1, Audit::query()->byLabel('self-audit')->count());
        $this->assertSame(0, Audit::query()->byLabel('absent')->count());
    }

    #[Test]
    public function with_modified_scope_matches_audits_touching_an_attribute(): void
    {
        $post = $this->makePost();
        $post->update(['title' => 'Changed']);
        $post->update(['body' => 'Changed body']);

        $this->assertSame(2, Audit::query()->withModified('title')->count());
        $this->assertSame(2, Audit::query()->withModified('body')->count());
        $this->assertSame(0, Audit::query()->withModified('published')->count());
    }

    #[Test]
    public function with_modified_scope_ignores_relation_and_custom_events(): void
    {
        $post = $this->makePost();
        $post->auditRelation(EventType::RELATION_CREATED, $this->makeTag());
        $post->audit('title', 'a', 'b');

        // Only the MODEL_CREATED audit qualifies; the custom and relation
        // events are filtered out by the event_type whitelist.
        $this->assertSame(1, Audit::query()->withModified('title')->count());
    }

    #[Test]
    public function modified_by_user_scope_accepts_a_model_or_a_key(): void
    {
        $user = User::create(['first_name' => 'Ada']);
        $this->actingAs($user);
        $this->makePost();

        $this->assertSame(1, Audit::query()->modifiedByUser($user)->count());
        $this->assertSame(1, Audit::query()->modifiedByUser($user->getKey())->count());
        $this->assertSame(0, Audit::query()->modifiedByUser($user->getKey() + 99)->count());
    }

    #[Test]
    public function with_modified_relation_scope_filters_relation_events(): void
    {
        $post = $this->makePost();
        $post->auditRelation(EventType::RELATION_CREATED, $this->makeTag());

        $this->assertSame(1, Audit::query()->withModifiedRelation()->count());
        $this->assertSame(1, Audit::query()->withModifiedRelation(null, 'name')->count());
        $this->assertSame(0, Audit::query()->withModifiedRelation(null, 'absent')->count());
    }

    #[Test]
    public function with_modified_relation_scope_hardcodes_the_app_models_namespace(): void
    {
        // Pins a real limitation: the relation name is prefixed with
        // 'App\Models\', so related models living anywhere else can never match.
        $post = $this->makePost();
        $post->auditRelation(EventType::RELATION_CREATED, $this->makeTag());

        // The fixture Tag is Workbench\App\Models\Tag, so filtering by name fails.
        $this->assertSame(0, Audit::query()->withModifiedRelation('Tag')->count());
    }
}
