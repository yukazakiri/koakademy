<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SanityService;
use Illuminate\Console\Command;

final class SyncSanityContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sanity:sync {--type=post : The document type to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync content from Sanity CMS to the local database';

    /**
     * Execute the console command.
     */
    public function handle(SanityService $sanityService): int
    {
        $type = $this->option('type');

        $this->info('Starting Sanity sync...');

        if (! $sanityService->isConfigured()) {
            $this->error('Sanity is not configured!');
            $this->error('Please set SANITY_PROJECT_ID and SANITY_API_TOKEN in your .env file.');

            return self::FAILURE;
        }

        $this->info("Fetching {$type} documents from Sanity...");

        $result = $sanityService->syncToDatabase($type);

        if (! $result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info('✓ '.$result['message']);
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Synced', $result['synced']],
                ['New Posts', $result['created']],
                ['Updated Posts', $result['updated']],
            ]
        );

        return self::SUCCESS;
    }
}
