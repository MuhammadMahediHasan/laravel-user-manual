<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use MuhammadMahediHasan\UserManual\Events\FullPdfGenerated;
use MuhammadMahediHasan\UserManual\Events\FullPdfGenerationFailed;
use MuhammadMahediHasan\UserManual\Facades\UserManual;
use MuhammadMahediHasan\UserManual\Jobs\GenerateFullManualPdf;
use MuhammadMahediHasan\UserManual\Pdf\FullPdfGenerator;
use MuhammadMahediHasan\UserManual\Pdf\FullPdfStore;
use MuhammadMahediHasan\UserManual\Pdf\PdfScope;

/*
| Test roles: "basic" sees public pages, "editor" also sees "material",
| "viewer" sees exactly what "basic" sees (to prove files are shared).
*/
beforeEach(function () {
    Storage::fake('local');

    $this->roles = [
        'basic' => [],
        'viewer' => [],
        'editor' => ['material_access'],
    ];

    UserManual::resolveRolesUsing(
        fn () => $this->roles,
        fn (Authenticatable $user) => $user->testRoles[0] ?? 'basic',
    );
});

afterEach(function () {
    UserManual::useRoleResolver(null);
});

function scope(string $locale = 'en'): PdfScope
{
    return new PdfScope('default', config('user-manual.version', '1.0'), $locale);
}

function store(): FullPdfStore
{
    return app(FullPdfStore::class);
}

it('serves the stored pdf of the user role without rendering', function () {
    $this->artisan('user-manual:pdf')->assertSuccessful();

    $entry = store()->entry(scope(), 'editor');
    expect($entry)->not->toBeNull();

    Queue::fake();

    $response = $this->actingAs($this->makeAuthenticatable(roles: ['editor']))
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->streamedContent())->toBe(Storage::disk('local')->get(store()->path(scope(), $entry['file'])));
    Queue::assertNothingPushed();
});

it('queues a missing pdf once and tells the user it is being generated', function () {
    Queue::fake();

    $user = $this->makeAuthenticatable(roles: ['editor']);

    $this->actingAs($user)->get(route('user-manual.pdf.full', ['locale' => 'en']))
        ->assertStatus(202)
        ->assertHeader('Retry-After')
        ->assertSee('Preparing your PDF');

    $this->actingAs($user)->getJson(route('user-manual.pdf.full', ['locale' => 'en']))
        ->assertStatus(202)
        ->assertJson(['status' => 'generating']);

    Queue::assertPushed(GenerateFullManualPdf::class, 1);
    Queue::assertPushed(fn (GenerateFullManualPdf $job) => $job->manual === 'default'
        && $job->locale === 'en'
        && $job->roleKey === 'editor');
});

it('serves the pdf right away when the queue is sync', function () {
    $response = $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));

    $response->assertOk();
    expect($response->streamedContent())->toContain('%PDF-');
});

it('gives each page set its own pdf and shares it between roles that see the same pages', function () {
    $this->artisan('user-manual:pdf')->assertSuccessful();

    $basic = store()->entry(scope(), 'basic');
    $viewer = store()->entry(scope(), 'viewer');
    $editor = store()->entry(scope(), 'editor');

    expect($basic['file'])->toBe($viewer['file'])
        ->and($editor['file'])->not->toBe($basic['file']);

    $disk = Storage::disk('local');
    expect(strlen($disk->get(store()->path(scope(), $basic['file']))))
        ->toBeLessThan(strlen($disk->get(store()->path(scope(), $editor['file']))));
});

it('keeps serving the old pdf until the new one is ready, then deletes it', function () {
    $this->artisan('user-manual:pdf', ['--role' => ['basic']])->assertSuccessful();
    $old = store()->entry(scope(), 'basic');

    // basic now gets the editor's permissions.
    $this->roles['basic'] = ['material_access'];

    Queue::fake();
    UserManual::regenerateRole('basic');
    Queue::assertPushed(GenerateFullManualPdf::class, count(config('user-manual.locales')));

    // Still the old file while the job waits.
    expect(store()->entry(scope(), 'basic'))->toBe($old);

    app(FullPdfGenerator::class)->generate('default', 'en', 'basic');

    $new = store()->entry(scope(), 'basic');
    expect($new['file'])->not->toBe($old['file'])
        ->and(Storage::disk('local')->exists(store()->path(scope(), $new['file'])))->toBeTrue()
        ->and(Storage::disk('local')->exists(store()->path(scope(), $old['file'])))->toBeFalse();
});

it('does not delete a file another role still uses', function () {
    $this->artisan('user-manual:pdf')->assertSuccessful();
    $shared = store()->entry(scope(), 'viewer')['file'];

    $this->roles['basic'] = ['material_access'];
    app(FullPdfGenerator::class)->generate('default', 'en', 'basic');

    expect(Storage::disk('local')->exists(store()->path(scope(), $shared)))->toBeTrue();
});

it('re-generates when content changes and serves the old file meanwhile', function () {
    $this->artisan('user-manual:pdf', ['--role' => ['basic'], '--locale' => ['en']])->assertSuccessful();
    $old = store()->entry(scope(), 'basic');

    $intro = rtrim(config('user-manual.content_path'), '/').'/1.0/en/introduction.md';
    $mtime = filemtime($intro);
    touch($intro, $mtime + 10);
    clearstatcache();

    try {
        Queue::fake();

        $this->actingAs($this->makeAuthenticatable())
            ->get(route('user-manual.pdf.full', ['locale' => 'en']))
            ->assertOk();

        Queue::assertPushed(GenerateFullManualPdf::class, 1);

        app(FullPdfGenerator::class)->generate('default', 'en', 'basic');
        expect(store()->entry(scope(), 'basic')['content_hash'])->not->toBe($old['content_hash']);
    } finally {
        touch($intro, $mtime);
        clearstatcache();
    }
});

it('skips rendering when the stored pdf is already up to date', function () {
    $generator = app(FullPdfGenerator::class);

    expect($generator->generate('default', 'en', 'basic'))->toBe(FullPdfGenerator::GENERATED)
        ->and($generator->generate('default', 'en', 'viewer'))->toBe(FullPdfGenerator::REUSED)
        ->and($generator->generate('default', 'en', 'basic'))->toBe(FullPdfGenerator::UNCHANGED)
        ->and($generator->generate('default', 'en', 'basic', force: true))->toBe(FullPdfGenerator::GENERATED);
});

it('forgets deleted roles and removes their files', function () {
    $this->artisan('user-manual:pdf')->assertSuccessful();
    $editorFile = store()->entry(scope(), 'editor')['file'];

    UserManual::forgetRole('editor');

    expect(store()->entry(scope(), 'editor'))->toBeNull()
        ->and(Storage::disk('local')->exists(store()->path(scope(), $editorFile)))->toBeFalse()
        ->and(store()->entry(scope(), 'basic'))->not->toBeNull();
});

it('forgets a role whose job runs after the role was deleted', function () {
    $this->artisan('user-manual:pdf')->assertSuccessful();
    unset($this->roles['editor']);

    expect(app(FullPdfGenerator::class)->generate('default', 'en', 'editor'))->toBe(FullPdfGenerator::FORGOTTEN)
        ->and(store()->entry(scope(), 'editor'))->toBeNull();
});

it('prunes roles that no longer exist when the command runs', function () {
    $this->artisan('user-manual:pdf')->assertSuccessful();
    unset($this->roles['editor']);

    $this->artisan('user-manual:pdf')->assertSuccessful();

    expect(store()->manifest(scope()))->toHaveKeys(['basic', 'viewer'])
        ->and(store()->manifest(scope()))->not->toHaveKey('editor')
        ->and(Storage::disk('local')->files(store()->directory(scope())))->toHaveCount(2); // 1 pdf + manifest
});

it('queues one job per role and locale with --queue', function () {
    Queue::fake();

    $this->artisan('user-manual:pdf', ['--queue' => true])->assertSuccessful();

    Queue::assertPushed(GenerateFullManualPdf::class, 3 * count(config('user-manual.locales')));
});

it('applies the queue config to dispatched jobs', function () {
    config([
        'user-manual.pdf.queue.connection' => 'redis',
        'user-manual.pdf.queue.queue' => 'pdfs',
        'user-manual.pdf.queue.timeout' => 120,
    ]);
    Queue::fake();

    UserManual::regenerateRole('basic');

    Queue::assertPushed(fn (GenerateFullManualPdf $job) => $job->connection === 'redis'
        && $job->queue === 'pdfs'
        && $job->timeout === 120);
});

it('deletes everything with user-manual:pdf-clear', function () {
    $this->artisan('user-manual:pdf')->assertSuccessful();
    expect(Storage::disk('local')->allFiles())->not->toBe([]);

    $this->artisan('user-manual:pdf-clear')->assertSuccessful();

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('stores pdfs on the configured disk and directory', function () {
    Storage::fake('pdfs');
    config(['user-manual.pdf.disk' => 'pdfs', 'user-manual.pdf.directory' => 'manuals']);

    $this->artisan('user-manual:pdf', ['--role' => ['basic'], '--locale' => ['en']])->assertSuccessful();

    expect(Storage::disk('pdfs')->exists('manuals/default/1.0/en/manifest.json'))->toBeTrue()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('fires events for generated and failed pdfs', function () {
    Event::fake([FullPdfGenerated::class, FullPdfGenerationFailed::class]);

    app(FullPdfGenerator::class)->generate('default', 'en', 'basic');
    (new GenerateFullManualPdf('default', 'en', 'basic'))->failed(new RuntimeException('boom'));

    Event::assertDispatched(FullPdfGenerated::class, fn ($event) => $event->roleKey === 'basic' && ! $event->reused);
    Event::assertDispatched(FullPdfGenerationFailed::class, fn ($event) => $event->exception?->getMessage() === 'boom');
});

it('rejects a job class that does not extend the package job', function () {
    config(['user-manual.pdf.queue.job' => stdClass::class]);

    UserManual::regenerateRole('basic');
})->throws(InvalidArgumentException::class);

function holdLock(string $roleKey, string $locale = 'en'): void
{
    $job = new GenerateFullManualPdf('default', $locale, $roleKey);
    Cache::lock('laravel_unique_job:'.GenerateFullManualPdf::class.$job->uniqueId(), 3600)->get();
}

it('skips dispatch while a lock is held and releases it with --unlock', function () {
    Queue::fake();
    holdLock('basic');

    UserManual::regenerateRole('basic');
    Queue::assertNotPushed(fn (GenerateFullManualPdf $job) => $job->locale === 'en');

    $this->artisan('user-manual:pdf', ['--unlock' => true, '--queue' => true, '--role' => ['basic']])->assertSuccessful();

    Queue::assertPushed(fn (GenerateFullManualPdf $job) => $job->locale === 'en' && $job->roleKey === 'basic');
});

it('releases locks when clearing pdfs', function () {
    Queue::fake();
    holdLock('editor');

    $this->artisan('user-manual:pdf-clear')->assertSuccessful();
    UserManual::regenerateRole('editor');

    Queue::assertPushed(fn (GenerateFullManualPdf $job) => $job->locale === 'en' && $job->roleKey === 'editor');
});

it('keeps the duplicate lock only a little longer than the job may run', function () {
    config(['user-manual.pdf.queue.timeout' => 120]);
    Queue::fake();

    UserManual::regenerateRole('basic');
    Queue::assertPushed(fn (GenerateFullManualPdf $job) => $job->uniqueFor === 180);

    config(['user-manual.pdf.queue.unique_for' => 30]);
    $this->artisan('user-manual:pdf-clear');
    UserManual::regenerateRole('basic');
    Queue::assertPushed(fn (GenerateFullManualPdf $job) => $job->uniqueFor === 30);
});

it('runs sync jobs after the response in web requests', function () {
    Bus::fake();
    config(['queue.default' => 'sync']);

    $app = app();
    $property = new ReflectionProperty($app, 'isRunningInConsole');
    $property->setValue($app, false);

    try {
        UserManual::regenerateRole('basic');
    } finally {
        $property->setValue($app, true);
    }

    Bus::assertDispatchedAfterResponse(GenerateFullManualPdf::class);
});
