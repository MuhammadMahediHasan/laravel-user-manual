<?php

namespace MuhammadMahediHasan\UserManual\Tests;

use Illuminate\Foundation\Application;

abstract class DomainManualsTestCase extends TestCase
{
    use SeedsManualContent;

    protected string $portalRoot = '';

    protected string $adminRoot = '';

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->portalRoot = sys_get_temp_dir().'/user-manual-portal-'.uniqid();
        $this->adminRoot = sys_get_temp_dir().'/user-manual-admin-'.uniqid();
        $this->seedManual($this->portalRoot, 'user-manual', 'Portal introduction', 'পোর্টাল ভূমিকা', 'Portal material');
        $this->seedManual($this->adminRoot, 'user-manual', 'Admin introduction', 'অ্যাডমিন ভূমিকা', 'Admin material');

        $app['config']->set('user-manual.manuals', [
            'portal' => [
                'content_path' => $this->portalRoot,
                'route_prefix' => 'user-manual',
                'locales' => ['en', 'bn'],
                'middleware' => ['web', 'auth'],
                'route' => [
                    'domain' => 'abc.com',
                ],
            ],
            'admin' => [
                'content_path' => $this->adminRoot,
                'route_prefix' => 'user-manual',
                'locales' => ['en', 'bn'],
                'cache_prefix' => 'user-manual-admin',
                'route_name' => 'admin.user-manual.show',
                'auth_guards' => ['admin'],
                'ui' => [
                    'app_name' => 'Admin',
                ],
                'route' => [
                    'domain' => 'x.abc.com',
                    'middleware' => ['web', 'auth:admin'],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteManualRoots($this->portalRoot, $this->adminRoot);

        parent::tearDown();
    }
}
