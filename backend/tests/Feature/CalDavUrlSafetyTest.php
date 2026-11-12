<?php

use App\Models\CalDAVAccount;
use App\Models\User;
use App\Services\CalDAVService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->active()->create();
});

function connectCalDav(string $url)
{
    return test()->actingAs(test()->user)->post(route('caldav-accounts.store'), [
        'url' => $url,
        'username' => 'someone',
        'password' => 'secret',
    ]);
}

/**
 * On the hosted service the CalDAV URL is the only place a customer gets the server to open a
 * connection to an address of their choosing. Anything that is not a public host over https
 * is refused before a single request goes out, so the cloud cannot be used to probe its own
 * network or the instance metadata endpoint.
 */
describe('on the hosted service', function () {
    beforeEach(function () {
        config(['settings.is_self_hosted' => false]);

        $this->mock(CalDAVService::class, function ($mock) {
            $mock->shouldNotReceive('checkConnection');
        });
    });

    test('a loopback address is refused', function () {
        connectCalDav('https://127.0.0.1/remote.php/dav')->assertSessionHasErrors('url');
        expect(CalDAVAccount::count())->toBe(0);
    });

    test('the cloud metadata address is refused', function () {
        connectCalDav('https://169.254.169.254/latest/meta-data/')->assertSessionHasErrors('url');
    });

    test('a private network address is refused', function () {
        connectCalDav('https://10.0.0.5/dav')->assertSessionHasErrors('url');
        connectCalDav('https://192.168.1.20/dav')->assertSessionHasErrors('url');
        connectCalDav('https://[::1]/dav')->assertSessionHasErrors('url');
    });

    test('a host name that resolves to a private address is refused', function () {
        connectCalDav('https://localhost/dav')->assertSessionHasErrors('url');
    });

    test('plain http is refused', function () {
        connectCalDav('http://dav.example.com/')->assertSessionHasErrors('url');
    });

    test('a url with embedded credentials is refused', function () {
        connectCalDav('https://user:pass@dav.example.com/')->assertSessionHasErrors('url');
    });
});

describe('self-hosted', function () {
    beforeEach(function () {
        config(['settings.is_self_hosted' => true]);
    });

    test('a calendar server on the local network is still allowed', function () {
        $this->mock(CalDAVService::class, function ($mock) {
            $mock->shouldReceive('checkConnection')->once()->andReturn(['success' => true, 'message' => 'ok']);
        });

        connectCalDav('http://192.168.1.20/remote.php/dav')->assertRedirect(route('dashboard'));
        expect(CalDAVAccount::count())->toBe(1);
    });
});

/**
 * The connection check talks to a server the customer named. Its response must not be
 * relayed back word for word: status lines and transport errors are exactly what turns the
 * check into a port scanner. Wrong credentials are the one thing worth telling apart.
 */
test('a connection failure does not echo the transport error', function () {
    // Nothing listens here; curl fails fast with a "connection refused" that must stay internal.
    $result = app(CalDAVService::class)->checkConnection('http://127.0.0.1:9', 'u', 'p');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->not->toContain('refused')
        ->and($result['message'])->not->toContain('127.0.0.1')
        ->and($result['message'])->not->toContain('curl');
});
