<?php

namespace Tests\Feature;

use App\Models\MaintenancePhotoUploadToken;
use App\Models\MaintenancePhoto;
use App\Models\MaintenanceRecord;
use App\Services\AuditLogService;
use App\Services\MaintenancePhotoService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MaintenancePhotoTest extends TestCase
{
    public function test_photo_url_uses_the_public_storage_path_on_the_current_host(): void
    {
        $photo = new MaintenancePhoto(['file_path' => 'maintenance/1/64/example.jpg']);

        $this->assertSame('/storage/maintenance/1/64/example.jpg', $photo->url);
    }

    public function test_photo_pdf_path_uses_local_public_disk_and_handles_missing_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('maintenance/1/64/example.jpg', 'image-content');

        $available = new MaintenancePhoto(['file_path' => 'maintenance/1/64/example.jpg']);
        $missing = new MaintenancePhoto(['file_path' => 'maintenance/1/64/missing.jpg']);

        $this->assertSame(Storage::disk('public')->path($available->file_path), $available->pdf_path);
        $this->assertNull($missing->pdf_path);
    }

    public function test_order_pdf_loads_and_renders_photos_without_public_urls(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MaintenanceController.php'));
        $view = file_get_contents(resource_path('views/vehicle/pdf/maintenance-order.blade.php'));

        $this->assertStringContainsString("'photos' => fn (\$query) => \$query->oldest('created_at')", $controller);
        $this->assertStringContainsString('Registros fotográficos', $view);
        $this->assertStringContainsString('$photo->pdf_path', $view);
        $this->assertStringContainsString('Nenhuma foto anexada.', $view);
        $this->assertStringNotContainsString('$photo->url', $view);
    }

    public function test_photo_routes_are_registered_with_expected_auth_boundaries(): void
    {
        $routes = app('router')->getRoutes();
        $this->assertContains('auth', $routes->getByName('vehicles.maintenance.photos.store')->gatherMiddleware());
        $this->assertNotContains('auth', $routes->getByName('public.maintenance-photos.show')->gatherMiddleware());
        $this->assertContains('DELETE', $routes->getByName('vehicles.maintenance.photos.destroy')->methods());
    }

    public function test_photo_permissions_are_enabled_for_supervisor_by_default(): void
    {
        $permissions = config('chm_permissions.groups.maintenance.permissions');
        $this->assertTrue($permissions['maintenance.upload_photos']['default']['supervisor']);
        $this->assertTrue($permissions['maintenance.delete_photos']['default']['supervisor']);
        $this->assertTrue($permissions['maintenance.generate_photo_qr']['default']['supervisor']);
    }

    public function test_expired_and_revoked_tokens_cannot_receive_uploads(): void
    {
        $maintenance = new MaintenanceRecord(['workflow_status' => 'open']);
        $expired = new MaintenancePhotoUploadToken(['expires_at' => now()->subMinute()]);
        $expired->setRelation('maintenanceRecord', $maintenance);
        $revoked = new MaintenancePhotoUploadToken(['expires_at' => now()->addMinute(), 'revoked_at' => now()]);
        $revoked->setRelation('maintenanceRecord', $maintenance);
        $this->assertFalse($expired->canReceiveUploads());
        $this->assertFalse($revoked->canReceiveUploads());
    }

    public function test_closing_is_blocked_below_two_photos_and_allowed_at_two(): void
    {
        $audit = Mockery::mock(AuditLogService::class);
        $audit->shouldReceive('record')->once();
        $this->app->instance(AuditLogService::class, $audit);
        $service = app(MaintenancePhotoService::class);

        try { $service->ensureCanClose($this->maintenanceWithPhotoCount(1)); $this->fail('Expected validation failure.'); }
        catch (ValidationException $e) { $this->assertSame('Envie pelo menos 2 fotos da manutenção antes de encerrar a ordem.', $e->errors()['maintenance'][0]); }

        $service->ensureCanClose($this->maintenanceWithPhotoCount(2));
        $this->addToAssertionCount(1);
    }

    public function test_global_limit_blocks_the_whole_batch_and_reports_remaining_capacity(): void
    {
        $service = app(MaintenancePhotoService::class);

        try {
            $service->ensureCanReceivePhotos($this->maintenanceWithPhotoCount(19), 2);
            $this->fail('Expected validation failure.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Esta ordem permite no máximo 20 fotos. Restam apenas 1 envio(s).',
                $e->errors()['photos'][0]
            );
        }

        $service->ensureCanReceivePhotos($this->maintenanceWithPhotoCount(19), 1);
        $this->addToAssertionCount(1);
    }

    public function test_both_authenticated_and_public_uploads_validate_global_capacity(): void
    {
        $authenticated = file_get_contents(app_path('Http/Controllers/MaintenancePhotoController.php'));
        $public = file_get_contents(app_path('Http/Controllers/PublicMaintenancePhotoController.php'));

        $this->assertStringContainsString('ensureCanReceivePhotos($maintenance, count($data[\'photos\']))', $authenticated);
        $this->assertStringContainsString('ensureCanReceivePhotos($uploadToken->maintenanceRecord, count($data[\'photos\']))', $public);
    }

    public function test_token_max_uploads_remains_ten(): void
    {
        $service = file_get_contents(app_path('Services/MaintenancePhotoService.php'));
        $this->assertStringContainsString("'expires_at' => now()->addMinutes(30), 'max_uploads' => 10", $service);
        $this->assertStringContainsString('$token->max_uploads - $token->photos()->count()', $service);
    }

    public function test_public_upload_preserves_success_when_redirected_token_cannot_receive_more(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/PublicMaintenancePhotoController.php'));
        $completed = file_get_contents(resource_path('views/maintenance-photos/completed.blade.php'));

        $this->assertStringContainsString("session()->has('photo_upload_result')", $controller);
        $this->assertStringContainsString("'success' => 'Fotos enviadas com sucesso.'", $controller);
        $this->assertStringContainsString('Fotos enviadas com sucesso', $completed);
        $this->assertStringNotContainsString('Link indisponível', $completed);
    }

    public function test_public_token_states_have_distinct_messages(): void
    {
        $service = file_get_contents(app_path('Services/MaintenancePhotoService.php'));

        $this->assertStringContainsString('Este link expirou.', $service);
        $this->assertStringContainsString('Este link foi revogado.', $service);
        $this->assertStringContainsString('Esta ordem não está mais disponível', $service);
        $this->assertStringContainsString('Este link permite mais ', $service);
    }

    public function test_used_at_is_informational_and_does_not_invalidate_token(): void
    {
        $model = file_get_contents(app_path('Models/MaintenancePhotoUploadToken.php'));
        $service = file_get_contents(app_path('Services/MaintenancePhotoService.php'));

        $this->assertStringNotContainsString('used_at !== null', $model);
        $this->assertStringContainsString("forceFill(['used_at' => now()])", $service);
    }

    private function maintenanceWithPhotoCount(int $count): MaintenanceRecord
    {
        return new class($count) extends MaintenanceRecord {
            public function __construct(private int $photoCount = 0) { parent::__construct(['tenant_id' => 1, 'vehicle_id' => 1, 'workflow_status' => 'open']); $this->id = 1; $this->setRelation('vehicle', null); }
            public function photos() { return new class($this->photoCount) { public function __construct(private int $count) {} public function count(): int { return $this->count; } }; }
        };
    }
}
