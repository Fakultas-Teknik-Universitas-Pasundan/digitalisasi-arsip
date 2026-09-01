<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $uploader;
    protected User $qc;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->uploader = User::factory()->create(['role' => 'uploader']);
        $this->qc = User::factory()->create(['role' => 'qc']);
    }

    public function test_manager_can_delete_multiple_rejected_documents(): void
    {
        $this->actingAs($this->manager);

        $doc1 = Document::factory()->rejected()->create();
        $doc2 = Document::factory()->rejected()->create();

        $response = $this->postJson('/api/documents/delete-multiple', [
            'ids' => [$doc1->id, $doc2->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('deleted_count', 2);

        $this->assertSoftDeleted('documents', ['id' => $doc1->id]);
        $this->assertSoftDeleted('documents', ['id' => $doc2->id]);
    }

    public function test_manager_can_download_multiple_documents_as_zip(): void
    {
        $this->actingAs($this->manager);

        $doc1 = Document::factory()->verified()->create([
            'file_path' => 'archives/doc1.pdf',
            'file_name' => 'doc1.pdf',
        ]);
        $doc2 = Document::factory()->verified()->create([
            'file_path' => 'archives/doc2.pdf',
            'file_name' => 'doc2.pdf',
        ]);

        Storage::put($doc1->file_path, 'Content Doc 1');
        Storage::put($doc2->file_path, 'Content Doc 2');

        $response = $this->postJson('/api/documents/download-multiple', [
            'ids' => [$doc1->id, $doc2->id],
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/zip');
    }

    public function test_manager_can_delete_multiple_users(): void
    {
        $this->actingAs($this->manager);

        $user1 = User::factory()->create(['role' => 'uploader']);
        $user2 = User::factory()->create(['role' => 'qc']);

        $response = $this->postJson('/api/users/delete-multiple', [
            'ids' => [$user1->id, $user2->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('deleted_count', 2);

        $this->assertDatabaseMissing('users', ['id' => $user1->id]);
        $this->assertDatabaseMissing('users', ['id' => $user2->id]);
    }

    public function test_manager_cannot_delete_self_in_bulk(): void
    {
        $this->actingAs($this->manager);

        $user1 = User::factory()->create(['role' => 'uploader']);

        $response = $this->postJson('/api/users/delete-multiple', [
            'ids' => [$this->manager->id, $user1->id],
        ]);

        $response->assertStatus(422);

        // Neither should be deleted
        $this->assertDatabaseHas('users', ['id' => $this->manager->id]);
        $this->assertDatabaseHas('users', ['id' => $user1->id]);
    }

    public function test_non_manager_cannot_delete_multiple_users(): void
    {
        $this->actingAs($this->uploader);

        $user1 = User::factory()->create();

        $response = $this->postJson('/api/users/delete-multiple', [
            'ids' => [$user1->id],
        ]);

        $response->assertStatus(403);
    }
}
