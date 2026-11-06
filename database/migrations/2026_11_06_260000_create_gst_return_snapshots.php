<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_return_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->char('period', 7);
            $t->enum('type', ['gstr1', 'gstr3b']);
            $t->string('schema_version')->nullable();
            $t->unsignedInteger('version');
            $t->json('payload');
            $t->char('sha256', 64);
            $t->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('generated_at');
            $t->enum('status', ['draft', 'final', 'superseded'])->default('draft');
            $t->timestamps();
            $t->unique(['company_id', 'period', 'type', 'version'], 'gst_return_snapshot_version_unique');
            $t->index(['company_id', 'period', 'type', 'status'], 'gst_return_snapshot_lookup');
        });
        Schema::create('gst_return_filings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->char('period', 7);
            $t->enum('type', ['gstr1', 'gstr3b']);
            $t->foreignId('snapshot_id')->constrained('gst_return_snapshots')->restrictOnDelete();
            $t->string('arn', 80);
            $t->date('filed_on');
            $t->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'period', 'type'], 'gst_return_filing_period_unique');
        });
        $this->immutableFinalSnapshot();
    }

    private function immutableFinalSnapshot(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER gst_return_snapshot_final_immutable BEFORE UPDATE ON gst_return_snapshots WHEN OLD.status='final' AND (NEW.status!='superseded' OR NEW.company_id!=OLD.company_id OR NEW.period!=OLD.period OR NEW.type!=OLD.type OR NEW.schema_version IS NOT OLD.schema_version OR NEW.version!=OLD.version OR NEW.payload!=OLD.payload OR NEW.sha256!=OLD.sha256 OR NEW.generated_by IS NOT OLD.generated_by OR NEW.generated_at!=OLD.generated_at) BEGIN SELECT RAISE(ABORT,'Final GST return snapshot is immutable'); END");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER gst_return_snapshot_final_immutable BEFORE UPDATE ON gst_return_snapshots FOR EACH ROW BEGIN IF OLD.status='final' AND (NEW.status!='superseded' OR NOT(NEW.company_id<=>OLD.company_id) OR NOT(NEW.period<=>OLD.period) OR NOT(NEW.type<=>OLD.type) OR NOT(NEW.schema_version<=>OLD.schema_version) OR NOT(NEW.version<=>OLD.version) OR NOT(NEW.payload<=>OLD.payload) OR NOT(NEW.sha256<=>OLD.sha256) OR NOT(NEW.generated_by<=>OLD.generated_by) OR NOT(NEW.generated_at<=>OLD.generated_at)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Final GST return snapshot is immutable'; END IF; END");
        } else {
            throw new RuntimeException('GST return snapshots require MySQL or SQLite.');
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS gst_return_snapshot_final_immutable');
        Schema::dropIfExists('gst_return_filings');
        Schema::dropIfExists('gst_return_snapshots');
    }
};
