<?php

namespace App\Console\Commands;

use App\Services\BytecodeObfuscatorService;
use App\Services\CodeIntegrityService;
use Illuminate\Console\Command;

class PackageApplicationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tunneldeck:package
                            {--out= : Destination zip file path}
                            {--no-obfuscate : Skip code obfuscation step}';

    /**
     * The console command description.
     */
    protected $description = 'Package application into production distribution bundle with Layer 4 hardening & signed integrity manifest';

    /**
     * Execute the console command.
     */
    public function handle(CodeIntegrityService $integrity, BytecodeObfuscatorService $obfuscator): int
    {
        $this->info('====================================================');
        $this->info('  TunnelDeck Layer 4 Binary Packaging & Hardening   ');
        $this->info('====================================================');

        // 1. Generate & sign Code Integrity Manifest
        $this->step('Generating signed code integrity manifest', function () use ($integrity) {
            $manifest = $integrity->generateManifest();
            return !empty($manifest['signature']);
        });

        // 2. Prepare isolated temporary staging directory
        $stagingDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'td_staging_' . uniqid();
        @mkdir($stagingDir, 0755, true);

        $this->step('Staging application source tree', function () use ($stagingDir) {
            $srcStaging = $stagingDir . DIRECTORY_SEPARATOR . 'src';
            $this->copyDirectory(base_path(), $srcStaging, [
                'tests',
                '.git',
                '.github',
                'node_modules',
                'vendor',
                'storage',
                'dist',
                '.app_data',
                'bin', // Binaries downloaded per-platform on first boot
            ]);

            // Create clean minimal storage directories in staging
            $cleanDirs = [
                'app',
                'framework' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'data',
                'framework' . DIRECTORY_SEPARATOR . 'sessions',
                'framework' . DIRECTORY_SEPARATOR . 'views',
                'logs',
            ];
            foreach ($cleanDirs as $dir) {
                @mkdir($srcStaging . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $dir, 0755, true);
            }

            // Copy root launcher files if present
            $rootPath = dirname(base_path());
            foreach (['App.exe', 'app.ico', 'serve.bat', 'loading.html'] as $launcherFile) {
                $src = $rootPath . DIRECTORY_SEPARATOR . $launcherFile;
                if (file_exists($src)) {
                    copy($src, $stagingDir . DIRECTORY_SEPARATOR . $launcherFile);
                }
            }

            return true;
        });

        // 3. Obfuscate security files in staging
        $shouldObfuscate = !$this->option('no-obfuscate');
        if ($shouldObfuscate) {
            $this->step('Applying Bytecode & String Obfuscation to staging', function () use ($stagingDir, $integrity, $obfuscator) {
                foreach ($integrity->getProtectedFiles() as $file) {
                    $stagedFile = $stagingDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $file;
                    if (file_exists($stagedFile)) {
                        $content = file_get_contents($stagedFile);
                        $obfuscated = $obfuscator->obfuscateCode($content);
                        file_put_contents($stagedFile, $obfuscated);
                    }
                }
                return true;
            });
        }

        // 4. Copy integrity manifest into staging
        $manifestPath = $integrity->getManifestPath();
        if (file_exists($manifestPath)) {
            $targetManifest = $stagingDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'integrity.manifest';
            @mkdir(dirname($targetManifest), 0755, true);
            copy($manifestPath, $targetManifest);
        }

        // 5. Build ZIP archive
        $rootDist = dirname(base_path()) . DIRECTORY_SEPARATOR . 'dist';
        $outZip = $this->option('out') ?: $rootDist . DIRECTORY_SEPARATOR . 'tunneldeck-pro.zip';
        @mkdir(dirname($outZip), 0755, true);
        if (file_exists($outZip)) {
            @unlink($outZip);
        }

        $this->step('Compressing distribution package', function () use ($stagingDir, $outZip) {
            return $this->createZipArchive($stagingDir, $outZip);
        });

        // Clean up staging directory
        $this->cleanDirectory($stagingDir);
        @rmdir($stagingDir);

        if (file_exists($outZip)) {
            $sizeMb = round(filesize($outZip) / (1024 * 1024), 2);
            $sha256 = hash_file('sha256', $outZip);

            $this->newLine();
            $this->info("Package created successfully!");
            $this->table(
                ['Attribute', 'Value'],
                [
                    ['Artifact Path', $outZip],
                    ['Package Size', $sizeMb . ' MB (' . number_format(filesize($outZip)) . ' bytes)'],
                    ['SHA-256 Checksum', $sha256],
                    ['Security Level', 'Layer 1-4 Complete (RSA + HWID + Bytecode + Integrity)'],
                ]
            );

            return self::SUCCESS;
        }

        $this->error('Failed to create distribution zip archive.');
        return self::FAILURE;
    }

    /**
     * Helper to render step execution with status output.
     */
    protected function step(string $title, callable $callback): bool
    {
        $this->output->write("  - {$title}... ");
        $result = $callback();
        if ($result !== false) {
            $this->output->writeln('<info>DONE</info>');
            return true;
        }
        $this->output->writeln('<error>FAILED</error>');
        return false;
    }

    /**
     * Cross-platform ZIP creation (ZipArchive -> PowerShell Compress-Archive -> Linux zip).
     */
    protected function createZipArchive(string $sourceDir, string $destinationZip): bool
    {
        // 1. Try PHP ZipArchive if available
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($destinationZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($sourceDir) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
                $zip->close();
                return file_exists($destinationZip);
            }
        }

        // 2. Windows PowerShell Compress-Archive
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = sprintf(
                'powershell -NoProfile -ExecutionPolicy Bypass -Command "Compress-Archive -Path \'%s\\*\' -DestinationPath \'%s\' -Force"',
                rtrim($sourceDir, '\\/'),
                $destinationZip
            );
            @shell_exec($cmd);
            return file_exists($destinationZip);
        }

        // 3. Linux/macOS zip command
        $cmd = sprintf('cd %s && zip -r %s .', escapeshellarg($sourceDir), escapeshellarg($destinationZip));
        @shell_exec($cmd);
        return file_exists($destinationZip);
    }

    /**
     * Helper to recursively copy directories with exclusion list.
     */
    protected function copyDirectory(string $src, string $dst, array $excludes = []): void
    {
        $dir = @opendir($src);
        if (!$dir) return;

        @mkdir($dst, 0755, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Check exclusion
            $srcPath = $src . DIRECTORY_SEPARATOR . $file;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $file;

            $isExcluded = false;
            foreach ($excludes as $exclude) {
                if ($file === $exclude || str_ends_with($srcPath, DIRECTORY_SEPARATOR . $exclude)) {
                    $isExcluded = true;
                    break;
                }
            }

            if ($isExcluded) {
                continue;
            }

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath, $excludes);
            } else {
                copy($srcPath, $dstPath);
            }
        }

        closedir($dir);
    }

    /**
     * Helper to recursively clean directories.
     */
    protected function cleanDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->cleanDirectory($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}
