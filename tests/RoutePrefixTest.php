<?php

namespace Aloware\Auditable\Tests;

use PHPUnit\Framework\Attributes\Test;

/**
 * Companion to RouteMiddlewareTest: the route prefix is also read at boot.
 */
class RoutePrefixTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auditable.route_prefix', '/internal/v2');
    }

    #[Test]
    public function it_honours_a_custom_route_prefix(): void
    {
        $post = $this->makePost();

        $this->getJson("/internal/v2/audits/post/{$post->getKey()}")->assertOk();
    }

    #[Test]
    public function the_default_prefix_is_no_longer_served(): void
    {
        $post = $this->makePost();

        $this->getJson("/api/audits/post/{$post->getKey()}")->assertNotFound();
    }
}
