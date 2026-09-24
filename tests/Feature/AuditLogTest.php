<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Services\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\VoidableRecord;

beforeEach(function () {
    Schema::create('voidable_records', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('company_id')->nullable();
        $table->timestamp('voided_at')->nullable();
        $table->unsignedBigInteger('voided_by')->nullable();
        $table->string('void_reason')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('voidable_records');
});

it('records an audit entry for a company', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company->id);

    $subject = DocumentSequence::create([
        'type' => 'invoice', 'prefix' => 'INV-', 'padding' => 4,
    ]);

    app(AuditLogger::class)->log('created', $subject, [], ['type' => 'invoice']);

    expect(AuditLog::where('company_id', $company->id)->where('event', 'created')->count())->toBe(1);
});

it('keeps audit entries immutable', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company->id);

    $subject = DocumentSequence::create([
        'type' => 'invoice', 'prefix' => 'INV-', 'padding' => 4,
    ]);
    app(AuditLogger::class)->log('created', $subject, [], []);

    $log = AuditLog::first();
    $log->event = 'tampered';
    $log->save();
})->throws(RuntimeException::class);

it('voids financial records with a reason instead of deleting them', function () {
    $record = VoidableRecord::create(['company_id' => null]);

    expect(fn () => $record->delete())->toThrow(RuntimeException::class);

    $record->void('Duplicate entry', 1);

    expect($record->fresh()->isVoided())->toBeTrue()
        ->and($record->void_reason)->toBe('Duplicate entry')
        ->and($record->voided_by)->toBe(1);

    $record->delete();

    expect(VoidableRecord::count())->toBe(0)
        ->and(AuditLog::where('event', 'voided')->count())->toBe(1);
});
