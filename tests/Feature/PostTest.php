<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    // ==================== INDEX TESTS ====================

    #[Test]
    public function it_can_list_published_posts()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create published posts
        $publishedPost1 = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $publishedPost2 = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => false,
            'published_at' => now()->subHour(),
        ]);

        // Create draft post (should not appear)
        Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => true,
        ]);

        // Create future scheduled post (should not appear)
        Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => false,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson(route('posts.index'));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $publishedPost1->id])
            ->assertJsonFragment(['id' => $publishedPost2->id])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'content',
                        'user' => [
                            'id',
                            'name',
                            'email',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_returns_empty_list_when_no_published_posts_exist()
    {
        $response = $this->getJson(route('posts.index'));

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_paginates_posts_list()
    {
        /** @var User $user */
        $user = User::factory()->create();

        Post::factory()->count(25)->create([
            'user_id' => $user->id,
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(route('posts.index'));

        $response->assertStatus(200)
            ->assertJsonCount(20, 'data')
            ->assertJsonStructure([
                'current_page',
                'data',
                'first_page_url',
                'last_page',
                'per_page',
                'total',
            ]);
    }

    // ==================== SHOW TESTS ====================

    #[Test]
    public function it_can_show_a_published_post()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson(route('posts.show', $post));

        $response->assertStatus(200)
            ->assertJson([
                'id' => $post->id,
                'title' => $post->title,
                'content' => $post->content,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
            ]);
    }

    #[Test]
    public function author_can_view_their_own_draft_post()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => true,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('posts.show', $post));

        $response->assertStatus(200)
            ->assertJson([
                'id' => $post->id,
                'is_draft' => true,
            ]);
    }

    #[Test]
    public function it_returns_404_for_draft_post_for_non_author()
    {
        /** @var User $author */
        $author = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'is_draft' => true,
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson(route('posts.show', $post));

        $response->assertStatus(404);
    }

    #[Test]
    public function it_returns_404_for_draft_post_for_guest()
    {
        /** @var User $author */
        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'is_draft' => true,
        ]);

        $response = $this->getJson(route('posts.show', $post));

        $response->assertStatus(404);
    }

    #[Test]
    public function it_returns_404_for_future_scheduled_post_for_non_author()
    {
        /** @var User $author */
        $author = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'is_draft' => false,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson(route('posts.show', $post));

        $response->assertStatus(404);
    }

    #[Test]
    public function author_can_view_their_own_scheduled_post()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => false,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('posts.show', $post));

        $response->assertStatus(200)
            ->assertJson([
                'id' => $post->id,
                'is_draft' => false,
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_post()
    {
        $response = $this->getJson(route('posts.show', 999));

        $response->assertStatus(404);
    }

    // ==================== CREATE TESTS ====================

    #[Test]
    public function authenticated_user_can_access_create_page()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('posts.create'));

        $response->assertStatus(200);
    }

    #[Test]
    public function guest_cannot_access_create_page()
    {
        $response = $this->get(route('posts.create'));

        $response->assertStatus(302)
            ->assertRedirect(route('login'));
    }

    // ==================== STORE TESTS ====================

    #[Test]
    public function authenticated_user_can_create_a_post()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $postData = [
            'title' => 'Test Post Title',
            'content' => 'Test post content here.',
            'is_draft' => false,
            'published_at' => now()->toDateTimeString(),
        ];

        $response = $this->actingAs($user)
            ->postJson(route('posts.store'), $postData);

        $response->assertStatus(201)
            ->assertJson([
                'title' => $postData['title'],
                'content' => $postData['content'],
                'is_draft' => false,
                'user' => [
                    'id' => $user->id,
                ],
            ]);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'title' => $postData['title'],
            'content' => $postData['content'],
        ]);
    }

    #[Test]
    public function authenticated_user_can_create_a_draft_post()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $postData = [
            'title' => 'Draft Post',
            'content' => 'This is a draft.',
            'is_draft' => true,
        ];

        $response = $this->actingAs($user)
            ->postJson(route('posts.store'), $postData);

        $response->assertStatus(201)
            ->assertJson([
                'is_draft' => true,
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => $postData['title'],
            'is_draft' => true,
        ]);
    }

    #[Test]
    public function guest_cannot_create_a_post()
    {
        $postData = [
            'title' => 'Test Post',
            'content' => 'Test content',
        ];

        $response = $this->postJson(route('posts.store'), $postData);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_requires_title_when_creating_post()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $postData = [
            'content' => 'Test content',
        ];

        $response = $this->actingAs($user)
            ->postJson(route('posts.store'), $postData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    #[Test]
    public function it_requires_content_when_creating_post()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $postData = [
            'title' => 'Test Title',
        ];

        $response = $this->actingAs($user)
            ->postJson(route('posts.store'), $postData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    #[Test]
    public function it_validates_title_max_length()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $postData = [
            'title' => str_repeat('a', 256),
            'content' => 'Test content',
        ];

        $response = $this->actingAs($user)
            ->postJson(route('posts.store'), $postData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    #[Test]
    public function it_validates_is_draft_is_boolean()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $postData = [
            'title' => 'Test',
            'content' => 'Test content',
            'is_draft' => 'invalid',
        ];

        $response = $this->actingAs($user)
            ->postJson(route('posts.store'), $postData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_draft']);
    }

    #[Test]
    public function it_validates_published_at_is_valid_date()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $postData = [
            'title' => 'Test',
            'content' => 'Test content',
            'published_at' => 'invalid-date',
        ];

        $response = $this->actingAs($user)
            ->postJson(route('posts.store'), $postData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['published_at']);
    }

    // ==================== EDIT TESTS ====================

    #[Test]
    public function post_author_can_access_edit_page()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('posts.edit', $post));

        $response->assertStatus(200);
    }

    #[Test]
    public function guest_cannot_access_edit_page()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->get(route('posts.edit', $post));

        $response->assertStatus(302)
            ->assertRedirect(route('login'));
    }

    // ==================== UPDATE TESTS ====================

    #[Test]
    public function post_author_can_update_their_post()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Original Title',
            'content' => 'Original content',
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ];

        $response = $this->actingAs($user)
            ->putJson(route('posts.update', $post), $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $post->id,
                'title' => 'Updated Title',
                'content' => 'Updated content',
            ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ]);
    }

    #[Test]
    public function post_author_can_partially_update_their_post()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Original Title',
            'content' => 'Original content',
        ]);

        $updateData = [
            'title' => 'Updated Title Only',
        ];

        $response = $this->actingAs($user)
            ->putJson(route('posts.update', $post), $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'Updated Title Only',
                'content' => 'Original content',
            ]);
    }

    #[Test]
    public function non_author_cannot_update_post()
    {
        /** @var User $author */
        $author = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $author->id]);

        $updateData = [
            'title' => 'Hacked Title',
        ];

        $response = $this->actingAs($otherUser)
            ->putJson(route('posts.update', $post), $updateData);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
            'title' => 'Hacked Title',
        ]);
    }

    #[Test]
    public function guest_cannot_update_post()
    {
        /** @var User $author */
        $author = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $author->id]);

        $updateData = [
            'title' => 'Updated Title',
        ];

        $response = $this->putJson(route('posts.update', $post), $updateData);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_validates_title_when_updating()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $updateData = [
            'title' => str_repeat('a', 256),
        ];

        $response = $this->actingAs($user)
            ->putJson(route('posts.update', $post), $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    #[Test]
    public function it_can_update_post_draft_status()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => true,
        ]);

        $updateData = [
            'is_draft' => false,
            'published_at' => now()->toDateTimeString(),
        ];

        $response = $this->actingAs($user)
            ->putJson(route('posts.update', $post), $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'is_draft' => false,
            ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'is_draft' => false,
        ]);
    }

    // ==================== DELETE TESTS ====================

    #[Test]
    public function post_author_can_delete_their_post()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->deleteJson(route('posts.destroy', $post));

        $response->assertStatus(204);

        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
        ]);
    }

    #[Test]
    public function non_author_cannot_delete_post()
    {
        /** @var User $author */
        $author = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $author->id]);

        $response = $this->actingAs($otherUser)
            ->deleteJson(route('posts.destroy', $post));

        $response->assertStatus(403);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
        ]);
    }

    #[Test]
    public function guest_cannot_delete_post()
    {
        /** @var User $author */
        $author = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $author->id]);

        $response = $this->deleteJson(route('posts.destroy', $post));

        $response->assertStatus(401);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
        ]);
    }

    #[Test]
    public function it_returns_404_when_deleting_non_existent_post()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson(route('posts.destroy', 999));

        $response->assertStatus(404);
    }
}
