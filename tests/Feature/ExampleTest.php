<?php

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(302);
    $response->assertRedirect(config('app.frontend_url'));
});
