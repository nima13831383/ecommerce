<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReconcilePublicMedia extends Command
{
    protected $signature = 'media:reconcile-public
        {--dry-run : Report planned copies without changing files}
        {--source-disk= : Override the legacy source disk}';

    protected $description = 'Reconcile recognized Product and Blog media onto the configured public disk.';

    /** @var array<string, int> */
    private array $counts = [
        'copied' => 0,
        'planned' => 0,
        'skipped' => 0,
        'missing' => 0,
        'invalid' => 0,
        'errors' => 0,
    ];

    public function handle(): int
    {
        $sourceDiskName = (string) ($this->option('source-disk') ?: config('media.legacy_disk', 'local'));
        $destinationDiskName = (string) config('media.public_disk', 'public');

        if ($sourceDiskName === $destinationDiskName) {
            $this->info('Source and destination disks are identical; no reconciliation is required.');

            return self::SUCCESS;
        }

        $source = Storage::disk($sourceDiskName);
        $destination = Storage::disk($destinationDiskName);

        ProductImage::query()->select(['id', 'path'])->orderBy('id')->cursor()->each(function (ProductImage $image) use ($source, $destination, $sourceDiskName, $destinationDiskName): void {
            $this->reconcile('ProductImage', (int) $image->id, (string) $image->path, $source, $destination, $sourceDiskName, $destinationDiskName);
        });

        Post::withTrashed()->whereNotNull('featured_image')->select(['id', 'featured_image'])->orderBy('id')->cursor()->each(function (Post $post) use ($source, $destination, $sourceDiskName, $destinationDiskName): void {
            $this->reconcile('Post', (int) $post->id, (string) $post->featured_image, $source, $destination, $sourceDiskName, $destinationDiskName);
        });

        foreach ($this->counts as $label => $count) {
            $this->line("{$label}: {$count}");
        }

        return $this->counts['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reconcile(string $type, int $id, string $path, Filesystem $source, Filesystem $destination, string $sourceDiskName, string $destinationDiskName): void
    {
        if (! $this->isRelativePath($path)) {
            $this->counts['invalid']++;
            $this->warn("{$type}#{$id}: invalid non-relative path skipped.");

            return;
        }

        if ($destination->exists($path)) {
            $this->counts['skipped']++;
            $this->line("{$type}#{$id}: destination exists; skipped {$path}");

            return;
        }

        if (! $source->exists($path)) {
            $this->counts['missing']++;
            $this->warn("{$type}#{$id}: source missing on {$sourceDiskName}: {$path}");

            return;
        }

        if ($this->option('dry-run')) {
            $this->counts['planned']++;
            $this->line("{$type}#{$id}: would copy {$path} from {$sourceDiskName} to {$destinationDiskName}");

            return;
        }

        $stream = $source->readStream($path);
        if ($stream === false) {
            $this->counts['errors']++;
            $this->error("{$type}#{$id}: could not read {$path}");

            return;
        }

        try {
            $written = $destination->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        if (! $written || ! $destination->exists($path)) {
            $this->counts['errors']++;
            $this->error("{$type}#{$id}: destination verification failed for {$path}");

            return;
        }

        $this->counts['copied']++;
        $this->line("{$type}#{$id}: copied {$path}");
    }

    private function isRelativePath(string $path): bool
    {
        $segments = explode('/', str_replace('\\', '/', $path));

        return $path !== ''
            && ! in_array('..', $segments, true)
            && ! Str::contains($path, "\0")
            && ! Str::startsWith($path, ['/', '\\'])
            && preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) !== 1;
    }
}
