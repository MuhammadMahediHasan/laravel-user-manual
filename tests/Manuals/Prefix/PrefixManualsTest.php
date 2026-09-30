<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

it('serves each prefix manual in english and bangla', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get('/manual/en/introduction')
        ->assertOk()
        ->assertSee('Staff introduction', false)
        ->assertSee('/manual/bn/introduction', false)
        ->assertSee('/manual/en/introduction/pdf', false)
        ->assertDontSee('Partner introduction', false);

    $this->actingAs($this->makeAuthenticatable())
        ->get('/manual/bn/introduction')
        ->assertOk()
        ->assertSee('স্টাফ ভূমিকা', false)
        ->assertDontSee('পার্টনার ভূমিকা', false);

    $this->actingAs($this->makeAuthenticatable(), 'partner')
        ->get('/partner/manual/en/introduction')
        ->assertOk()
        ->assertSee('Partner introduction', false)
        ->assertSee('/partner/manual/bn/introduction', false)
        ->assertDontSee('Staff introduction', false);

    $this->actingAs($this->makeAuthenticatable(), 'partner')
        ->get('/partner/manual/bn/introduction')
        ->assertOk()
        ->assertSee('পার্টনার ভূমিকা', false);
});

it('redirects a prefix manual root to its default locale page', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get('/manual')
        ->assertRedirect('/manual/en/introduction');
});

it('uses each manual permission map', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get('/manual/en/material')
        ->assertForbidden();

    $this->actingAs($this->makeAuthenticatable(), 'partner')
        ->get('/partner/manual/en/material')
        ->assertOk()
        ->assertSee('Partner material', false);
});

it('keeps rendered cache keys separate for each manual', function () {
    $staffFile = $this->staffRoot.'/1.0/en/introduction.md';
    $partnerFile = $this->partnerRoot.'/1.0/en/introduction.md';

    $this->actingAs($this->makeAuthenticatable())
        ->get('/manual/en/introduction')
        ->assertOk();

    $this->actingAs($this->makeAuthenticatable(), 'partner')
        ->get('/partner/manual/en/introduction')
        ->assertOk();

    $staffKey = 'user-manual-test.staff.1.0.en.introduction.'.File::lastModified($staffFile);
    $partnerKey = 'user-manual-test.partner.1.0.en.introduction.'.File::lastModified($partnerFile);

    expect(Cache::has($staffKey))->toBeTrue()
        ->and(Cache::has($partnerKey))->toBeTrue();

    $this->artisan('user-manual:clear-cache')
        ->assertSuccessful();

    expect(Cache::has($staffKey))->toBeFalse()
        ->and(Cache::has($partnerKey))->toBeFalse();
});

it('rejects an unknown manual name on the cache command', function () {
    $this->artisan('user-manual:cache', ['manual' => 'missing'])
        ->assertFailed();
});
