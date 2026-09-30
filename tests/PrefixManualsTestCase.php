<?php

namespace MuhammadMahediHasan\UserManual\Tests;

use Illuminate\Foundation\Application;

abstract class PrefixManualsTestCase extends TestCase
{
    use SeedsManualContent;

    protected string $staffRoot = '';

    protected string $partnerRoot = '';

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->staffRoot = sys_get_temp_dir().'/user-manual-staff-'.uniqid();
        $this->partnerRoot = sys_get_temp_dir().'/user-manual-partner-'.uniqid();
        $this->seedManual($this->staffRoot, 'manual', 'Staff introduction', 'স্টাফ ভূমিকা', 'Staff material');
        $this->seedManual($this->partnerRoot, 'partner/manual', 'Partner introduction', 'পার্টনার ভূমিকা', 'Partner material');

        $app['config']->set('auth.guards.partner', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        $app['config']->set('user-manual.manuals', [
            'staff' => [
                'content_path' => $this->staffRoot,
                'route_prefix' => 'manual',
                'locales' => ['en', 'bn'],
                'middleware' => ['web', 'auth'],
                'permission-mapper' => [
                    'introduction' => '*',
                    'material' => ['material_access'],
                ],
            ],
            'partner' => [
                'content_path' => $this->partnerRoot,
                'route_prefix' => 'partner/manual',
                'locales' => ['en', 'bn'],
                'middleware' => ['web', 'auth:partner'],
                'auth_guards' => ['partner'],
                'permission-mapper' => [
                    'introduction' => '*',
                    'material' => '*',
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteManualRoots($this->staffRoot, $this->partnerRoot);

        parent::tearDown();
    }
}
