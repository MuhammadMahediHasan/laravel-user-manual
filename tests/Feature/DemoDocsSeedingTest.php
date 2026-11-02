<?php

use Illuminate\Support\Facades\File;
use MuhammadMahediHasan\UserManual\UserManualServiceProvider;

function seedDemoDocs(): void
{
    $provider = new UserManualServiceProvider(app());

    (fn () => $this->ensureDemoDocsExist())->call($provider);
}

beforeEach(function () {
    $this->demoRoot = sys_get_temp_dir().'/user-manual-demo-'.uniqid();
    config()->set('user-manual.content_path', $this->demoRoot);
    config()->set('user-manual.version', '1.0');
    config()->set('user-manual.default_locale', 'en');
    config()->set('user-manual.manuals', []);
});

afterEach(function () {
    File::deleteDirectory($this->demoRoot);
});

it('seeds demo docs into an empty content directory', function () {
    seedDemoDocs();

    expect("{$this->demoRoot}/1.0/en/introduction.md")->toBeFile();
    expect("{$this->demoRoot}/1.0/en/navigation.md")->toBeFile();
});

it('does not add demo pages to a directory that already has content', function () {
    File::ensureDirectoryExists("{$this->demoRoot}/1.0/en");
    File::put("{$this->demoRoot}/1.0/en/prerequisites.md", "# Prerequisites\n");

    seedDemoDocs();

    expect("{$this->demoRoot}/1.0/en/introduction.md")->not->toBeFile();
});

it('does not seed demo docs when named manuals are configured', function () {
    config()->set('user-manual.manuals', [
        'web' => ['content_path' => $this->demoRoot, 'route_prefix' => 'web/manual'],
    ]);

    seedDemoDocs();

    expect("{$this->demoRoot}/1.0/en/introduction.md")->not->toBeFile();
});
