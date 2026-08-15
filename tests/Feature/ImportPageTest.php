<?php

use App\Jobs\ImportNotes as ImportNotesJob;
use App\Livewire\Admin\ImportNotes;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('keeps guests and non-admins out and shows the sidebar link to admins only', function () {
    $admin = User::factory()->admin()->create();
    $regularUser = User::factory()->create();

    $this->get(route('admin.import'))->assertRedirect(route('login'));
    $this->actingAs($regularUser)->get(route('admin.import'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.import'))->assertSuccessful();

    $adminHome = $this->actingAs($admin)->get('/');
    $adminHome->assertSuccessful();
    $adminHome->assertSee(route('admin.import'));

    $regularUserHome = $this->actingAs($regularUser)->get('/');
    $regularUserHome->assertSuccessful();
    $regularUserHome->assertDontSee(route('admin.import'));
});

it('previews an upload into an empty install: all new, nothing matched', function () {
    Storage::fake(config('filesystems.default'));
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ImportNotes::class)
        ->set('file', UploadedFile::fake()->createWithContent('export.json', file_get_contents(base_path('tests/fixtures/export-v1.json'))))
        ->assertSee('3 new notes')
        ->assertSee('2 authors')
        ->assertSee('2 teams')
        ->assertDontSee('Already here');
});

it('queues the import on confirm and the notes appear when the job runs', function () {
    Storage::fake(config('filesystems.default'));
    Queue::fake();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ImportNotes::class)
        ->set('file', UploadedFile::fake()->createWithContent('export.json', file_get_contents(base_path('tests/fixtures/export-v1.json'))))
        ->call('import')
        ->assertHasNoErrors();

    expect(Note::withTrashed()->count())->toBe(0);
    Queue::assertPushed(ImportNotesJob::class, function (ImportNotesJob $job): bool {
        // Run it in-process the way a worker would, proving the stored blob is complete.
        $job->handle();

        return $job->createUsers === true && $job->fallbackOwner === null && $job->overwriteUlids === [];
    });
    expect(Note::withTrashed()->count())->toBe(3);
    expect(Note::where('code', 'abq4x')->sole()->title)->toBe('How to install the puppet client on Rocky Linux');
    expect(Storage::disk(config('filesystems.default'))->allFiles('imports'))->toBeEmpty();
});

it('shows drifted matches with a skip default and passes overwrite choices to the job', function () {
    Storage::fake(config('filesystems.default'));
    $admin = User::factory()->admin()->create();
    $fixture = file_get_contents(base_path('tests/fixtures/export-v1.json'));
    Note::factory()->create(['ulid' => '01ARZ3NDEKTSV4RRFFQ69G5FA2', 'code' => 'abq4x', 'title' => 'Drifted local title']);
    Queue::fake();

    Livewire::actingAs($admin)
        ->test(ImportNotes::class)
        ->set('file', UploadedFile::fake()->createWithContent('export.json', $fixture))
        ->assertSee('Changed since the file was made')
        ->assertSee('Drifted local title')
        ->assertSee('How to install the puppet client on Rocky Linux')
        ->assertSee('title')
        ->assertSet('decisions.01ARZ3NDEKTSV4RRFFQ69G5FA2', 'skip')
        ->set('decisions.01ARZ3NDEKTSV4RRFFQ69G5FA2', 'overwrite')
        ->call('import')
        ->assertHasNoErrors();

    Queue::assertPushed(ImportNotesJob::class, function (ImportNotesJob $job): bool {
        return $job->overwriteUlids === ['01ARZ3NDEKTSV4RRFFQ69G5FA2'];
    });
});

it('silently skips identical matches, reporting only a count and asking nothing', function () {
    Storage::fake(config('filesystems.default'));
    $admin = User::factory()->admin()->create();
    $fixture = file_get_contents(base_path('tests/fixtures/export-v1.json'));
    Storage::disk(config('filesystems.default'))->put('imports/seed.json', $fixture);
    (new ImportNotesJob(config('filesystems.default'), 'imports/seed.json'))->handle();
    Queue::fake();

    Livewire::actingAs($admin)
        ->test(ImportNotes::class)
        ->set('file', UploadedFile::fake()->createWithContent('export.json', $fixture))
        ->assertSee('3 notes are already here and unchanged')
        ->assertDontSee('Changed since the file was made')
        ->assertSet('decisions', [])
        ->call('import')
        ->assertHasNoErrors();

    Queue::assertPushed(ImportNotesJob::class, function (ImportNotesJob $job): bool {
        return $job->overwriteUlids === [];
    });
});

it('shows new notes whose code is taken as re-mint warnings', function () {
    Storage::fake(config('filesystems.default'));
    $admin = User::factory()->admin()->create();
    Note::factory()->create(['code' => 'abq4x', 'title' => 'A local stranger']);

    Livewire::actingAs($admin)
        ->test(ImportNotes::class)
        ->set('file', UploadedFile::fake()->createWithContent('export.json', file_get_contents(base_path('tests/fixtures/export-v1.json'))))
        ->assertSee('Codes that will be re-minted')
        ->assertSee('#abq4x')
        ->assertDontSee('Already here');
});

it('passes the acting admin as fallback owner when author creation is switched off', function () {
    Storage::fake(config('filesystems.default'));
    Queue::fake();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ImportNotes::class)
        ->set('file', UploadedFile::fake()->createWithContent('export.json', file_get_contents(base_path('tests/fixtures/export-v1.json'))))
        ->set('createUsers', false)
        ->call('import');

    Queue::assertPushed(ImportNotesJob::class, function (ImportNotesJob $job) use ($admin): bool {
        return $job->createUsers === false && $job->fallbackOwner?->is($admin);
    });
});

it('rejects a malformed file with an error and stores and queues nothing', function () {
    Storage::fake(config('filesystems.default'));
    Queue::fake();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ImportNotes::class)
        ->set('file', UploadedFile::fake()->createWithContent('export.json', json_encode(['version' => 99, 'notes' => []])))
        ->assertHasErrors('file')
        ->assertDontSee('Run import');

    Livewire::actingAs($admin)
        ->test(ImportNotes::class)
        ->set('file', UploadedFile::fake()->createWithContent('export.json', 'not even json {'))
        ->assertHasErrors('file');

    expect(Storage::disk(config('filesystems.default'))->allFiles('imports'))->toBeEmpty();
    Queue::assertNothingPushed();
});
