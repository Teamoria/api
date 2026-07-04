<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('api.key', 'test-api-key');
});

it('returns clear validation errors using a consistent response shape', function () {
    $this->postJson('/api/v1/auth/login', [], apiErrorHeaders())
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'message' => 'Some fields contain invalid values. Please review the errors and try again.',
            'error_code' => 'VALIDATION_ERROR',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'error_code',
            'data' => ['email', 'password'],
        ]);
});

it('explains when the api key header is missing', function () {
    $this->postJson('/api/v1/auth/login')
        ->assertUnauthorized()
        ->assertExactJson([
            'success' => false,
            'message' => 'The x-api-key header is required.',
            'error_code' => 'MISSING_API_KEY',
            'data' => [],
        ]);
});

it('explains when the api key is invalid', function () {
    $this->postJson('/api/v1/auth/login', [], [
        'x-api-key' => 'incorrect-key',
    ])
        ->assertUnauthorized()
        ->assertExactJson([
            'success' => false,
            'message' => 'The provided API key is invalid.',
            'error_code' => 'INVALID_API_KEY',
            'data' => [],
        ]);
});

it('returns a clear message for an unknown api endpoint', function () {
    $this->getJson('/api/v1/missing-endpoint', apiErrorHeaders())
        ->assertNotFound()
        ->assertExactJson([
            'success' => false,
            'message' => 'The requested API endpoint does not exist.',
            'error_code' => 'NOT_FOUND',
            'data' => [],
        ]);
});

it('preserves clear not found messages raised by the application', function () {
    Route::get('/api/testing/missing-file', function () {
        abort(404, 'The stored file was not found.');
    });

    $this->getJson('/api/testing/missing-file')
        ->assertNotFound()
        ->assertExactJson([
            'success' => false,
            'message' => 'The stored file was not found.',
            'error_code' => 'NOT_FOUND',
            'data' => [],
        ]);
});

it('returns a clear method not allowed error', function () {
    Route::get('/api/testing/read-only', fn () => response()->noContent());

    $this->postJson('/api/testing/read-only')
        ->assertMethodNotAllowed()
        ->assertExactJson([
            'success' => false,
            'message' => 'The POST method is not supported for this endpoint.',
            'error_code' => 'METHOD_NOT_ALLOWED',
            'data' => [],
        ]);
});

it('does not expose internal exception details to api consumers', function () {
    config()->set('app.debug', false);

    Route::get('/api/testing/unexpected-error', function () {
        throw new RuntimeException('Sensitive database connection details.');
    });

    $this->getJson('/api/testing/unexpected-error')
        ->assertInternalServerError()
        ->assertExactJson([
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again later.',
            'error_code' => 'INTERNAL_ERROR',
            'data' => [],
        ]);
});

/** @return array<string, string> */
function apiErrorHeaders(): array
{
    return ['x-api-key' => 'test-api-key'];
}
