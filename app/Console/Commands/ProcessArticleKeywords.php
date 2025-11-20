<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Article;
use App\Services\ContentAnalyzer;

class ProcessArticleKeywords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:process-keywords {--force : Force reprocess all articles}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract and cache keywords from article content for ML recommendations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing article keywords...');

        $query = Article::query();

        if (!$this->option('force')) {
            // Only process articles without keywords
            $query->whereNull('keywords');
        }

        $articles = $query->get();
        $total = $articles->count();

        if ($total === 0) {
            $this->info('No articles to process.');
            return 0;
        }

        $this->info("Processing {$total} articles...");
        $bar = $this->output->createProgressBar($total);

        foreach ($articles as $article) {
            try {
                $keywords = ContentAnalyzer::extractKeywords($article->content, 30);
                $article->keywords = json_encode($keywords);
                $article->content_hash = md5($article->content);
                $article->save();
                
                $bar->advance();
            } catch (\Exception $e) {
                $this->error("\nFailed to process article {$article->id}: {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('✅ Keywords processed successfully!');

        return 0;
    }
}
