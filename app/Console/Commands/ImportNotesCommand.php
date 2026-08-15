<?php

namespace App\Console\Commands;

use App\Jobs\ImportNotes;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('devnotes:import {file : Path to a devnotes export JSON file}')]
#[Description('Import a devnotes export, skipping note ids that already exist')]
class ImportNotesCommand extends Command
{
    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $disk = config('filesystems.default');
        $storedPath = 'imports/'.Str::uuid().'.json';
        Storage::disk($disk)->put($storedPath, file_get_contents($file));

        $report = (new ImportNotes($disk, $storedPath))->handle();

        $this->info("Imported: {$report['imported']}");

        if ($report['skipped'] !== []) {
            $this->info('Skipped existing ids: '.implode(', ', $report['skipped']));
        }

        $this->info("Users created: {$report['users_created']}");
        $this->info("Teams created: {$report['teams_created']}");

        return self::SUCCESS;
    }
}
