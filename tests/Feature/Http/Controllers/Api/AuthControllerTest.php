<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Payload válido para POST /api/register, con los cambios indicados.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
        'device_name' => 'iphone',
        ...$overrides,
    ];
}

describe('register', function () {
    it('creates the user and returns a token for the device', function () {
        $response = $this->postJson('/api/register', registrationPayload());

        $response->assertCreated()
            ->assertJsonPath('user.name', 'Ana Pérez')
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonMissingPath('user.password');

        $user = User::firstWhere('email', 'ana@example.com');
        $token = PersonalAccessToken::findToken($response->json('token'));

        expect($user)->not->toBeNull();
        expect($token)
            ->name->toBe('iphone')
            ->tokenable_id->toBe($user->id);
    });

    it('stores the password hashed', function () {
        $this->postJson('/api/register', registrationPayload());

        $password = User::firstWhere('email', 'ana@example.com')->password;

        expect($password)->not->toBe('secreto123');
        expect(Hash::check('secreto123', $password))->toBeTrue();
    });

    it('does not let the payload set attributes outside the registration fields', function () {
        $this->postJson('/api/register', registrationPayload([
            'email_verified_at' => '2026-01-01 00:00:00',
        ]));

        $this->assertDatabaseHas('users', [
            'email' => 'ana@example.com',
            'email_verified_at' => null,
        ]);
    });

    it('returns 422 when the required fields are missing', function () {
        $response = $this->postJson('/api/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name' => 'The name field is required.',
                'email' => 'The email field is required.',
                'password' => 'The password field is required.',
                'device_name' => 'The device name field is required.',
            ]);
        $this->assertDatabaseCount('users', 0);
    });

    it('returns 422 when a field is invalid', function (array $overrides, string $field, string $message) {
        $response = $this->postJson('/api/register', registrationPayload($overrides));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([$field => $message]);
        $this->assertDatabaseCount('users', 0);
    })->with([
        'name longer than 255 characters' => [
            ['name' => str_repeat('a', 256)],
            'name',
            'The name field must not be greater than 255 characters.',
        ],
        'email with an invalid format' => [
            ['email' => 'no-es-un-correo'],
            'email',
            'The email field must be a valid email address.',
        ],
        'email longer than 255 characters' => [
            ['email' => str_repeat('a', 244).'@example.com'],
            'email',
            'The email field must not be greater than 255 characters.',
        ],
        'password shorter than 8 characters' => [
            ['password' => 'corta12', 'password_confirmation' => 'corta12'],
            'password',
            'The password field must be at least 8 characters.',
        ],
        'password confirmation that does not match' => [
            ['password_confirmation' => 'otra-distinta'],
            'password',
            'The password field confirmation does not match.',
        ],
        'device name longer than 255 characters' => [
            ['device_name' => str_repeat('a', 256)],
            'device_name',
            'The device name field must not be greater than 255 characters.',
        ],
    ]);

    it('returns 422 when the email is already registered', function () {
        User::factory()->create(['email' => 'ana@example.com']);

        $response = $this->postJson('/api/register', registrationPayload(['email' => 'ana@example.com']));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
        $this->assertDatabaseCount('users', 1);
    });
});

describe('login', function () {
    it('returns the user and a new token when the credentials are valid', function () {
        $user = User::factory()->create(['email' => 'ana@example.com', 'password' => 'secreto123']);

        $response = $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'secreto123',
            'device_name' => 'iphone',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password');

        expect(PersonalAccessToken::findToken($response->json('token')))
            ->name->toBe('iphone')
            ->tokenable_id->toBe($user->id);
    });

    it('keeps the tokens of other devices active', function () {
        $user = User::factory()->create(['email' => 'ana@example.com', 'password' => 'secreto123']);
        $laptopToken = $user->createToken('laptop')->accessToken;

        $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'secreto123',
            'device_name' => 'iphone',
        ])->assertOk();

        $this->assertModelExists($laptopToken);
        $this->assertDatabaseCount('personal_access_tokens', 2);
    });

    it('returns 422 with the same message when the credentials are wrong', function (array $credentials) {
        User::factory()->create(['email' => 'ana@example.com', 'password' => 'secreto123']);

        $response = $this->postJson('/api/login', [...$credentials, 'device_name' => 'iphone']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    })->with([
        'wrong password' => [['email' => 'ana@example.com', 'password' => 'incorrecta']],
        'unregistered email' => [['email' => 'nadie@example.com', 'password' => 'secreto123']],
    ]);

    it('returns 422 when the required fields are missing', function () {
        $response = $this->postJson('/api/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'The email field is required.',
                'password' => 'The password field is required.',
                'device_name' => 'The device name field is required.',
            ]);
    });

    it('returns 422 when a field is invalid', function (array $overrides, string $field, string $message) {
        User::factory()->create(['email' => 'ana@example.com', 'password' => 'secreto123']);

        $response = $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'secreto123',
            'device_name' => 'iphone',
            ...$overrides,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([$field => $message]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    })->with([
        'email with an invalid format' => [
            ['email' => 'no-es-un-correo'],
            'email',
            'The email field must be a valid email address.',
        ],
        'device name longer than 255 characters' => [
            ['device_name' => str_repeat('a', 256)],
            'device_name',
            'The device name field must not be greater than 255 characters.',
        ],
    ]);
});

describe('me', function () {
    it('returns the owner of the token without sensitive attributes', function () {
        $user = User::factory()->create();
        $token = $user->createToken('iphone')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
        expect(array_keys($response->json()))->toEqualCanonicalizing([
            'id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at',
        ]);
    });

    it('returns 401 when the token does not exist', function () {
        $response = $this->withToken('1|token-inventado')->getJson('/api/me');

        $response->assertUnauthorized();
    });

    it('returns 401 when the token has been revoked', function () {
        $newToken = User::factory()->create()->createToken('iphone');
        $newToken->accessToken->delete();

        $response = $this->withToken($newToken->plainTextToken)->getJson('/api/me');

        $response->assertUnauthorized();
    });

    it('returns 401 when the token was issued more than 24 hours ago', function () {
        $token = User::factory()->create()->createToken('iphone')->plainTextToken;
        $this->travel(24)->hours();
        $this->travel(1)->seconds();

        $response = $this->withToken($token)->getJson('/api/me');

        $response->assertUnauthorized();
    });

    it('accepts a token issued less than 24 hours ago', function () {
        $token = User::factory()->create()->createToken('iphone')->plainTextToken;
        $this->travel(23)->hours();

        $response = $this->withToken($token)->getJson('/api/me');

        $response->assertOk();
    });
});

describe('logout', function () {
    it('revokes only the token used in the request', function () {
        $user = User::factory()->create();
        $phoneToken = $user->createToken('iphone');
        $laptopToken = $user->createToken('laptop');

        $response = $this->withToken($phoneToken->plainTextToken)->postJson('/api/logout');

        $response->assertNoContent();
        $this->assertModelMissing($phoneToken->accessToken);
        $this->assertModelExists($laptopToken->accessToken);
    });
});

it('returns 401 when no token is provided', function (string $method, string $uri) {
    $response = $this->json($method, $uri);

    $response->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
})->with([
    'GET /api/me' => ['GET', '/api/me'],
    'POST /api/logout' => ['POST', '/api/logout'],
]);

it('returns 429 after 6 attempts in one minute', function (string $uri) {
    foreach (range(1, 6) as $attempt) {
        $this->postJson($uri, [])->assertUnprocessable();
    }

    $response = $this->postJson($uri, []);

    $response->assertTooManyRequests();
})->with([
    'register' => ['/api/register'],
    'login' => ['/api/login'],
]);
