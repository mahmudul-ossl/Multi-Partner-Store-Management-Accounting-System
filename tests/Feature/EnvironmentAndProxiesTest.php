<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\TrustedProxies;
use Dotenv\Dotenv;
use Dotenv\Repository\Adapter\ArrayAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

class EnvironmentAndProxiesTest extends FeatureTestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_example_env_keeps_hash_characters_inside_quoted_seed_passwords(): void
    {
        $repository = RepositoryBuilder::createWithNoAdapters()
            ->addAdapter(ArrayAdapter::class)
            ->make();

        Dotenv::create($repository, base_path(), '.env.example')->load();

        $this->assertSame('SuperAdmin#2026', $repository->get('SEED_SUPER_ADMIN_PASSWORD'));
        $this->assertSame('Partner#2026', $repository->get('SEED_DEMO_PASSWORD'));
        $this->assertSame('System Administrator', $repository->get('SEED_SUPER_ADMIN_NAME'));
        $this->assertSame('MP Store', $repository->get('APP_NAME'));
    }

    public function test_forwarded_https_is_honored_when_every_proxy_is_trusted(): void
    {
        $this->useProxies('*');

        $response = $this->forwardedHttps('127.0.0.1');

        $this->assertTrue($response->baseRequest->isSecure());
        $this->assertStringStartsWith('https://', asset('build/manifest.json'));
    }

    public function test_forwarded_https_is_ignored_for_an_unlisted_proxy(): void
    {
        $this->useProxies('10.1.1.1');

        $response = $this->forwardedHttps('127.0.0.1');

        $this->assertFalse($response->baseRequest->isSecure());
        $this->assertStringStartsWith('http://', asset('build/manifest.json'));
    }

    public function test_forwarded_https_is_ignored_when_trusted_proxies_are_empty(): void
    {
        $this->useProxies('');

        $response = $this->forwardedHttps('127.0.0.1');

        $this->assertFalse($response->baseRequest->isSecure());
        $this->assertStringStartsWith('http://', asset('build/manifest.json'));
    }

    public function test_a_comma_separated_proxy_list_trusts_only_those_addresses(): void
    {
        $this->useProxies('10.1.1.1, 127.0.0.1');

        $trusted = $this->forwardedHttps('127.0.0.1');
        $this->assertTrue($trusted->baseRequest->isSecure());

        $this->useProxies('10.1.1.1, 127.0.0.1');

        $outsider = $this->forwardedHttps('192.0.2.10');
        $this->assertFalse($outsider->baseRequest->isSecure());
    }

    private function forwardedHttps(string $remoteAddress): TestResponse
    {
        return $this->withServerVariables([
            'REMOTE_ADDR' => $remoteAddress,
        ])->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://localhost/login');
    }

    private function useProxies(string $proxies): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        TrustProxies::flushState();
        config(['mpstore.trusted_proxies' => $proxies]);
        TrustedProxies::apply();
    }
}
