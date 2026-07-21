<?php

it('serves default css and js assets', function () {
    $this->get(route('user-manual.asset', ['path' => 'css/user-manual.css']))
        ->assertOk()
        ->assertHeader('content-type', 'text/css; charset=UTF-8');

    $this->get(route('user-manual.asset', ['path' => 'js/user-manual.js']))
        ->assertOk()
        ->assertHeader('content-type', 'application/javascript; charset=UTF-8');
});

it('renders docs page with package styling hooks and no inline handlers', function () {
    $response = $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.show', ['locale' => 'en', 'page' => 'introduction']));

    $response->assertOk()
        ->assertSee('user-manual__content', false)
        ->assertSee('user-manual.css', false)
        ->assertSee('user-manual.js', false);

    expect($response->getContent())->not->toContain('onclick=');
});
