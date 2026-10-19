<?php

use App\Http\Controllers\ProposalDraftPaperController;
use App\Http\Middleware\EnsureWorkspace;
use App\Http\Middleware\HandleFileUploads;
use App\Models\ProposalDraft;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class FileUploadTransportRequest extends FormRequest
{
    public static bool $allowed = true;

    public function authorize(): bool
    {
        return self::$allowed;
    }

    public function rules(): array
    {
        return ['attachment' => ['required', 'file'], 'title' => ['required', 'string']];
    }
}

class FileUploadTransportController
{
    public static int $runs = 0;

    public function store(FileUploadTransportRequest $request, TopicProposal $topic): JsonResponse|RedirectResponse
    {
        self::$runs++;
        if ($request->input('mode') === 'error') {
            return back()->withErrors(['attachment' => 'The document could not be stored.'], 'upload');
        }
        if ($request->input('mode') === 'failure') {
            return response()->json(['message' => 'Temporary failure'], 503);
        }
        if ($request->input('mode') === 'external') {
            return redirect('https://outside.test/');
        }
        if ($request->header('X-Athena-Upload-Form') === '1' || $request->input('mode') === 'native') {
            return redirect('/upload-finished')->with('status', 'Document uploaded.');
        }

        return response()->json(['saved' => true, 'version' => self::$runs], 201);
    }
}

beforeEach(function () {
    $this->withoutVite();
    config(['cache.default' => 'array']);
    Cache::flush();
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'research_head']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('faculty', 'research_head');
    $this->topic = TopicProposal::create(['user_id' => $this->owner->id, 'title' => 'Upload transport project', 'status' => 'pending']);
    FileUploadTransportController::$runs = 0;
    FileUploadTransportRequest::$allowed = true;
    Route::middleware(['web', 'auth', 'workspace:faculty|research_head', HandleFileUploads::class])->group(function () {
        Route::post('/upload-transport/{topic}', [FileUploadTransportController::class, 'store'])->name('upload-transport.store');
        Route::post('/upload-transport/{topic}/preview', [FileUploadTransportController::class, 'store'])->name('upload-transport.preview');
        Route::put('/upload-draft/{proposalDraft}/papers/{paper}', [ProposalDraftPaperController::class, 'update'])->name('upload-draft.update');
        Route::post('/upload-unsafe', function (Request $request): JsonResponse {
            FileUploadTransportController::$runs++;

            return response()->json(['saved' => true]);
        });
    });
    Route::getRoutes()->refreshNameLookups();
    $this->actingAs($this->owner)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty']);
    $this->withCookie(config('session.cookie'), app('session')->getId());
    $this->uploadId = (string) Str::uuid();
    $this->uploadData = fn (array $extra = []): array => [
        'attachment' => UploadedFile::fake()->create('paper.pdf', 2, 'application/pdf'), 'title' => 'Research paper', ...$extra,
    ];
    $this->uploadHeaders = fn (array $extra = []): array => ['X-Athena-Upload-ID' => $this->uploadId, ...$extra];
});

test('native file upload redirects become JSON and successful retries replay once with flash', function () {
    $headers = ($this->uploadHeaders)(['X-Athena-Upload-Form' => '1']);
    $url = route('upload-transport.store', $this->topic);
    $this->post($url, ($this->uploadData)(), $headers)->assertOk()
        ->assertJson(['redirect' => url('/upload-finished')])->assertSessionHas('status', 'Document uploaded.');
    $this->post($url, ($this->uploadData)(), [...$headers, 'X-Athena-Upload-Retry' => '1'])
        ->assertOk()->assertJson(['redirect' => url('/upload-finished')])->assertSessionHas('status', 'Document uploaded.');
    expect(FileUploadTransportController::$runs)->toBe(1);
});

test('autosave success keeps its status and payload and skips stale validation on replay', function () {
    $url = route('upload-transport.store', $this->topic);
    $headers = ($this->uploadHeaders)();
    $this->post($url, ($this->uploadData)(), $headers)->assertCreated()->assertExactJson(['saved' => true, 'version' => 1]);
    $this->post($url, ($this->uploadData)(['title' => '']), [...$headers, 'X-Athena-Upload-Retry' => '1'])
        ->assertCreated()->assertExactJson(['saved' => true, 'version' => 1]);
    expect(FileUploadTransportController::$runs)->toBe(1);
});

test('redirected named error bags become validation JSON and are never cached', function () {
    $url = route('upload-transport.store', $this->topic);
    $headers = ($this->uploadHeaders)(['X-Athena-Upload-Form' => '1']);
    $this->from('/upload-form')->post($url, ($this->uploadData)(['mode' => 'error']), $headers)
        ->assertUnprocessable()->assertJsonValidationErrors('attachment');
    $this->post($url, ($this->uploadData)(), $headers)->assertOk()->assertJson(['redirect' => url('/upload-finished')]);
    expect(FileUploadTransportController::$runs)->toBe(2);
});

test('normal browser forms and preview requests keep their original behavior', function () {
    $this->post(route('upload-transport.store', $this->topic), ($this->uploadData)(['mode' => 'native']))
        ->assertRedirect('/upload-finished');
    $headers = ($this->uploadHeaders)();
    foreach (range(1, 2) as $attempt) {
        $this->post(route('upload-transport.preview', $this->topic), ($this->uploadData)(), $headers)->assertCreated();
    }
    expect(FileUploadTransportController::$runs)->toBe(3);
});

test('replay checks current form authorization and every bound view policy before returning cached content', function () {
    $url = route('upload-transport.store', $this->topic);
    $headers = ($this->uploadHeaders)();
    $this->post($url, ($this->uploadData)(), $headers)->assertCreated();
    FileUploadTransportRequest::$allowed = false;
    $this->post($url, ($this->uploadData)(), $headers)->assertForbidden();
    FileUploadTransportRequest::$allowed = true;
    $this->topic->update(['user_id' => User::factory()->create()->id]);
    $this->post($url, ($this->uploadData)(), $headers)->assertForbidden();
    expect(FileUploadTransportController::$runs)->toBe(1);
});

test('upload keys separate route targets users workspaces and sessions', function () {
    $headers = ($this->uploadHeaders)();
    $url = route('upload-transport.store', $this->topic);
    $this->post($url, ($this->uploadData)(), $headers)->assertCreated();
    expect(FileUploadTransportController::$runs)->toBe(1);
    $other = TopicProposal::create(['user_id' => $this->owner->id, 'title' => 'Other project', 'status' => 'pending']);
    $this->post(route('upload-transport.store', $other), ($this->uploadData)(), $headers)->assertCreated();
    expect(FileUploadTransportController::$runs)->toBe(2);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head']);
    app('session')->save();
    $this->post($url, ($this->uploadData)(), $headers)->assertCreated();
    expect(FileUploadTransportController::$runs)->toBe(3);
    app('session')->regenerate();
    $this->withCookie(config('session.cookie'), app('session')->getId());
    $this->post($url, ($this->uploadData)(), $headers)->assertCreated();
    expect(FileUploadTransportController::$runs)->toBe(4);
    $colleague = User::factory()->create();
    $colleague->assignRole('research_head');
    $this->actingAs($colleague)->post($url, ($this->uploadData)(), $headers)->assertCreated();
    expect(FileUploadTransportController::$runs)->toBe(5);
});

test('failed upload responses can retry and invalid identifiers do not run the mutation', function () {
    $url = route('upload-transport.store', $this->topic);
    $headers = ($this->uploadHeaders)();
    $this->post($url, ($this->uploadData)(['mode' => 'failure']), $headers)->assertServiceUnavailable();
    $this->post($url, ($this->uploadData)(), $headers)->assertCreated();
    $this->post($url, ($this->uploadData)(), ['X-Athena-Upload-ID' => 'not-a-uuid'])->assertUnprocessable();
    expect(FileUploadTransportController::$runs)->toBe(2);
});

test('inline-only authorization refuses an uncertain retry without repeating its mutation', function () {
    $headers = ($this->uploadHeaders)();
    $this->post('/upload-unsafe', ($this->uploadData)(), $headers)->assertOk();
    $this->post('/upload-unsafe', ($this->uploadData)(), [...$headers, 'X-Athena-Upload-Retry' => '1'])
        ->assertConflict()->assertJsonPath('code', 'upload_retry_unavailable');
    expect(FileUploadTransportController::$runs)->toBe(1);
});

test('native upload responses never send the client to another origin', function () {
    $this->post(route('upload-transport.store', $this->topic), ($this->uploadData)(['mode' => 'external']),
        ($this->uploadHeaders)(['X-Athena-Upload-Form' => '1']))->assertUnprocessable()->assertJsonMissingPath('redirect');
});

test('a saved proposal file replay survives its stale document version without writing another version', function () {
    Storage::fake('local');
    Queue::fake();
    config(['proposal_papers.expense-breakdown.mode' => 'upload',
        'proposal_papers.expense-breakdown.accepted_extensions' => ['pdf'],
        'proposal_papers.expense-breakdown.accepted_mime_types' => ['application/pdf'],
        'proposal_papers.expense-breakdown.max_kilobytes' => 25600]);
    $draft = ProposalDraft::create(['user_id' => $this->owner->id, 'project_title' => 'Retryable file upload']);
    $headers = ($this->uploadHeaders)(['X-Athena-Upload-Form' => '1']);
    $url = route('upload-draft.update', [$draft, 'expense-breakdown']);
    $data = fn (): array => ['document_version' => 0, 'documents' => [UploadedFile::fake()->create('expenses.pdf', 2, 'application/pdf')]];
    $this->put($url, $data(), $headers)->assertOk()->assertJsonStructure(['redirect']);
    $document = $draft->documents()->sole();
    $this->put($url, $data(), [...$headers, 'X-Athena-Upload-Retry' => '1'])->assertOk()->assertJsonStructure(['redirect']);
    expect($draft->documents()->count())->toBe(1)
        ->and($document->fresh()->lock_version)->toBe($document->lock_version);
});

test('busy uploads return a retry delay without running their mutation', function () {
    $url = route('upload-transport.store', $this->topic);
    $key = 'athena-upload:'.hash('sha256', json_encode([
        $this->owner->id, app('session')->getId(), 'faculty', 'POST', $url, $this->uploadId, null,
    ]));
    $lock = Cache::lock($key.':lock', 600);
    expect($lock->get())->toBeTrue();
    try {
        $this->post($url, ($this->uploadData)(), ($this->uploadHeaders)())->assertConflict()
            ->assertJsonPath('code', 'upload_in_progress')->assertHeader('Retry-After', '2');
        expect(FileUploadTransportController::$runs)->toBe(0);
    } finally {
        $lock->release();
    }
    $this->post($url, ($this->uploadData)(), ($this->uploadHeaders)())->assertCreated();
});

test('upload replay runs after authentication workspace and route model binding middleware', function () {
    $middleware = app('router')->gatherRouteMiddleware(Route::getRoutes()->getByName('upload-transport.store'));
    $upload = array_search(HandleFileUploads::class, $middleware, true);
    expect(array_search(Authenticate::class, $middleware, true))->toBeLessThan($upload)
        ->and(array_search(EnsureWorkspace::class.':faculty|research_head', $middleware, true))->toBeLessThan($upload)
        ->and(array_search(SubstituteBindings::class, $middleware, true))->toBeLessThan($upload);
});
