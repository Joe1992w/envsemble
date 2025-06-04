<?php

declare(strict_types=1);

namespace JoeWare\Envsemble\Commands;

use Illuminate\Console\Command;
use JoeWare\Envsemble\EnvMerger;
use JoeWare\Envsemble\EnvMergeResult;

class BuildEnvCommand extends Command
{
    protected $signature = 'env:build 
                            {--base= : Path to the base .env file}
                            {--patches= : Directory containing patch files}
                            {--out= : Output file path}
                            {--dry-run : Preview changes without writing output}
                            {--no-comments : Exclude source comments from output}
                            {--squash : Squash all files into a new base file and remove patches}';

    protected $description = 'Merge a base .env file with patch files to create a final .env output';

    public function handle(): int
    {
        $baseFile = $this->option('base');
        $patchesDir = $this->option('patches');
        $outputFile = $this->option('out');
        $dryRun = $this->option('dry-run');
        $includeComments = ! $this->option('no-comments');
        $squash = $this->option('squash');

        // Validate required options
        if (! $baseFile || ! $patchesDir || ! $outputFile) {
            $this->error('Missing required options. Please provide --base, --patches, and --out.');

            return Command::FAILURE;
        }

        // Resolve paths
        $baseFile = $this->resolvePath($baseFile);
        $patchesDir = $this->resolvePath($patchesDir);
        $outputFile = $this->resolvePath($outputFile);

        $this->info('🚀 Starting environment file merge...');
        $this->newLine();

        try {
            $merger = new EnvMerger;

            if ($squash) {
                $this->warn('⚠️  SQUASH MODE: This will remove all patch files after merging!');
                if (! $this->confirm('Are you sure you want to continue?')) {
                    $this->info('Operation cancelled.');

                    return Command::SUCCESS;
                }

                $result = $merger->squash($baseFile, $patchesDir, $outputFile);
                $this->info('✅ Files squashed successfully!');
            } else {
                $result = $merger->merge($baseFile, $patchesDir, $includeComments);

                if (! $dryRun) {
                    $output = $merger->generateOutput($result, $includeComments);
                    file_put_contents($outputFile, $output);
                    $this->info("✅ Environment file generated: {$outputFile}");
                } else {
                    $this->info('🔍 DRY RUN MODE - No files will be written');
                }
            }

            $this->displayReport($result, $dryRun);

            if ($dryRun) {
                $this->newLine();
                $this->info('📋 Preview of output:');
                $this->line('─────────────────────────────────────');
                $output = $merger->generateOutput($result, $includeComments);
                $this->line($output);
                $this->line('─────────────────────────────────────');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    private function displayReport(EnvMergeResult $result, bool $dryRun): void
    {
        $this->newLine();
        $this->info('📊 Merge Report:');

        $report = $result->getReport();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Base file keys', $result->getBaseKeysCount()],
                ['Patch files processed', $result->getPatchFilesCount()],
                ['Keys added', $result->getAddedKeysCount()],
                ['Keys modified', $result->getModifiedKeysCount()],
                ['Keys deleted', $result->getDeletedKeysCount()],
                ['Final output keys', $result->getKeysCount()],
            ]
        );

        if ($result->getAddedKeys() !== []) {
            $this->newLine();
            $this->info('➕ Added keys: '.implode(', ', $result->getAddedKeys()));
        }

        if ($result->getModifiedKeys() !== []) {
            $this->newLine();
            $this->info('🔄 Modified keys: '.implode(', ', $result->getModifiedKeys()));
        }

        if ($result->getDeletedKeys() !== []) {
            $this->newLine();
            $this->info('🗑️  Deleted keys: '.implode(', ', $result->getDeletedKeys()));
        }

        $this->newLine();
        $this->info('📁 Files processed:');
        $this->line("   Base: {$report['base_file']}");
        foreach ($report['patch_files'] as $patchFile) {
            $this->line("   Patch: {$patchFile}");
        }

        if (! $dryRun) {
            $efficiency = $result->getBaseKeysCount() > 0
                ? round(($result->getKeysCount() / $result->getBaseKeysCount()) * 100, 1)
                : 0;

            $this->newLine();
            $this->info("📈 Efficiency: {$efficiency}% keys retained/added from base");
        }
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return getcwd().'/'.$path;
    }
}
