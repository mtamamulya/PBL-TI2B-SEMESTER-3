<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $db = $app['db']->connection();
        $memory = $db->getDriverName() === 'sqlite' && $db->getDatabaseName() === ':memory:';
        $localTest = in_array($db->getDriverName(), ['mysql', 'mariadb'], true)
            && in_array($db->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
            && preg_match('/(?:^|_)(?:test(?:ing)?|ci_verifikasi)(?:_|$)/i', $db->getDatabaseName())
            && ! $db->getConfig('unix_socket');

        if (! $memory && ! $localTest) {
            throw new \RuntimeException('Tes dihentikan sebelum migration. Gunakan SQLite :memory: atau database tes MySQL lokal.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
