<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublishScheduledPostsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_publishes_scheduled_posts_when_publish_date_has_arrived()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create a scheduled post with published_at in the past
        $scheduledPost = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => true,
            'published_at' => now()->subHour(),
        ]);

        // Run the command
        $this->artisan('posts:publish')
            ->expectsOutput('Published 1 scheduled post(s).')
            ->assertExitCode(0);

        // Verify the post is now published
        $scheduledPost->refresh();
        $this->assertFalse($scheduledPost->is_draft);
    }

    #[Test]
    public function it_does_not_publish_posts_scheduled_for_future()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create a scheduled post with published_at in the future
        $futurePost = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => true,
            'published_at' => now()->addDay(),
        ]);

        // Run the command
        $this->artisan('posts:publish')
            ->expectsOutput('Published 0 scheduled post(s).')
            ->assertExitCode(0);

        // Verify the post is still a draft
        $futurePost->refresh();
        $this->assertTrue($futurePost->is_draft);
    }

    #[Test]
    public function it_does_not_publish_posts_without_published_at_date()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create a draft post without published_at
        $draftPost = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => true,
            'published_at' => null,
        ]);

        // Run the command
        $this->artisan('posts:publish')
            ->expectsOutput('Published 0 scheduled post(s).')
            ->assertExitCode(0);

        // Verify the post is still a draft
        $draftPost->refresh();
        $this->assertTrue($draftPost->is_draft);
    }

    #[Test]
    public function it_does_not_affect_already_published_posts()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create already published post
        $publishedPost = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        // Run the command
        $this->artisan('posts:publish')
            ->expectsOutput('Published 0 scheduled post(s).')
            ->assertExitCode(0);

        // Verify the post is still published
        $publishedPost->refresh();
        $this->assertFalse($publishedPost->is_draft);
    }

    #[Test]
    public function it_publishes_multiple_scheduled_posts_at_once()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create 3 scheduled posts with published_at in the past
        Post::factory()->count(3)->create([
            'user_id' => $user->id,
            'is_draft' => true,
            'published_at' => now()->subHours(2),
        ]);

        // Create 1 future scheduled post (should not be published)
        Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => true,
            'published_at' => now()->addDay(),
        ]);

        // Run the command
        $this->artisan('posts:publish')
            ->expectsOutput('Published 3 scheduled post(s).')
            ->assertExitCode(0);

        // Verify 3 posts are published
        $publishedCount = Post::where('is_draft', false)->count();
        $this->assertEquals(3, $publishedCount);

        // Verify 1 post is still draft
        $draftCount = Post::where('is_draft', true)->count();
        $this->assertEquals(1, $draftCount);
    }

    #[Test]
    public function published_scheduled_posts_appear_in_index()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create a scheduled post
        $scheduledPost = Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Scheduled Post Title',
            'is_draft' => true,
            'published_at' => now()->subHour(),
        ]);

        // Before publishing, post should not appear in index
        $response = $this->getJson(route('posts.index'));
        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Run the command
        $this->artisan('posts:publish');

        // After publishing, post should appear in index
        $response = $this->getJson(route('posts.index'));
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Scheduled Post Title']);
    }

    #[Test]
    public function published_scheduled_posts_are_accessible_via_show_route()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create a scheduled post
        $scheduledPost = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => true,
            'published_at' => now()->subHour(),
        ]);

        // Before publishing, show should return 404
        $response = $this->getJson(route('posts.show', $scheduledPost));
        $response->assertStatus(404);

        // Run the command
        $this->artisan('posts:publish');

        // After publishing, show should return 200
        $scheduledPost->refresh();
        $response = $this->getJson(route('posts.show', $scheduledPost));
        $response->assertStatus(200)
            ->assertJson(['id' => $scheduledPost->id]);
    }
}
