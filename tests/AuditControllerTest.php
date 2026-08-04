<?php

namespace Aloware\Auditable\Tests;

use Aloware\Auditable\Enums\EventType;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class AuditControllerTest extends TestCase
{
    #[Test]
    public function it_registers_the_audits_route_under_the_configured_prefix(): void
    {
        $post = $this->makePost();

        $this->getJson("/api/audits/post/{$post->getKey()}")->assertOk();
    }

    #[Test]
    public function it_returns_the_audits_for_a_model_newest_first(): void
    {
        $post = $this->makePost();
        $post->update(['title' => 'Changed']);

        $response = $this->getJson("/api/audits/post/{$post->getKey()}")->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame(EventType::MODEL_UPDATED->value, $data[0]['event_type']);
        $this->assertSame(EventType::MODEL_CREATED->value, $data[1]['event_type']);
    }

    #[Test]
    public function it_returns_a_paginated_payload(): void
    {
        config()->set('auditable.per_page', 2);
        $post = $this->makePost();
        $post->update(['title' => 'a']);
        $post->update(['title' => 'b']);

        $response = $this->getJson("/api/audits/post/{$post->getKey()}")->assertOk();

        $response->assertJsonPath('per_page', 2)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('last_page', 2);
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function it_eager_loads_the_user_auditable_and_related_records(): void
    {
        $user = User::create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $this->actingAs($user);
        $post = $this->makePost();

        $response = $this->getJson("/api/audits/post/{$post->getKey()}")->assertOk();

        $audit = $response->json('data.0');
        $this->assertSame('Ada', $audit['user']['first_name']);
        // Only the three selected columns are exposed for the user.
        $this->assertSame(['id', 'first_name', 'last_name'], array_keys($audit['user']));
        $this->assertNotNull($audit['auditable']);
    }

    #[Test]
    public function it_still_resolves_a_soft_deleted_user(): void
    {
        $user = User::create(['first_name' => 'Ada']);
        $this->actingAs($user);
        $post = $this->makePost();
        $user->delete();

        $response = $this->getJson("/api/audits/post/{$post->getKey()}")->assertOk();

        $this->assertSame('Ada', $response->json('data.0.user.first_name'));
    }

    #[Test]
    public function it_filters_by_user(): void
    {
        $ada = User::create(['first_name' => 'Ada']);
        $grace = User::create(['first_name' => 'Grace']);

        $this->actingAs($ada);
        $post = $this->makePost();

        $this->actingAs($grace);
        $post->update(['title' => 'Changed by Grace']);

        $response = $this->getJson("/api/audits/post/{$post->getKey()}?user_id={$grace->getKey()}")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Grace', $response->json('data.0.user.first_name'));
    }

    #[Test]
    public function it_filters_by_event_type(): void
    {
        $post = $this->makePost();
        $post->update(['title' => 'Changed']);

        $response = $this->getJson("/api/audits/post/{$post->getKey()}?type=model_updated")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(EventType::MODEL_UPDATED->value, $response->json('data.0.event_type'));
    }

    #[Test]
    public function it_rejects_an_unknown_event_type_with_a_validation_error(): void
    {
        // Regression: an unrecognised type used to reach scopeByType() as null
        // and blow up its EventType argument type, returning a 500.
        $post = $this->makePost();

        $this->getJson("/api/audits/post/{$post->getKey()}?type=bogus")
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    #[Test]
    public function it_ignores_an_empty_event_type(): void
    {
        $post = $this->makePost();

        $this->getJson("/api/audits/post/{$post->getKey()}?type=")->assertOk();
    }

    #[Test]
    public function it_accepts_every_known_event_type(): void
    {
        $post = $this->makePost();

        foreach (EventType::values() as $type) {
            $this->getJson("/api/audits/post/{$post->getKey()}?type={$type}")->assertOk();
        }
    }

    #[Test]
    public function it_filters_by_label(): void
    {
        $post = $this->makePost();
        $post->audit('title', 'a', 'b', 'my-label');

        $response = $this->getJson("/api/audits/post/{$post->getKey()}?label=my-label")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('my-label', $response->json('data.0.label'));
    }

    #[Test]
    public function it_filters_by_modified_attribute(): void
    {
        $post = $this->makePost();
        $post->update(['title' => 'Changed']);
        $post->update(['body' => 'Changed body']);

        $response = $this->getJson("/api/audits/post/{$post->getKey()}?attribute=body")->assertOk();

        // The creation audit and the body update both index `body`.
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function it_filters_by_date_range(): void
    {
        $post = $this->makePost();
        $this->travel(2)->days();
        $post->update(['title' => 'Later change']);

        $from = now()->subDay()->toDateTimeString();

        $response = $this->getJson("/api/audits/post/{$post->getKey()}?from={$from}")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(EventType::MODEL_UPDATED->value, $response->json('data.0.event_type'));
    }

    #[Test]
    public function it_invokes_the_post_load_audits_hook(): void
    {
        Post::$post_loaded = null;
        $post = $this->makePost();

        $this->getJson("/api/audits/post/{$post->getKey()}")->assertOk();

        $paginator = Post::$post_loaded;
        $this->assertNotNull($paginator, 'postLoadAudits should receive the paginator');
        $this->assertSame(1, $paginator->total());
    }

    #[Test]
    public function it_returns_404_for_a_missing_model(): void
    {
        $this->getJson('/api/audits/post/9999')->assertNotFound();
    }

    #[Test]
    public function it_fails_for_an_unregistered_model_alias(): void
    {
        $this->withoutExceptionHandling();

        try {
            $this->getJson('/api/audits/nope/1');
            $this->fail('An unregistered alias should raise an exception.');
        } catch (Exception $e) {
            $this->assertSame('Auditable model alias nope is not defined', $e->getMessage());
        }
    }

    #[Test]
    public function it_exposes_the_audits_route_by_name_free_get_only(): void
    {
        $post = $this->makePost();

        // Only GET is registered for the endpoint.
        $this->postJson("/api/audits/post/{$post->getKey()}")->assertMethodNotAllowed();
    }
}
