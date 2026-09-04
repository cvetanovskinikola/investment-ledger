<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_can_be_created(): void
    {
        $this->postJson('/api/clients', ['name' => 'Ana'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ana');

        $this->assertDatabaseHas('clients', ['name' => 'Ana']);
    }

    public function test_a_client_name_is_required(): void
    {
        $this->postJson('/api/clients', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_client_names_must_be_unique(): void
    {
        Client::create(['name' => 'Ana']);

        $this->postJson('/api/clients', ['name' => 'Ana'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_clients_can_be_listed(): void
    {
        Client::create(['name' => 'Ana']);
        Client::create(['name' => 'Marko']);

        $this->getJson('/api/clients')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
