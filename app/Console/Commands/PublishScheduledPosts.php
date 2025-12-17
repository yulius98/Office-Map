<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish schedule posts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $publishedCount = Post::where('is_draft', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->update(['is_draft' => false]);

        $this->info("Published {$publishedCount} scheduled post(s).");

        return self::SUCCESS;
    }
}
