<?php

namespace App\Services;

use App\Models\ProposalDraft;
use App\Models\ResearchManual;
use App\Models\TopicProposal;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Throwable;

class ResearchManualLibrary
{
    public function __construct(private ResearchManualIndexer $indexer, private ResearchManualRetriever $retriever) {}

    public function active(): ?ResearchManual
    {
        if (! is_file($this->activeIndexPath())) {
            return null;
        }

        return $this->withLock(function (): ?ResearchManual {
            try {
                $index = $this->activeIndex();
            } catch (RuntimeException) {
                return null;
            }

            return $index !== null && Schema::hasTable('research_manuals')
                ? ResearchManual::where('version', $index['version'])->first()
                : null;
        }, LOCK_SH);
    }

    /** @return array<string, mixed>|null */
    public function activeIndex(): ?array
    {
        return $this->readIndex($this->activeIndexPath());
    }

    /** @return array<string, mixed> */
    public function index(ResearchManual $manual): array
    {
        $index = $this->readIndex($manual->indexPath());

        if ($index === null || $index['version'] !== $manual->version || ! is_file($manual->pdfPath()) || ! is_readable($manual->pdfPath())) {
            throw new RuntimeException('The saved research manual files are missing or do not match this edition.');
        }

        return $index;
    }

    /** @return array{manual: ?ResearchManual, index: array<string, mixed>, pdf_path: string, pdf_content?: string}|null */
    public function resolveVersion(string $version): ?array
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $version)) {
            return null;
        }

        return $this->withLock(function () use ($version): ?array {
            $manual = ResearchManual::where('version', $version)->first();

            if ($manual !== null) {
                return ['manual' => $manual, 'index' => $this->index($manual), 'pdf_path' => $manual->pdfPath()];
            }

            $index = $this->activeIndex();

            if ($index === null || $index['version'] !== $version) {
                return null;
            }

            $pdfPath = dirname($this->activeIndexPath()).'/manual.pdf';

            if (! is_file($pdfPath) || ! is_readable($pdfPath)) {
                throw new RuntimeException('This research manual PDF is unavailable. Contact the research office for a restored copy.');
            }

            // Activation can replace the legacy file after the shared lock is released.
            return ['manual' => null, 'index' => $index, 'pdf_path' => $pdfPath, 'pdf_content' => File::get($pdfPath)];
        }, LOCK_SH);
    }

    /**
     * @param  array<string, mixed>  $index
     * @param  list<string>  $reviewSections
     * @return array<string, mixed>
     */
    public function readerConfig(array $index, ?ResearchManual $manual, bool $canCheckGuidance = false, array $reviewSections = []): array
    {
        return [
            'title' => $index['title'], 'edition' => $manual?->edition, 'version' => $index['version'],
            'page_count' => $index['page_count'], 'unindexed_pages' => $index['unindexed_pages'],
            'pdf_url' => route('research-manuals.file', ['version' => $index['version']]),
            'read_url' => route('research-manuals.read', ['version' => $index['version']]),
            'guidance_url' => $manual !== null && $canCheckGuidance ? route('research-manuals.guidance-check', $manual) : null,
            'guidance_questions' => $manual !== null && $canCheckGuidance ? $this->guidanceQuestions($index, $manual, $reviewSections) : [],
        ];
    }

    /** @param array<string, mixed> $index @param list<string> $reviewSections @return list<string> */
    public function guidanceQuestions(array $index, ResearchManual $manual, array $reviewSections = [], bool $includeMissing = false): array
    {
        $questions = [];
        foreach (['What are the requirements for a research proposal?', 'What ethics review is required?', 'How are research outputs submitted?'] as $question) {
            if ($includeMissing || $this->retriever->retrieve($question, indexPath: $manual->indexPath()) !== []) {
                $questions[] = $question;
            }
        }

        foreach (array_slice(array_unique([...$reviewSections, ...array_column($index['chunks'], 'section')]), 0, 20) as $section) {
            if (count($questions) >= 5) {
                break;
            }
            if (preg_match_all('/(?<![\d.])\d+(?:\.\d+)+(?![\d.])/', $section, $numbers) > 0) {
                $number = $numbers[0][array_key_last($numbers[0])];
                $question = 'What does section '.$number.' require in this edition?';
            } else {
                $question = 'What does this edition say about '.Str::limit($section, 180, '').'?';
            }
            if (! in_array($question, $questions, true) && ($includeMissing || $this->retriever->retrieve($question, indexPath: $manual->indexPath()) !== [])) {
                $questions[] = $question;
            }
        }

        return $questions;
    }

    public function importLegacy(): ?ResearchManual
    {
        return $this->withLock(fn (): ?ResearchManual => DB::transaction(function (): ?ResearchManual {
            $manual = $this->preserveActive();
            $this->pinUnassigned($manual);

            return $manual;
        }));
    }

    public function upload(string $sourcePath, string $title, string $edition, string $effectiveDate, User $user): ResearchManual
    {
        $directory = 'research-manual/editions/'.Str::uuid();
        $indexPath = Storage::disk('local')->path($directory.'/index.json');

        try {
            try {
                $index = $this->indexer->index($sourcePath, $title, indexPath: $indexPath);
            } catch (RuntimeException $exception) {
                report($exception);

                throw ValidationException::withMessages(['manual' => 'The PDF could not be read. Upload an unprotected PDF with selectable text, or a copy processed with text recognition (OCR). Contact the administrator if it still cannot be read.']);
            }

            $manual = $this->withLock(fn (): ResearchManual => DB::transaction(function () use ($index, $directory, $edition, $effectiveDate, $user): ResearchManual {
                if (ResearchManual::where('version', $index['version'])->exists()
                    || ($this->activeIndex()['version'] ?? null) === $index['version']) {
                    throw ValidationException::withMessages(['manual' => 'This PDF and title are already indexed. Upload a different edition or title.']);
                }

                $this->pinUnassigned($this->preserveActive());
                $manual = ResearchManual::create([
                    'title' => $index['title'], 'edition' => $edition, 'effective_date' => $effectiveDate,
                    'version' => $index['version'], 'directory' => $directory, 'page_count' => $index['page_count'],
                    'unindexed_pages' => $index['unindexed_pages'], 'uploaded_by' => $user->getKey(),
                ]);
                activity()->performedOn($manual)->causedBy($user)->event('uploaded')->log('Research manual edition uploaded for review');

                return $manual;
            }));
        } catch (Throwable $exception) {
            Storage::disk('local')->deleteDirectory($directory);

            throw $exception;
        }

        app(ResearchManualChecks::class)->queue($manual);

        return $manual;
    }

    public function activate(ResearchManual $manual): void
    {
        if ($manual->effective_date?->isAfter(today())) {
            throw ValidationException::withMessages(['effective_date' => 'This edition cannot be activated before its effective date.']);
        }

        $this->withLock(function () use ($manual): void {
            $index = $this->index($manual);
            $indexPath = $this->activeIndexPath();
            $pdfPath = dirname($indexPath).'/manual.pdf';
            $previousIndex = is_file($indexPath) ? File::get($indexPath) : null;
            $previousPdf = is_file($pdfPath) ? File::get($pdfPath) : null;
            $publishing = false;

            try {
                DB::transaction(function () use ($manual, $index, $indexPath, $pdfPath, &$publishing): void {
                    $previous = $this->preserveActive();
                    $this->pinUnassigned($previous);
                    $manual->forceFill(['activated_at' => now()])->save();
                    activity()->performedOn($manual)->causedBy(auth()->user())->event('activated')
                        ->withProperties(['previous_manual_id' => $previous?->getKey()])->log('Research manual edition activated');
                    $publishing = true;
                    File::replace($pdfPath, File::get($manual->pdfPath()));
                    File::replace($indexPath, json_encode([...$index, 'managed_manual_id' => $manual->getKey()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                });
            } catch (Throwable $exception) {
                if ($publishing) {
                    try {
                        $previousPdf !== null ? File::replace($pdfPath, $previousPdf) : File::delete($pdfPath);
                        $previousIndex !== null ? File::replace($indexPath, $previousIndex) : File::delete($indexPath);
                    } catch (Throwable $restoreException) {
                        report($exception);

                        throw new RuntimeException('Manual activation failed and the previous active files could not be restored. Contact the administrator before trying again.', 0, $restoreException);
                    }
                }

                throw $exception;
            }
        });
    }

    private function activeIndexPath(): string
    {
        return (string) config('research_assistant.manual.index_path', storage_path('app/private/research-manual/index.json'));
    }

    /** @return array<string, mixed>|null */
    private function readIndex(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            $index = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException|FileNotFoundException $exception) {
            throw new RuntimeException('The research manual index is unreadable. The active manual has not been changed.', 0, $exception);
        }

        if (! is_array($index) || ($index['schema_version'] ?? null) !== 1 || ! is_string($index['version'] ?? null)
            || ! preg_match('/^[a-f0-9]{64}$/', $index['version'])
            || ! is_string($index['title'] ?? null) || trim($index['title']) === ''
            || ! is_int($index['page_count'] ?? null) || $index['page_count'] < 1
            || ! is_array($index['unindexed_pages'] ?? null) || ! array_is_list($index['unindexed_pages'])
            || ! is_array($index['chunks'] ?? null) || ! array_is_list($index['chunks']) || $index['chunks'] === []) {
            throw new RuntimeException('The research manual index is incomplete. The active manual has not been changed.');
        }

        foreach ($index['unindexed_pages'] as $page) {
            if (! is_int($page) || $page < 1 || $page > $index['page_count']) {
                throw new RuntimeException('The research manual index contains invalid page metadata. The active manual has not been changed.');
            }
        }

        foreach ($index['chunks'] as $chunk) {
            if (! is_array($chunk) || ! is_string($chunk['id'] ?? null) || trim($chunk['id']) === ''
                || ! is_string($chunk['section'] ?? null) || ! is_string($chunk['content'] ?? null) || trim($chunk['content']) === ''
                || ! is_int($chunk['page_start'] ?? null) || ! is_int($chunk['page_end'] ?? null)
                || $chunk['page_start'] < 1 || $chunk['page_end'] < $chunk['page_start'] || $chunk['page_end'] > $index['page_count']) {
                throw new RuntimeException('The research manual index contains an invalid text passage. The active manual has not been changed.');
            }
        }

        return $index;
    }

    private function preserveActive(): ?ResearchManual
    {
        $index = $this->activeIndex();

        if ($index === null) {
            return null;
        }

        $existing = ResearchManual::where('version', $index['version'])->first();

        if ($existing !== null) {
            $this->index($existing);

            return $existing;
        }

        $pdfPath = dirname($this->activeIndexPath()).'/manual.pdf';

        if (! is_file($pdfPath)) {
            throw new RuntimeException('The active manual PDF is missing. Restore it before replacing this edition.');
        }

        $directory = 'research-manual/editions/'.Str::uuid();
        $target = Storage::disk('local')->path($directory);
        File::ensureDirectoryExists($target);

        try {
            if (! File::copy($pdfPath, $target.'/manual.pdf') || ! File::copy($this->activeIndexPath(), $target.'/index.json')) {
                throw new RuntimeException('The existing research manual could not be preserved.');
            }

            return ResearchManual::create([
                'title' => $index['title'], 'edition' => 'Legacy edition', 'version' => $index['version'],
                'directory' => $directory, 'page_count' => $index['page_count'],
                'unindexed_pages' => $index['unindexed_pages'], 'activated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->deleteDirectory($directory);

            throw $exception;
        }
    }

    private function pinUnassigned(?ResearchManual $manual): void
    {
        if ($manual !== null) {
            ProposalDraft::withTrashed()->whereNull('research_manual_id')->update(['research_manual_id' => $manual->getKey()]);
            TopicProposal::whereNull('research_manual_id')->update(['research_manual_id' => $manual->getKey()]);
        }
    }

    private function withLock(Closure $callback, int $mode = LOCK_EX): mixed
    {
        $path = $this->activeIndexPath();
        File::ensureDirectoryExists(dirname($path));
        $lock = @fopen($path.'.lock', 'c');

        if ($lock === false) {
            throw new RuntimeException('The research manual library could not be locked.');
        }

        try {
            if (! flock($lock, $mode)) {
                throw new RuntimeException('The research manual library could not be locked.');
            }

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
