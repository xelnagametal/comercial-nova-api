<?php

use App\Enums\Rol;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

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

    it('returns the roles and permissions of the user', function () {
        usuarioConRol(Rol::Vendedor)->update(['email' => 'ana@example.com', 'password' => 'secreto123']);

        $response = $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'secreto123',
            'device_name' => 'iphone',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.roles', ['vendedor'])
            ->assertJsonPath('user.permisos', [
                'categorias.ver',
                'clientes.crear',
                'clientes.editar',
                'clientes.ver',
                'productos.ver',
            ]);
    });

    it('rejects an inactive or deleted user with the generic message', function (string $estado) {
        $user = User::factory()->create(['email' => 'ana@example.com', 'password' => 'secreto123']);
        match ($estado) {
            'inactivo' => $user->update(['activo' => false]),
            'eliminado' => $user->delete(),
        };

        $response = $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'secreto123',
            'device_name' => 'iphone',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    })->with([
        'inactive' => ['inactivo'],
        'soft deleted' => ['eliminado'],
    ]);

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
            'id', 'name', 'email', 'activo', 'roles', 'permisos', 'created_at', 'updated_at',
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
    'login' => ['/api/login'],
]);
