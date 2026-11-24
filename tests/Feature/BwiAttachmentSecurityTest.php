<?php

namespace Tests\Feature;

use App\Filament\Resources\BerkasResource\Pages\CreateBerkas;
use App\Filament\Resources\BerkasResource\Pages\EditBerkas;
use App\Models\Berkas;
use App\Models\Cabang;
use App\Models\User;
use App\Services\BwiAttachment;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BwiAttachmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $name): Cabang
    {
        return Cabang::create(['nama_cabang' => $name, 'saldo_awal' => 0]);
    }

    private function actor(Cabang $branch, array $permissions = []): User
    {
        $user = User::factory()->create();
        $user->forceFill(['cabang_id' => $branch->id])->save();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        return $user;
    }

    public function test_legacy_identity_document_requires_login_permission_and_branch(): void
    {
        Storage::fake('public');
        $own = $this->branch('Own');
        $other = $this->branch('Other');
        $owner = $this->actor($own);
        $owner->forceFill(['file_ktp' => 'legacy.pdf'])->save();
        Storage::disk('public')->put('legacy.pdf', "%PDF-1.4\n%%EOF");
        $url = BwiAttachment::url('legacy.pdf');

        $this->get($url)->assertRedirect();
        $this->actingAs($this->actor($own))->get($url)->assertNotFound();
        $this->actingAs($this->actor($other, ['view_any_user']))->get($url)->assertNotFound();

        $response = $this->actingAs($this->actor($own, ['view_any_user']))
            ->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('legacy.pdf');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $central = $this->actor($other, ['view_user']);
        $central->assignRole(Role::findOrCreate('monitoring_pusat', 'web'));
        $this->actingAs($central)->get($url)->assertOk();

        // The unprotected historical URL is no longer a valid application route.
        $this->get('/storage/legacy.pdf')->assertNotFound();
    }

    public function test_shared_or_unreferenced_paths_and_wrong_mime_fail_closed(): void
    {
        Storage::fake('public');
        $own = $this->branch('Own');
        $other = $this->branch('Other');
        $actor = $this->actor($own, ['view_user']);
        $actor->forceFill(['file_kk' => 'shared.pdf'])->save();
        $this->actor($other)->forceFill(['file_ktp' => 'shared.pdf'])->save();
        Storage::disk('public')->put('shared.pdf', "%PDF-1.4\n%%EOF");
        Storage::disk('public')->put('orphan.pdf', "%PDF-1.4\n%%EOF");

        $this->actingAs($actor)->get(BwiAttachment::url('shared.pdf'))->assertNotFound();
        $this->get(BwiAttachment::url('orphan.pdf'))->assertNotFound();

        $actor->forceFill(['file_kk' => 'wrong.png'])->save();
        Storage::disk('public')->put('wrong.png', "<?php echo 'not an image';");
        $this->get(BwiAttachment::url('wrong.png'))->assertNotFound();
        $this->assertNull(BwiAttachment::url('../.env'));
        $this->assertFalse(BwiAttachment::validName('file.php'));
        $this->assertFalse(BwiAttachment::validName('file.php/preview.png'));
    }

    public function test_general_berkas_is_global_only_with_its_own_permission(): void
    {
        Storage::fake('public');
        $actor = $this->actor($this->branch('Own'), ['view_any_user']);
        Berkas::create(['nama' => 'General', 'file' => 'general.pdf']);
        Storage::disk('public')->put('general.pdf', "%PDF-1.4\n%%EOF");
        $this->actingAs($actor)->get(BwiAttachment::url('general.pdf'))->assertNotFound();

        $actor->givePermissionTo(Permission::findOrCreate('view_any_berkas', 'web'));
        $this->get(BwiAttachment::url('general.pdf'))->assertOk();
    }

    public function test_filament_create_cannot_attach_an_existing_foreign_filename(): void
    {
        Storage::fake('public');
        $own = $this->branch('Own');
        $foreign = $this->actor($this->branch('Other'));
        $foreign->forceFill(['file_ktp' => 'foreign.pdf'])->save();
        Storage::disk('public')->put('foreign.pdf', "%PDF-1.4\n%%EOF");

        $actor = $this->actor($own, ['view_any_berkas', 'create_berkas']);
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('bwi'));

        Livewire::test(CreateBerkas::class)
            ->set('data.nama', 'Forged')
            ->set('data.file', ['forged' => 'foreign.pdf'])
            ->call('create')
            ->assertHasErrors('data.file');

        $this->assertDatabaseMissing('berkas', ['file' => 'foreign.pdf']);
        Storage::disk('public')->assertExists('foreign.pdf');
    }

    public function test_filament_valid_upload_uses_a_private_generated_filename(): void
    {
        Storage::fake('public');
        $actor = $this->actor($this->branch('Own'), ['view_any_berkas', 'create_berkas']);
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('bwi'));

        Livewire::test(CreateBerkas::class)
            ->set('data.nama', 'Valid')
            ->set('data.file', [UploadedFile::fake()->image('evidence.png')])
            ->call('create')
            ->assertHasNoErrors();

        $record = Berkas::where('nama', 'Valid')->sole();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}\.png$/', $record->file);
        Storage::disk('public')->assertExists($record->file);
        $this->get(BwiAttachment::url($record->file))->assertOk();
    }

    public function test_edit_retains_a_legacy_filename_and_rejects_repointing_it(): void
    {
        Storage::fake('public');
        $own = $this->branch('Own');
        $actor = $this->actor($own, ['view_any_berkas', 'view_berkas', 'update_berkas']);
        $record = Berkas::create(['nama' => 'Original', 'file' => 'old.pdf']);
        Storage::disk('public')->put('old.pdf', "%PDF-1.4\n%%EOF");
        $foreign = $this->actor($this->branch('Other'));
        $foreign->forceFill(['file_ktp' => 'foreign.pdf'])->save();
        Storage::disk('public')->put('foreign.pdf', "%PDF-1.4\n%%EOF");

        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('bwi'));
        Livewire::test(EditBerkas::class, ['record' => $record->id])
            ->set('data.nama', 'Retained')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('old.pdf', $record->fresh()->file);

        Livewire::test(EditBerkas::class, ['record' => $record->id])
            ->set('data.file', ['forged' => 'foreign.pdf'])
            ->call('save')
            ->assertHasErrors('data.file');
        $this->assertSame('old.pdf', $record->fresh()->file);
    }

    public function test_storage_links_are_disabled_and_trusted_proxies_are_loopback_only(): void
    {
        $this->assertSame([], config('filesystems.links'));
        $this->assertFalse(config('filesystems.disks.public.serve'));
        $this->assertSame('private', config('filesystems.disks.public.visibility'));
        $reflection = new \ReflectionClass(\App\Http\Middleware\TrustProxies::class);
        $this->assertSame(['127.0.0.1', '::1'], $reflection->getDefaultProperties()['proxies']);
    }
}
