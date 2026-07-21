<?php

afterEach(function () {
    $paths = [
        config_path('user-manual.php'),
        resource_path('views/vendor/user-manual/show.blade.php'),
        resource_path('views/vendor/user-manual/partials/nav.blade.php'),
        lang_path('vendor/user-manual/en/messages.php'),
        lang_path('vendor/user-manual/bn/messages.php'),
        public_path('vendor/user-manual/css/user-manual.css'),
        public_path('vendor/user-manual/js/user-manual.js'),
        resource_path('user-manual/1.0/en/navigation.md'),
        resource_path('user-manual/1.0/en/introduction.md'),
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
});

it('rejects path traversal attempts in the page slug', function () {
    $this->actingAs($this->makeAuthenticatable());

    $this->get('/user-manual/en/../../etc/passwd')->assertNotFound();
    $this->get('/user-manual/en/..%2F..%2Fetc%2Fpasswd')->assertNotFound();
    $this->get('/user-manual/en/foo/bar')->assertNotFound();
    $this->get('/user-manual/en/..%2f..%2fetc%2fpasswd')->assertNotFound();
});

it('publishes package config to the application config path', function () {
    $destination = config_path('user-manual.php');

    if (is_file($destination)) {
        unlink($destination);
    }

    $this->artisan('vendor:publish', ['--tag' => 'user-manual-config'])
        ->assertSuccessful();

    expect($destination)->toBeFile();
});

it('publishes package views to the vendor views directory', function () {
    $destination = resource_path('views/vendor/user-manual/show.blade.php');

    if (is_file($destination)) {
        unlink($destination);
    }

    $this->artisan('vendor:publish', ['--tag' => 'user-manual-views'])
        ->assertSuccessful();

    expect($destination)->toBeFile();
    expect(resource_path('views/vendor/user-manual/partials/nav.blade.php'))->toBeFile();
});

it('publishes package translations to the vendor lang directory', function () {
    $destination = lang_path('vendor/user-manual/en/messages.php');

    if (is_file($destination)) {
        unlink($destination);
    }

    $this->artisan('vendor:publish', ['--tag' => 'user-manual-lang'])
        ->assertSuccessful();

    expect($destination)->toBeFile();
    expect(lang_path('vendor/user-manual/bn/messages.php'))->toBeFile();
});

it('publishes package assets to the public vendor directory', function () {
    $css = public_path('vendor/user-manual/css/user-manual.css');
    $js = public_path('vendor/user-manual/js/user-manual.js');

    foreach ([$css, $js] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    $this->artisan('vendor:publish', ['--tag' => 'user-manual-assets'])
        ->assertSuccessful();

    expect($css)->toBeFile();
    expect($js)->toBeFile();
});

it('publishes package docs stubs to the application docs directory', function () {
    $nav = resource_path('user-manual/1.0/en/navigation.md');
    $intro = resource_path('user-manual/1.0/en/introduction.md');

    $this->artisan('vendor:publish', ['--tag' => 'user-manual-docs'])
        ->assertSuccessful();

    expect($nav)->toBeFile();
    expect($intro)->toBeFile();
});

it('publishes all package resources with the combined tag', function () {
    $paths = [
        config_path('user-manual.php'),
        resource_path('views/vendor/user-manual/show.blade.php'),
        lang_path('vendor/user-manual/en/messages.php'),
        public_path('vendor/user-manual/css/user-manual.css'),
        public_path('vendor/user-manual/js/user-manual.js'),
        resource_path('user-manual/1.0/en/navigation.md'),
        resource_path('user-manual/1.0/en/introduction.md'),
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }

    $this->artisan('vendor:publish', ['--tag' => 'user-manual'])
        ->assertSuccessful();

    foreach ($paths as $path) {
        expect($path)->toBeFile();
    }
});
