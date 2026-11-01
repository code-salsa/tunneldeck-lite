<?php

namespace App\Console\Commands;

use App\Services\BytecodeObfuscatorService;
use App\Services\CodeIntegrityService;
use Illuminate\Console\Command;

class ObfuscateCodeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tunneldeck:obfuscate
                            {--file= : Specific relative path to obfuscate}
                            {--out= : Output directory (defaults to storage/dist/obfuscated)}
                            {--dry-run : Simulate obfuscation without writing files}';

    /**
     * The console command description.
     */
    protected $description = 'Obfuscate PHP source files and encrypt sensitive string literals (Layer 4 Hardening)';

    /**
     * Execute the console command.
     */
    public function handle(BytecodeObfuscatorService $obfuscator, CodeIntegrityService $integrity): int
    {
        $this->info('Starting Layer 4 Bytecode & String Obfuscation...');

        $outDir = $this->option('out') ?: storage_path('dist/obfuscated');
        $dryRun = (bool) $this->option('dry-run');
        $specificFile = $this->option('file');

        $files = $specificFile ? [$specificFile] : $integrity->getProtectedFiles();
        $tableRows = [];

        foreach ($files as $file) {
            $fullPath = base_path($file);
            if (!file_exists($fullPath) || !is_readable($fullPath)) {
                $this->warn("Skipping missing file: {$file}");
                continue;
            }

            $raw = file_get_contents($fullPath);
            $origSize = strlen($raw);

            $obfuscated = $obfuscator->obfuscateCode($raw);
            $obfSize = strlen($obfuscated);
            $ratio = $origSize > 0 ? round(($obfSize / $origSize) * 100, 1) . '%' : 'N/A';

            if (!$dryRun) {
                $targetFile = $outDir . DIRECTORY_SEPARATOR . $file;
                $targetDir = dirname($targetFile);
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }
                file_put_contents($targetFile, $obfuscated);
            }

            $tableRows[] = [
                $file,
                number_format($origSize) . ' bytes',
                number_format($obfSize) . ' bytes',
                $ratio,
                $dryRun ? 'Simulated' : 'Obfuscated',
            ];
        }

        $this->table(
            ['Protected File', 'Original Size', 'Obfuscated Size', 'Ratio', 'Status'],
            $tableRows
        );

        if ($dryRun) {
            $this->info('[DRY RUN] No files written to disk.');
        } else {
            $this->info("Obfuscated files saved to: {$outDir}");
        }

        return self::SUCCESS;
    }
}

