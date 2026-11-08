<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use CyrildeWit\EloquentViewable\Facades\Views;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every page of the demo renders on a small simulated history.
 */
class DemoTest extends TestCase
{
    use RefreshDatabase;

    private const string BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    protected bool $seed = true;

    protected function setUp(): void
    {
        putenv('DEMO_DAYS=45');
        putenv('DEMO_VIEWS=4000');

        parent::setUp();

        config(['eloquent-viewable.presence.driver' => 'array']);

        // The Console writes its background runs under storage; keep the
        // tests' runs out of the demo's own.
        $this->app->useStoragePath(sys_get_temp_dir().'/inkwell-test-storage');

        // The Console runs commands in a separate process, against the
        // real database. Never in a test.
        Process::fake();

        $this->withHeader('User-Agent', self::BROWSER);
    }

    public static function pages(): array
    {
        return [
            'home' => ['/'],
            'articles' => ['/posts'],
            'trending' => ['/posts?sort=trending'],
            'hot' => ['/posts?sort=hot'],
            'google' => ['/posts?sort=google'],
            'unread' => ['/posts?sort=unread'],
            'podcast' => ['/episodes'],
            'stats' => ['/stats'],
            'stats per hour' => ['/stats/24h'],
            'stats per month' => ['/stats/2y?type=episode&tz=Australia/Sydney'],
            'live' => ['/stats/live.json'],
            'lab' => ['/lab'],
            'console' => ['/console'],
            'account' => ['/account'],
            'tour' => ['/tour'],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_page_renders(string $url): void
    {
        $this->get($url)->assertOk();
    }

    public function test_an_article_records_a_view_and_a_reload_is_skipped(): void
    {
        $post = Post::query()->published()->first();
        $before = views($post)->count();

        $this->get(route('posts.show', $post))->assertOk()->assertSee('View recorded');
        $this->get(route('posts.show', $post))->assertOk()->assertSee('Skipped by EnforceCooldown');

        $this->assertSame($before + 1, views($post)->count());
    }

    public function test_an_author_reading_their_own_post_is_not_counted(): void
    {
        $post = Post::query()->published()->with('author')->first();

        $this->actingAs($post->author)
            ->get(route('posts.show', $post))
            ->assertSee('Skipped by IgnoreAuthors');
    }

    public function test_the_per_post_stats_render(): void
    {
        $this->get(route('posts.stats', [Post::query()->published()->first(), '7d']))->assertOk();
    }

    public function test_only_reads_the_rollups_cannot_answer_are_refused_once_views_are_pruned(): void
    {
        Artisan::call('views:rollup');
        Artisan::call('views:prune', ['--older-than' => '20d']);

        // Every dimension is folded, so a count by one is answered from the
        // rollups; only counts that combine two dimensions are refused.
        $this->get(route('stats', '3m'))->assertOk()->assertDontSee('Not answerable for this period')->assertSee('Two dimensions at once');
        $this->get(route('posts.stats', [Post::query()->published()->first(), '3m']))->assertOk()->assertSee('Not answerable for this period');
    }

    public function test_the_console_renders_a_snapshot_after_maintenance(): void
    {
        Artisan::call('views:maintain');
        Artisan::call('demo:examine');

        $this->get(route('console'))->assertOk()->assertSee('Snapshot of the tables from')->assertSee('views:source');
    }

    public function test_the_lab_refuses_a_crawler(): void
    {
        $post = Post::query()->published()->first();

        $this->post(route('lab.attempt'), [
            'target' => "post:{$post->slug}",
            'agent' => 'googlebot',
            'ip' => '203.0.113.10',
            'arrival' => 'google',
            'cooldown' => 0,
        ])->assertRedirect();

        $this->get(route('lab'))->assertSee('IgnoreCrawlers');
    }

    public function test_a_reader_sees_their_history(): void
    {
        $chloe = User::query()->where('email', 'chloe@inkwell.test')->first();

        $this->actingAs($chloe)->get(route('account'))->assertOk()->assertSee('Recommended for you');
    }

    public function test_the_simulator_adds_live_readers(): void
    {
        $this->post(route('console.simulate'), ['action' => 'live'])->assertRedirect();

        $this->assertGreaterThan(0, Views::live()->count());
    }
}
