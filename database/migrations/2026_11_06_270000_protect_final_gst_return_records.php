<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up():void
    {
        if(!Schema::hasTable('gst_return_snapshots')||!Schema::hasTable('gst_return_filings'))throw new RuntimeException('GST return snapshot tables must exist first.');
        if(DB::getDriverName()==='sqlite'){
            DB::unprepared('DROP TRIGGER IF EXISTS gst_return_snapshot_final_immutable');
            DB::unprepared("CREATE TRIGGER gst_return_snapshot_final_immutable BEFORE UPDATE ON gst_return_snapshots WHEN OLD.status IN ('final','superseded') AND ((OLD.status='superseded' AND NEW.status!='superseded') OR (OLD.status='final' AND NEW.status NOT IN ('final','superseded')) OR NEW.company_id!=OLD.company_id OR NEW.period!=OLD.period OR NEW.type!=OLD.type OR NEW.schema_version IS NOT OLD.schema_version OR NEW.version!=OLD.version OR NEW.payload!=OLD.payload OR NEW.sha256!=OLD.sha256 OR NEW.generated_by IS NOT OLD.generated_by OR NEW.generated_at!=OLD.generated_at) BEGIN SELECT RAISE(ABORT,'Final GST return snapshot is immutable'); END");
            DB::unprepared("CREATE TRIGGER gst_return_snapshot_final_delete BEFORE DELETE ON gst_return_snapshots WHEN OLD.status IN ('final','superseded') BEGIN SELECT RAISE(ABORT,'Final GST return snapshot cannot be deleted'); END");
            DB::unprepared("CREATE TRIGGER gst_return_filing_immutable_u BEFORE UPDATE ON gst_return_filings BEGIN SELECT RAISE(ABORT,'GST return filing record is immutable'); END");
            DB::unprepared("CREATE TRIGGER gst_return_filing_immutable_d BEFORE DELETE ON gst_return_filings BEGIN SELECT RAISE(ABORT,'GST return filing record is immutable'); END");
        } elseif(DB::getDriverName()==='mysql'){
            DB::unprepared('DROP TRIGGER IF EXISTS gst_return_snapshot_final_immutable');
            DB::unprepared("CREATE TRIGGER gst_return_snapshot_final_immutable BEFORE UPDATE ON gst_return_snapshots FOR EACH ROW BEGIN IF OLD.status IN ('final','superseded') AND ((OLD.status='superseded' AND NEW.status!='superseded') OR (OLD.status='final' AND NEW.status NOT IN ('final','superseded')) OR NOT(NEW.company_id<=>OLD.company_id) OR NOT(NEW.period<=>OLD.period) OR NOT(NEW.type<=>OLD.type) OR NOT(NEW.schema_version<=>OLD.schema_version) OR NOT(NEW.version<=>OLD.version) OR NOT(NEW.payload<=>OLD.payload) OR NOT(NEW.sha256<=>OLD.sha256) OR NOT(NEW.generated_by<=>OLD.generated_by) OR NOT(NEW.generated_at<=>OLD.generated_at)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Final GST return snapshot is immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER gst_return_snapshot_final_delete BEFORE DELETE ON gst_return_snapshots FOR EACH ROW BEGIN IF OLD.status IN ('final','superseded') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Final GST return snapshot cannot be deleted'; END IF; END");
            DB::unprepared("CREATE TRIGGER gst_return_filing_immutable_u BEFORE UPDATE ON gst_return_filings FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='GST return filing record is immutable'; END");
            DB::unprepared("CREATE TRIGGER gst_return_filing_immutable_d BEFORE DELETE ON gst_return_filings FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='GST return filing record is immutable'; END");
        } else throw new RuntimeException('GST return locking requires MySQL or SQLite.');
    }
    public function down():void
    {
        foreach(['gst_return_snapshot_final_delete','gst_return_filing_immutable_u','gst_return_filing_immutable_d'] as $trigger)DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
    }
};
