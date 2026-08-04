<?php

namespace Aloware\Auditable\Tests;

use Aloware\Auditable\ServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // The shipped config points at the host application's App\Models\*,
        // so redirect it at the workbench fixtures.
        $app['config']->set('auditable.user_model', \Workbench\App\Models\User::class);
        $app['config']->set('auditable.user_table', 'users');
        $app['config']->set('auditable.audits_table', 'audits');
        $app['config']->set('auditable.audit_touch', false);
        $app['config']->set('auditable.per_page', 10);
        $app['config']->set('auditable.excluded_attributes', ['updated_at']);
        $app['config']->set('auditable.route_prefix', '/api');
        $app['config']->set('auditable.route_middleware', []);
        $app['config']->set('auditable.models', [
            'post' => Post::class,
            'article' => \Workbench\App\Models\Article::class,
        ]);
    }

    /**
     * The package migration is loaded by the ServiceProvider; these are the
     * tables the fixtures need.
     */
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('published')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('internal_note')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id');
            $table->foreignId('tag_id');
        });
    }

    protected function makePost(array $attributes = []): Post
    {
        return Post::create(array_merge([
            'title' => 'Original title',
            'body' => 'Original body',
        ], $attributes));
    }

    protected function makeTag(string $name = 'laravel'): Tag
    {
        return Tag::create(['name' => $name]);
    }
}
