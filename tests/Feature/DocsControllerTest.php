<?php

use Illuminate\Support\Facades\Cache;

it('redirects guests to login', function () {
    $this->get(route('user-manual.show', ['locale' => 'en', 'page' => 'introduction']))
        ->assertRedirect('/login');
});

it('renders a public docs page for authenticated users', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.show', ['locale' => 'en', 'page' => 'introduction']))
        ->assertOk()
        ->assertSee('Welcome to the user manual', false);
});

it('returns forbidden for restricted pages without permission', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.show', ['locale' => 'en', 'page' => 'material']))
        ->assertForbidden();
});

it('renders restricted pages when permission is granted', function () {
    $this->actingAs($this->makeAuthenticatable(['material_access']))
        ->get(route('user-manual.show', ['locale' => 'en', 'page' => 'material']))
        ->assertOk()
        ->assertSee('Material module documentation', false);
});

it('redirects docs root to default page', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get('/docs')
        ->assertRedirect('/docs/en/introduction');
});

it('returns updated content when markdown file is modified without clearing cache', function () {
    $filePath = __DIR__.'/../fixtures/docs/1.0/en/introduction.md';
    $originalContent = file_get_contents($filePath);

    try {
        $this->actingAs($this->makeAuthenticatable())
            ->get(route('user-manual.show', ['locale' => 'en', 'page' => 'introduction']))
            ->assertOk()
            ->assertSee('Welcome to the user manual', false);

        file_put_contents($filePath, "# Introduction\n\nUpdated content after edit.\n");
        touch($filePath, time() + 5);

        $this->actingAs($this->makeAuthenticatable())
            ->get(route('user-manual.show', ['locale' => 'en', 'page' => 'introduction']))
            ->assertOk()
            ->assertSee('Updated content after edit.', false)
            ->assertDontSee('Welcome to the user manual', false);
    } finally {
        file_put_contents($filePath, $originalContent);
        touch($filePath, time());
    }
});

it('clears cached docs entries', function () {
    $filePath = __DIR__.'/../fixtures/docs/1.0/en/introduction.md';
    $navPath = __DIR__.'/../fixtures/docs/1.0/en/navigation.md';

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.show', ['locale' => 'en', 'page' => 'introduction']))
        ->assertOk();

    expect(Cache::has('user-manual-test.1.0.en.introduction.'.filemtime($filePath)))->toBeTrue();
    expect(Cache::has('user-manual-test.1.0.en.nav.tree.'.filemtime($navPath)))->toBeTrue();

    $this->artisan('user-manual:clear-cache')
        ->assertSuccessful();

    expect(Cache::has('user-manual-test.1.0.en.introduction.'.filemtime($filePath)))->toBeFalse();
    expect(Cache::has('user-manual-test.1.0.en.nav.tree.'.filemtime($navPath)))->toBeFalse();
});

it('returns not found for invalid locales', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get('/docs/fr/introduction')
        ->assertNotFound();
});

it('returns not found for missing pages', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.show', ['locale' => 'en', 'page' => 'missing-page']))
        ->assertNotFound();
});

it('redirects legacy docs urls to default locale', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get('/docs/material')
        ->assertRedirect('/docs/en/material');
});

it('clears cache even when a locale directory is missing', function () {
    config(['user-manual.locales' => ['en', 'bn']]);

    $this->artisan('user-manual:clear-cache')
        ->assertSuccessful()
        ->expectsOutputToContain('Cleared');
});

it('does not set locale when disabled in config', function () {
    config(['user-manual.set_locale_on_visit' => false]);
    app()->setLocale('bn');

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.show', ['locale' => 'en', 'page' => 'introduction']))
        ->assertOk();

    expect(app()->getLocale())->toBe('bn');
});
