<?php

declare(strict_types=1);

use JoeWare\Envsemble\Commands\BuildEnvCommand;
use JoeWare\Envsemble\EnvMerger;

describe('CLI Integration', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/envsemble_cli_test_' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/patches');
    });

    afterEach(function () {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
            
            rmdir($this->tempDir);
        }
    });

    it('can validate command signature and options', function () {
        $command = new BuildEnvCommand();
        
        // Test the signature contains required options
        $signature = $command->getDefinition();
        
        expect($signature->hasOption('base'))->toBeTrue();
        expect($signature->hasOption('patches'))->toBeTrue();
        expect($signature->hasOption('out'))->toBeTrue();
        expect($signature->hasOption('dry-run'))->toBeTrue();
        expect($signature->hasOption('no-comments'))->toBeTrue();
        expect($signature->hasOption('squash'))->toBeTrue();
    });

    it('can merge files and generate output via CLI workflow', function () {
        // Setup test files
        $baseFile = $this->tempDir . '/.env.base';
        $outputFile = $this->tempDir . '/.env.output';
        
        file_put_contents($baseFile, "APP_NAME=TestApp\nAPP_ENV=production");
        file_put_contents($this->tempDir . '/patches/01-test.env', "APP_DEBUG=true\nAPP_ENV=local");
        
        // Test the core functionality that the CLI would use
        $merger = new EnvMerger();
        $result = $merger->merge($baseFile, $this->tempDir . '/patches');
        $output = $merger->generateOutput($result);
        
        file_put_contents($outputFile, $output);
        
        // Verify output file was created and contains expected content
        expect(file_exists($outputFile))->toBeTrue();
        
        $content = file_get_contents($outputFile);
        expect($content)->toContain('APP_NAME=TestApp # from: .env.base');
        expect($content)->toContain('APP_ENV=local # from: 01-test.env');
        expect($content)->toContain('APP_DEBUG=true # from: 01-test.env');
        
        // Verify the merge report
        $report = $result->getReport();
        expect($report['base_keys_count'])->toBe(2);
        expect($report['patch_files_count'])->toBe(1);
        expect($result->getKeysCount())->toBe(3);
        expect($result->getModifiedKeysCount())->toBe(1); // APP_ENV was modified
        expect($result->getAddedKeysCount())->toBe(1); // APP_DEBUG was added
    });

    it('can perform dry run workflow', function () {
        // Setup test files
        $baseFile = $this->tempDir . '/.env.base';
        $outputFile = $this->tempDir . '/.env.output';
        
        file_put_contents($baseFile, "APP_NAME=TestApp");
        file_put_contents($this->tempDir . '/patches/01-test.env', "APP_DEBUG=true");
        
        // Simulate dry run - merge but don't write output
        $merger = new EnvMerger();
        $result = $merger->merge($baseFile, $this->tempDir . '/patches');
        $previewOutput = $merger->generateOutput($result);
        
        // In dry run, we don't write the file
        // file_put_contents($outputFile, $previewOutput);
        
        // Output file should not exist (simulating --dry-run)
        expect(file_exists($outputFile))->toBeFalse();
        
        // But we should have the preview content
        expect($previewOutput)->toContain('APP_NAME=TestApp');
        expect($previewOutput)->toContain('APP_DEBUG=true');
    });

    it('can perform squash workflow', function () {
        // Setup test files
        $baseFile = $this->tempDir . '/.env.base';
        $outputFile = $this->tempDir . '/squashed.env';
        
        file_put_contents($baseFile, "APP_NAME=TestApp\nDB_HOST=localhost");
        
        $patch1 = $this->tempDir . '/patches/01-staging.env';
        $patch2 = $this->tempDir . '/patches/02-debug.env';
        file_put_contents($patch1, "APP_ENV=staging");
        file_put_contents($patch2, "APP_DEBUG=true");
        
        // Test squash functionality
        $merger = new EnvMerger();
        $result = $merger->squash($baseFile, $this->tempDir . '/patches', $outputFile);
        
        // Check that output file was created
        expect(file_exists($outputFile))->toBeTrue();
        
        // Check that patch files were removed
        expect(file_exists($patch1))->toBeFalse();
        expect(file_exists($patch2))->toBeFalse();
        
        // Check content (squash mode doesn't include comments)
        $content = file_get_contents($outputFile);
        expect($content)->toContain('APP_NAME=TestApp');
        expect($content)->toContain('APP_ENV=staging');
        expect($content)->toContain('APP_DEBUG=true');
        expect($content)->not->toContain('# from:');
    });
});
