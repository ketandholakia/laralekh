<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up():void
    {
        if(!Schema::hasTable('gst_return_snapshots')||!Schema::hasTable('gst_return_filings'))throw new RuntimeException('GST return snapshot tables must exist first.');
        if(DB::getDriverName()==='sqlite'){
            DB::unprepared("CREATE TRIGGER gst_return_snapshot_final_delete BEFORE DELETE ON gst_return_snapshots WHEN OLD.status='final' BEGIN SELECT RAISE(ABORT,'Final GST return snapshot cannot be deleted'); END");
            DB::unprepared("CREATE TRIGGER gst_return_filing_immutable_u BEFORE UPDATE ON gst_return_filings BEGIN SELECT RAISE(ABORT,'GST return filing record is immutable'); END");
            DB::unprepared("CREATE TRIGGER gst_return_filing_immutable_d BEFORE DELETE ON gst_return_filings BEGIN SELECT RAISE(ABORT,'GST return filing record is immutable'); END");
        } elseif(DB::getDriverName()==='mysql'){
            DB::unprepared("CREATE TRIGGER gst_return_snapshot_final_delete BEFORE DELETE ON gst_return_snapshots FOR EACH ROW BEGIN IF OLD.status='final' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Final GST return snapshot cannot be deleted'; END IF; END");
            DB::unprepared("CREATE TRIGGER gst_return_filing_immutable_u BEFORE UPDATE ON gst_return_filings FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='GST return filing record is immutable'; END");
            DB::unprepared("CREATE TRIGGER gst_return_filing_immutable_d BEFORE DELETE ON gst_return_filings FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='GST return filing record is immutable'; END");
        } else throw new RuntimeException('GST return locking requires MySQL or SQLite.');
    }
    public function down():void
    {
        foreach(['gst_return_snapshot_final_delete','gst_return_filing_immutable_u','gst_return_filing_immutable_d'] as $trigger)DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
    }
};
