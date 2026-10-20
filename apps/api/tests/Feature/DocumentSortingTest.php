<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_resource_returns_status_as_title_case_label()
    {
        $user = User::factory()->create(['role' => 'manager']);
        $this->actingAs($user);

        Document::factory()->pending()->create();
        Document::factory()->verified()->create();
        Document::factory()->rejected()->create();

        $response = $this->getJson('/api/documents');

        $response->assertStatus(200);

        // The API sends the display label (Title Case), not the raw enum value.
        $this->assertEqualsCanonicalizing(
            ['Menunggu Verifikasi', 'Terverifikasi', 'Tidak Terverifikasi'],
            collect($response->json('data'))->pluck('status')->all()
        );
    }

    public function test_documents_can_be_sorted_by_tahun_lulus_asc()
    {
        $user = User::factory()->create(['role' => 'manager']);
        $this->actingAs($user);

        Document::factory()->create(['document_type' => 'ijazah', 'tahun_lulus' => '2020', 'created_at' => now()->subDays(2)]);
        Document::factory()->create(['document_type' => 'ijazah', 'tahun_lulus' => '2022', 'created_at' => now()->subDays(1)]);
        Document::factory()->create(['document_type' => 'ijazah', 'tahun_lulus' => '2021', 'created_at' => now()]);

        $response = $this->getJson('/api/documents?sort_by=tahun_lulus&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('2020', $data[0]['tahun_lulus']);
        $this->assertEquals('2021', $data[1]['tahun_lulus']);
        $this->assertEquals('2022', $data[2]['tahun_lulus']);
    }

    public function test_documents_can_be_sorted_by_tahun_lulus_desc()
    {
        $user = User::factory()->create(['role' => 'manager']);
        $this->actingAs($user);

        Document::factory()->create(['document_type' => 'ijazah', 'tahun_lulus' => '2020', 'created_at' => now()->subDays(2)]);
        Document::factory()->create(['document_type' => 'ijazah', 'tahun_lulus' => '2022', 'created_at' => now()->subDays(1)]);
        Document::factory()->create(['document_type' => 'ijazah', 'tahun_lulus' => '2021', 'created_at' => now()]);

        $response = $this->getJson('/api/documents?sort_by=tahun_lulus&sort_direction=desc');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('2022', $data[0]['tahun_lulus']);
        $this->assertEquals('2021', $data[1]['tahun_lulus']);
        $this->assertEquals('2020', $data[2]['tahun_lulus']);
    }

    public function test_invalid_sort_column_defaults_to_created_at()
    {
        $user = User::factory()->create(['role' => 'manager']);
        $this->actingAs($user);

        Document::factory()->create(['created_at' => now()->subDays(2)]);
        Document::factory()->create(['created_at' => now()]); // Most recent

        $response = $this->getJson('/api/documents?sort_by=invalid_column&sort_direction=desc');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should sort by created_at desc (default)
        $this->assertTrue($data[0]['created_at'] > $data[1]['created_at']);
    }
}
