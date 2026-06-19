<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guests_are_redirected_to_login(): void
    {
        // Root forwards to the terminal, which guests cannot access.
        $this->get('/')->assertRedirect(route('terminal'));
        $this->get('/terminal')->assertRedirect(route('login'));
        $this->get('/login')->assertOk();
    }
}
