<?php

namespace Aloware\Auditable\Tests;

use PHPUnit\Framework\Attributes\Test;

/**
 * The package registers its routes while the ServiceProvider boots, so the
 * middleware stack must be configured before the application comes up. This
 * lives in its own case so the override lands after the base environment.
 */
class RouteMiddlewareTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auditable.route_middleware', ['auth']);
    }

    #[Test]
    public function it_applies_the_configured_route_middleware(): void
    {
        $post = $this->makePost();

        $this->getJson("/api/audits/post/{$post->getKey()}")->assertUnauthorized();
    }

    #[Test]
    public function it_serves_the_endpoint_once_authenticated(): void
    {
        $this->actingAs(\Workbench\App\Models\User::create(['first_name' => 'Ada']));

        $post = $this->makePost();

        $this->getJson("/api/audits/post/{$post->getKey()}")->assertOk();
    }
}
