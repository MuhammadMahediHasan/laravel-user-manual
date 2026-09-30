<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

it('serves the same path on different domains from different manuals', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get('http://abc.com/user-manual/en/introduction')
        ->assertOk()
        ->assertSee('Portal introduction', false)
        ->assertSee('http://abc.com/user-manual/bn/introduction', false)
        ->assertDontSee('Admin introduction', false);

    $this->actingAs($this->makeAuthenticatable())
        ->get('http://abc.com/user-manual/bn/introduction')
        ->assertOk()
        ->assertSee('পোর্টাল ভূমিকা', false);

    $this->actingAs($this->makeAuthenticatable(), 'admin')
        ->get('http://x.abc.com/user-manual/en/introduction')
        ->assertOk()
        ->assertSee('Admin introduction', false)
        ->assertSee('Admin', false)
        ->assertSee('http://x.abc.com/user-manual/bn/introduction', false)
        ->assertDontSee('Portal introduction', false);

    $this->actingAs($this->makeAuthenticatable(), 'admin')
        ->get('http://x.abc.com/user-manual/bn/introduction')
        ->assertOk()
        ->assertSee('অ্যাডমিন ভূমিকা', false);
});

it('does not serve a domain manual on another host', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get('http://example.com/user-manual/en/introduction')
        ->assertNotFound();
});

it('registers domain and middleware from the route group', function () {
    $portal = app('router')->getRoutes()->getByName('portal.user-manual.show');
    $admin = app('router')->getRoutes()->getByName('admin.user-manual.show');

    expect($portal)->not->toBeNull()
        ->and($portal->domain())->toBe('abc.com')
        ->and($portal->gatherMiddleware())->toContain('auth')
        ->and($admin)->not->toBeNull()
        ->and($admin->domain())->toBe('x.abc.com')
        ->and($admin->gatherMiddleware())->toContain('auth:admin');
});

it('uses an explicit cache prefix without suffixing the manual id again', function () {
    $adminFile = $this->adminRoot.'/1.0/en/introduction.md';

    $this->actingAs($this->makeAuthenticatable(), 'admin')
        ->get('http://x.abc.com/user-manual/en/introduction')
        ->assertOk();

    $adminKey = 'user-manual-admin.1.0.en.introduction.'.File::lastModified($adminFile);

    expect(Cache::has($adminKey))->toBeTrue()
        ->and(Cache::has('user-manual-admin.admin.1.0.en.introduction.'.File::lastModified($adminFile)))->toBeFalse();
});
