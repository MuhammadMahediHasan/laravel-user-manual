<?php

use Mpdf\Mpdf;
use MuhammadMahediHasan\UserManual\Services\PdfGeneratorService;

it('exports single page as pdf for authenticated users', function () {
    $response = $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'introduction']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toContain('%PDF-');
});

it('returns forbidden when exporting a restricted page as pdf without permission', function () {
    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'material']))
        ->assertForbidden();
});

it('exports restricted page as pdf when permission is granted', function () {
    $response = $this->actingAs($this->makeAuthenticatable(['material_access']))
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'material']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toContain('%PDF-');
});

it('exports full manual as pdf for authenticated users', function () {
    $response = $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toContain('%PDF-');
});

it('returns 404 when pdf export is disabled in config', function () {
    config(['user-manual.pdf.enabled' => false]);

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.page', ['locale' => 'en', 'page' => 'introduction']))
        ->assertNotFound();

    $this->actingAs($this->makeAuthenticatable())
        ->get(route('user-manual.pdf.full', ['locale' => 'en']))
        ->assertNotFound();
});

it('configures custom fonts in mpdf instance', function () {
    config([
        'user-manual.pdf.fonts.font_dirs' => [__DIR__.'/../fixtures'],
        'user-manual.pdf.fonts.font_data' => [
            'customfont' => [
                'R' => 'DejaVuSansCondensed.ttf',
            ],
        ],
    ]);

    $service = app(PdfGeneratorService::class);
    $mpdf = $service->createMpdfInstance();

    expect($mpdf)->toBeInstanceOf(Mpdf::class);
});
