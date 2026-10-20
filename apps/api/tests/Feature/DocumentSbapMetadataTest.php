<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\Prodi;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression tests for Phase 1: SBAP metadata restriction.
 *
 * Business rule: SBAP may only see metadata of VERIFIED documents,
 * through list and detail endpoints. Statistics returns 403 for SBAP.
 * Request filter status must never bypass the verified-only scope.
 */
class DocumentSbapMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected User $sbap;
    protected User $manager;
    protected User $uploader;
    protected User $qc;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        $this->sbap = User::factory()->create(['role' => 'sbap']);
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->uploader = User::factory()->create(['role' => 'uploader']);
        $this->qc = User::factory()->create(['role' => 'qc']);
    }

    protected function createMixedStatusDocuments(): void
    {
        Document::factory()->verified()->create(['file_name' => 'verified-1.pdf']);
        Document::factory()->verified()->create(['file_name' => 'verified-2.pdf']);
        Document::factory()->pending()->create(['file_name' => 'pending-1.pdf']);
        Document::factory()->rejected()->create(['file_name' => 'rejected-1.pdf']);
    }

    protected function statusesIn(array $data): array
    {
        return collect($data)->pluck('status')->all();
    }

    // ------------------------------------------------------------------
    // NEGATIVE: list endpoint must not leak non-verified metadata
    // ------------------------------------------------------------------

    public function test_sbap_list_only_contains_verified_documents(): void
    {
        $this->createMixedStatusDocuments();

        $response = $this->actingAs($this->sbap, 'sanctum')
            ->getJson('/api/documents');

        $response->assertStatus(200);
        $this->assertEqualsCanonicalizing(
            ['Terverifikasi', 'Terverifikasi'],
            $this->statusesIn($response->json('data'))
        );
        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_sbap_filter_status_from_request_cannot_bypass_verified_only_scope(): void
    {
        $this->createMixedStatusDocuments();

        foreach ([
            'menunggu verifikasi',      // pending enum
            'tidak terverifikasi',      // rejected enum
            'terverifikasi',            // valid enum, still fine
        ] as $statusFilter) {
            $response = $this->actingAs($this->sbap, 'sanctum')
                ->getJson('/api/documents?status='.urlencode($statusFilter));

            $response->assertStatus(200);
            $this->assertEquals(2, $response->json('meta.total'));
            $this->assertEqualsCanonicalizing(
                ['Terverifikasi', 'Terverifikasi'],
                $this->statusesIn($response->json('data'))
            );
        }
    }

    public function test_sbap_search_cannot_leak_pending_or_rejected_metadata(): void
    {
        $this->createMixedStatusDocuments();

        foreach (['pending-1', 'rejected-1'] as $needle) {
            $response = $this->actingAs($this->sbap, 'sanctum')
                ->getJson('/api/documents?search='.urlencode($needle));

            $response->assertStatus(200);
            $this->assertEquals(0, $response->json('meta.total'));
            $this->assertCount(0, $response->json('data'));
        }

        // Search that matches a verified document still works
        $response = $this->actingAs($this->sbap, 'sanctum')
            ->getJson('/api/documents?search='.urlencode('verified-1'));

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('Terverifikasi', $response->json('data.0.status'));
    }

    public function test_sbap_prodi_filter_cannot_leak_non_verified_documents(): void
    {
        $prodi = Prodi::INFORMATIKA->value;

        // Documents that actually MATCH the prodi filter: one verified,
        // one pending, one rejected — so the verified-only restriction is
        // exercised against real matches instead of an empty result set.
        $verified = Document::factory()->verified()->create([
            'file_name' => 'informatika-verified.pdf',
            'prodi' => $prodi,
        ]);
        Document::factory()->pending()->create([
            'file_name' => 'informatika-pending.pdf',
            'prodi' => $prodi,
        ]);
        Document::factory()->rejected()->create([
            'file_name' => 'informatika-rejected.pdf',
            'prodi' => $prodi,
        ]);

        // Control: a verified document on a DIFFERENT prodi proves the
        // prodi filter itself is applied (not silently ignored).
        Document::factory()->verified()->create([
            'file_name' => 'industri-verified.pdf',
            'prodi' => Prodi::INDUSTRI->value,
        ]);

        $response = $this->actingAs($this->sbap, 'sanctum')
            ->getJson('/api/documents?prodi='.urlencode($prodi));

        $response->assertStatus(200);
        // Exactly the verified prodi-matching document — pending/rejected
        // from the same prodi and the other-prodi doc must all be excluded.
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEqualsCanonicalizing(
            ['informatika-verified.pdf'],
            collect($response->json('data'))->pluck('file_name')->all()
        );
        $this->assertEquals('Terverifikasi', $response->json('data.0.status'));
        $this->assertEquals($verified->id, $response->json('data.0.id'));
    }

    public function test_sbap_pagination_and_total_only_count_verified_documents(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Document::factory()->verified()->create(['file_name' => "verified-{$i}.pdf"]);
        }
        Document::factory()->pending()->create(['file_name' => 'pending-x.pdf']);
        Document::factory()->rejected()->create(['file_name' => 'rejected-x.pdf']);

        $response = $this->actingAs($this->sbap, 'sanctum')
            ->getJson('/api/documents?per_page=3&page=2');

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('meta.total'));   // verified only
        $this->assertEquals(2, $response->json('meta.last_page')); // ceil(5/3)
        $this->assertCount(2, $response->json('data'));
        $this->assertEqualsCanonicalizing(
            ['Terverifikasi', 'Terverifikasi'],
            $this->statusesIn($response->json('data'))
        );
    }

    // ------------------------------------------------------------------
    // NEGATIVE: direct ID access
    // ------------------------------------------------------------------

    public function test_sbap_cannot_view_pending_document_detail(): void
    {
        $document = Document::factory()->pending()->create();

        $this->actingAs($this->sbap, 'sanctum')
            ->getJson("/api/documents/{$document->id}")
            ->assertStatus(403);
    }

    public function test_sbap_cannot_view_rejected_document_detail(): void
    {
        $document = Document::factory()->rejected()->create();

        $this->actingAs($this->sbap, 'sanctum')
            ->getJson("/api/documents/{$document->id}")
            ->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // NEGATIVE: statistics blocked for SBAP
    // ------------------------------------------------------------------

    public function test_sbap_cannot_access_statistics(): void
    {
        $this->createMixedStatusDocuments();

        $this->actingAs($this->sbap, 'sanctum')
            ->getJson('/api/documents/statistics')
            ->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // POSITIVE: SBAP can see verified documents everywhere
    // ------------------------------------------------------------------

    public function test_sbap_can_view_verified_document_detail(): void
    {
        $document = Document::factory()->verified()->create();

        $response = $this->actingAs($this->sbap, 'sanctum')
            ->getJson("/api/documents/{$document->id}");

        $response->assertStatus(200);
        $this->assertEquals('Terverifikasi', $response->json('data.status'));
    }

    // ------------------------------------------------------------------
    // REGRESSION: other roles keep their prior behavior
    // ------------------------------------------------------------------

    public function test_manager_still_sees_all_statuses_in_list(): void
    {
        $this->createMixedStatusDocuments();

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/documents');

        $response->assertStatus(200);
        $this->assertEqualsCanonicalizing(
            ['Terverifikasi', 'Terverifikasi', 'Menunggu Verifikasi', 'Tidak Terverifikasi'],
            $this->statusesIn($response->json('data'))
        );
        $this->assertEquals(4, $response->json('meta.total'));
    }

    public function test_manager_still_can_access_statistics(): void
    {
        $this->createMixedStatusDocuments();

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/documents/statistics');

        $response->assertStatus(200);
        $this->assertEquals(4, $response->json('data.total_documents'));
    }

    public function test_qc_still_can_access_statistics(): void
    {
        $this->createMixedStatusDocuments();

        $this->actingAs($this->qc, 'sanctum')
            ->getJson('/api/documents/statistics')
            ->assertStatus(200);
    }

    public function test_uploader_still_sees_own_documents_including_non_verified(): void
    {
        Document::factory()->pending()->create([
            'file_name' => 'mine-pending.pdf',
            'uploaded_by' => $this->uploader->id,
        ]);
        Document::factory()->rejected()->create([
            'file_name' => 'mine-rejected.pdf',
            'uploaded_by' => $this->uploader->id,
        ]);
        Document::factory()->verified()->create([
            'file_name' => 'someone-else-verified.pdf',
            'uploaded_by' => User::factory()->create(['role' => 'uploader'])->id,
        ]);

        $response = $this->actingAs($this->uploader, 'sanctum')
            ->getJson('/api/documents');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.total'));
        $this->assertEqualsCanonicalizing(
            ['Menunggu Verifikasi', 'Tidak Terverifikasi'],
            $this->statusesIn($response->json('data'))
        );
    }

    public function test_uploader_still_can_view_own_pending_document_detail(): void
    {
        $document = Document::factory()->pending()->create([
            'uploaded_by' => $this->uploader->id,
        ]);

        $this->actingAs($this->uploader, 'sanctum')
            ->getJson("/api/documents/{$document->id}")
            ->assertStatus(200);
    }

    public function test_qc_still_sees_all_statuses_in_list(): void
    {
        $this->createMixedStatusDocuments();

        $response = $this->actingAs($this->qc, 'sanctum')
            ->getJson('/api/documents');

        $response->assertStatus(200);
        $this->assertEquals(4, $response->json('meta.total'));
    }

    public function test_sbap_sort_and_pagination_input_cannot_leak_non_verified_documents(): void
    {
        $this->createMixedStatusDocuments();

        // Combination of filters that would return a pending doc if the
        // verified-only scope were dropped (e.g. via sort or date window).
        $response = $this->actingAs($this->sbap, 'sanctum')
            ->getJson('/api/documents?sort_by=created_at&sort_direction=asc&per_page=100&page=1');

        $response->assertStatus(200);
        $this->assertEqualsCanonicalizing(
            ['Terverifikasi', 'Terverifikasi'],
            $this->statusesIn($response->json('data'))
        );
    }
}
