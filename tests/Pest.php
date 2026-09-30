<?php

use MuhammadMahediHasan\UserManual\Tests\DomainManualsTestCase;
use MuhammadMahediHasan\UserManual\Tests\PrefixManualsTestCase;
use MuhammadMahediHasan\UserManual\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');
uses(PrefixManualsTestCase::class)->in('Manuals/Prefix');
uses(DomainManualsTestCase::class)->in('Manuals/Domain');
