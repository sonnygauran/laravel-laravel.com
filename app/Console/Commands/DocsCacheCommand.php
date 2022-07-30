<?php

namespace App\Console\Commands;

use App\Documentation;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DocsCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:cache
        {version? : Laravel version}
        {--versions : Get all available versions}
    }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache documentation markdown';

    private $docs;

    const EXIT_DOCS_MISSING = 1;
    const EXIT_VERSION_INVALID = 1;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Documentation $docs)
    {
        parent::__construct();
        $this->docs = $docs;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $is_get_all_versions = $this->option('versions');
        $specified_version = $this->argument('version');

        $disk = Storage::disk('docs');
        $versions = $disk->directories();
        
        if (empty($versions)) {
            $this->error('Run '.base_path('build/docs.sh').' first to download documentation markup');
            return self::EXIT_DOCS_MISSING;
        }

        if ($is_get_all_versions) {
            $this->info('Available versions:');
            foreach ($versions as $version) {
                $this->line($version);
            }

            return 0;
        }

        if ($specified_version) {
            if (!in_array($specified_version, $versions)) {
                $this->error("Invalid version specified - '{$specified_version}'");
                $this->info("Available versions:");

                foreach ($versions as $version) {
                    $this->line($version);
                }

                return self::EXIT_VERSION_INVALID;
            }

            $versions = [$specified_version];
        }

        foreach ($versions as $version) {
            $this->output->write("{$version}... ", false);
            $this->docs->getIndex($version);

            $pages = collect($disk->allFiles("{$version}"))->filter(function($filename) {
                return Str::endsWith($filename, '.md');
            })
            ->map(function($filename) {
                $part = Str::after($filename, DIRECTORY_SEPARATOR);
                return Str::before($part, '.');
            });

            $this->info("{$pages->count()} pages");

            $count = 1;
            $pages->each(function($page, $key) use ($version, &$count) {
                $now = now();
                $this->output->write(Str::padLeft(($count++), 2).' - '. $page.' ', false);
                
                $this->docs->get($version, $page);
                $duration = now()->diffInMilliseconds($now);
                $this->info($duration.'ms');
            });

        }

        return 0;
    }

}
