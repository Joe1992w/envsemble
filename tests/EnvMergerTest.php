<?php

declare(strict_types=1);

use JoeWare\Envsemble\EnvMerger;
use JoeWare\Envsemble\EnvMergeResult;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/envsemble_test_'.uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir.'/patches');

    $this->merger = new EnvMerger;
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

describe('EnvMerger', function () {
    it('can parse a basic env file', function () {
        $baseContent = "APP_NAME=MyApp\nAPP_ENV=production\nDB_HOST=localhost";
        $baseFile = $this->tempDir.'/.env.base';
        file_put_contents($baseFile, $baseContent);

        $result = $this->merger->merge($baseFile, $this->tempDir.'/patches');

        expect($result)->toBeInstanceOf(EnvMergeResult::class);
        expect($result->getKeysCount())->toBe(3);
        expect($result->getVariableValue('APP_NAME'))->toBe('MyApp');
        expect($result->getVariableValue('APP_ENV'))->toBe('production');
        expect($result->getVariableValue('DB_HOST'))->toBe('localhost');
    });

    it('can merge patch files in order', function () {
        // Base file
        $baseContent = "APP_NAME=MyApp\nAPP_ENV=production\nDB_HOST=localhost";
        $baseFile = $this->tempDir.'/.env.base';
        file_put_contents($baseFile, $baseContent);

        // Patch file 1
        $patch1Content = "APP_ENV=staging\nAPP_DEBUG=true";
        file_put_contents($this->tempDir.'/patches/01-staging.env', $patch1Content);

        // Patch file 2
        $patch2Content = "DB_HOST=127.0.0.1\nCACHE_DRIVER=redis";
        file_put_contents($this->tempDir.'/patches/02-cache.env', $patch2Content);

        $result = $this->merger->merge($baseFile, $this->tempDir.'/patches');

        expect($result->getKeysCount())->toBe(5);
        expect($result->getVariableValue('APP_NAME'))->toBe('MyApp');
        expect($result->getVariableValue('APP_ENV'))->toBe('staging'); // overridden
        expect($result->getVariableValue('DB_HOST'))->toBe('127.0.0.1'); // overridden
        expect($result->getVariableValue('APP_DEBUG'))->toBe('true'); // added
        expect($result->getVariableValue('CACHE_DRIVER'))->toBe('redis'); // added
    });

    it('can delete keys using DELETE marker', function () {
        // Base file
        $baseContent = "APP_NAME=MyApp\nAPP_ENV=production\nDB_HOST=localhost\nDB_PORT=3306";
        $baseFile = $this->tempDir.'/.env.base';
        file_put_contents($baseFile, $baseContent);

        // Patch file with deletion
        $patchContent = "DB_HOST=__DELETE__\nAPP_DEBUG=true";
        file_put_contents($this->tempDir.'/patches/01-remove-host.env', $patchContent);

        $result = $this->merger->merge($baseFile, $this->tempDir.'/patches');

        expect($result->getKeysCount())->toBe(4);
        expect($result->hasVariable('DB_HOST'))->toBeFalse();
        expect($result->getVariableValue('APP_NAME'))->toBe('MyApp');
        expect($result->getVariableValue('APP_DEBUG'))->toBe('true');
        expect($result->getDeletedKeysCount())->toBe(1);
        expect($result->getAddedKeysCount())->toBe(1);
    });

    it('handles quoted values correctly', function () {
        $baseContent = 'APP_NAME="My App With Spaces"'."\n"."APP_KEY='secret key with \"quotes\"'";
        $baseFile = $this->tempDir.'/.env.base';
        file_put_contents($baseFile, $baseContent);

        $result = $this->merger->merge($baseFile, $this->tempDir.'/patches');

        expect($result->getVariableValue('APP_NAME'))->toBe('My App With Spaces');
        expect($result->getVariableValue('APP_KEY'))->toBe('secret key with "quotes"');
    });

    it('parses inline comments', function () {
        $baseContent = 'APP_NAME=MyApp # primary app';
        $baseFile = $this->tempDir.'/.env.base';
        file_put_contents($baseFile, $baseContent);

        $result = $this->merger->merge($baseFile, $this->tempDir.'/patches');
        $variable = $result->getVariable('APP_NAME');

        expect($variable['value'])->toBe('MyApp');
        expect($variable['comment'])->toBe('primary app');
    });

    it('generates output with source comments', function () {
        // Base file
        $baseContent = "APP_NAME=MyApp\nDB_HOST=localhost";
        $baseFile = $this->tempDir.'/.env.base';
        file_put_contents($baseFile, $baseContent);

        // Patch file
        $patchContent = "APP_ENV=staging\nDB_HOST=127.0.0.1";
        file_put_contents($this->tempDir.'/patches/01-staging.env', $patchContent);

        $result = $this->merger->merge($baseFile, $this->tempDir.'/patches', true);
        $output = $this->merger->generateOutput($result, true);

        expect($output)->toContain('# Generated by Envsemble');
        expect($output)->toContain('APP_NAME=MyApp # from: .env.base');
        expect($output)->toContain('DB_HOST=127.0.0.1 # from: 01-staging.env');
        expect($output)->toContain('APP_ENV=staging # from: 01-staging.env');
    });

    it('generates output without comments when requested', function () {
        $baseContent = 'APP_NAME=MyApp';
        $baseFile = $this->tempDir.'/.env.base';
        file_put_contents($baseFile, $baseContent);

        $result = $this->merger->merge($baseFile, $this->tempDir.'/patches', false);
        $output = $this->merger->generateOutput($result, false);

        expect($output)->not->toContain('# from:');
        expect($output)->toBe("APP_NAME=MyApp\n");
    });

    it('provides detailed merge report', function () {
        // Base file
        $baseContent = "APP_NAME=MyApp\nAPP_ENV=production\nDB_HOST=localhost";
        $baseFile = $this->tempDir.'/.env.base';
        file_put_contents($baseFile, $baseContent);

        // Patch file
        $patchContent = "APP_ENV=staging\nAPP_DEBUG=true\nDB_HOST=__DELETE__";
        file_put_contents($this->tempDir.'/patches/01-changes.env', $patchContent);

        $result = $this->merger->merge($baseFile, $this->tempDir.'/patches');
        $report = $result->getReport();

        expect($report['base_keys_count'])->toBe(3);
        expect($report['patch_files_count'])->toBe(1);
        expect($report['final_keys_count'])->toBe(3);
        expect($result->getDeletedKeysCount())->toBe(1);
        expect($result->getModifiedKeysCount())->toBe(1);
        expect($result->getAddedKeysCount())->toBe(1);
        expect($result->getDeletedKeys())->toContain('DB_HOST');
        expect($result->getModifiedKeys())->toContain('APP_ENV');
        expect($result->getAddedKeys())->toContain('APP_DEBUG');
    });

    it('can squash files', function () {
        // Base file
        $baseContent = "APP_NAME=MyApp\nDB_HOST=localhost";
        $baseFile = $this->tempDir.'/.env.base';
        file_put_contents($baseFile, $baseContent);

        // Patch files
        $patch1 = $this->tempDir.'/patches/01-staging.env';
        file_put_contents($patch1, 'APP_ENV=staging');

        $patch2 = $this->tempDir.'/patches/02-debug.env';
        file_put_contents($patch2, 'APP_DEBUG=true');

        $outputFile = $this->tempDir.'/squashed.env';
        $result = $this->merger->squash($baseFile, $this->tempDir.'/patches', $outputFile);

        // Check that output file was created
        expect(file_exists($outputFile))->toBeTrue();

        // Check that patch files were removed
        expect(file_exists($patch1))->toBeFalse();
        expect(file_exists($patch2))->toBeFalse();

        // Check content
        $content = file_get_contents($outputFile);
        expect($content)->toContain('APP_NAME=MyApp');
        expect($content)->toContain('APP_ENV=staging');
        expect($content)->toContain('APP_DEBUG=true');
    });

    it('throws exception for missing base file', function () {
        expect(fn () => $this->merger->merge('/nonexistent/file', $this->tempDir.'/patches'))
            ->toThrow(InvalidArgumentException::class, 'Base file not found');
    });

    it('throws exception for missing patch directory', function () {
        $baseFile = $this->tempDir.'/.env.base';
        file_put_contents($baseFile, 'APP_NAME=Test');

        expect(fn () => $this->merger->merge($baseFile, '/nonexistent/directory'))
            ->toThrow(InvalidArgumentException::class, 'Patch directory not found');
    });

    it('handles empty patch directory gracefully', function () {
        $baseContent = 'APP_NAME=MyApp';
        $baseFile = $this->tempDir.'/.env.base';
        file_put_contents($baseFile, $baseContent);

        $result = $this->merger->merge($baseFile, $this->tempDir.'/patches');

        expect($result->getKeysCount())->toBe(1);
        expect($result->getVariableValue('APP_NAME'))->toBe('MyApp');
        expect($result->getPatchFilesCount())->toBe(0);
    });

    it('ignores comments and empty lines in env files', function () {
        $baseContent = "# This is a comment\nAPP_NAME=MyApp\n\n# Another comment\nAPP_ENV=production\n";
        $baseFile = $this->tempDir.'/.env.base';
        file_put_contents($baseFile, $baseContent);

        $result = $this->merger->merge($baseFile, $this->tempDir.'/patches');

        expect($result->getKeysCount())->toBe(2);
        expect($result->getVariableValue('APP_NAME'))->toBe('MyApp');
        expect($result->getVariableValue('APP_ENV'))->toBe('production');
    });
});
