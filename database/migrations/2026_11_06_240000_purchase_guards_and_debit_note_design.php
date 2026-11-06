<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    private function money(Blueprint $t,string $name):void { DB::getDriverName()==='sqlite' ? $t->string($name,17)->default('0.00') : $t->decimal($name,15,2)->default('0.00'); }
    private function guard(string $name,string $table,string $event,string $condition):void {
        if(DB::getDriverName()==='sqlite') DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table WHEN $condition BEGIN SELECT RAISE(ABORT, 'Immutable posted purchase document'); END");
        else DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table FOR EACH ROW BEGIN IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable posted purchase document'; END IF; END");
    }
    public function up():void {
        Schema::table('purchase_bill_items',fn(Blueprint $t)=>$t->unique(['company_id','financial_year_id','purchase_bill_id','id'],'purchase_bill_item_identity'));
        foreach(['UPDATE'=>'u','DELETE'=>'d'] as $event=>$suffix) {
            $old="OLD.status = 'posted'"; $this->guard('purchase_bills_immutable_'.$suffix,'purchase_bills',$event,$old);
        }
        foreach(['INSERT'=>'i','UPDATE'=>'u','DELETE'=>'d'] as $event=>$suffix) {
            $rows=$event==='INSERT'?['NEW']:($event==='DELETE'?['OLD']:['OLD','NEW']);
            $conditions=[]; foreach($rows as $r) $conditions[]="EXISTS (SELECT 1 FROM purchase_bills WHERE id = $r.purchase_bill_id AND status = 'posted')";
            $this->guard('purchase_bill_items_immutable_'.$suffix,'purchase_bill_items',$event,implode(' OR ',$conditions));
        }
        Schema::create('purchase_debit_notes',function(Blueprint $t){
            $t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->unsignedBigInteger('financial_year_id');
            $t->foreign(['company_id','financial_year_id'])->references(['company_id','id'])->on('financial_years')->restrictOnDelete();
            $t->unsignedBigInteger('purchase_bill_id');$t->foreign(['company_id','financial_year_id','purchase_bill_id'],'purchase_debit_notes_bill_fk')->references(['company_id','financial_year_id','id'])->on('purchase_bills')->restrictOnDelete();
            $t->unsignedBigInteger('voucher_id')->nullable();$t->foreign(['company_id','financial_year_id','voucher_id'],'purchase_debit_notes_voucher_fk')->references(['company_id','financial_year_id','id'])->on('vouchers')->restrictOnDelete();
            $t->date('date');$t->string('series',10);$t->unsignedBigInteger('number')->nullable();$t->enum('status',['draft','posted'])->default('draft');$t->text('reason');$t->json('supplier_snapshot');
            foreach(['taxable_amount','cgst','sgst','utgst','igst','cess','total'] as $field)$this->money($t,$field);$t->timestamps();
            $t->unique(['company_id','financial_year_id','series','number'],'purchase_debit_notes_number_unique');
            $t->unique(['company_id','financial_year_id','id'],'purchase_debit_notes_tenant_year_id_unique');
        });
        Schema::create('purchase_debit_note_lines',function(Blueprint $t){
            $t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->unsignedBigInteger('financial_year_id');
            $t->foreign(['company_id','financial_year_id'])->references(['company_id','id'])->on('financial_years')->restrictOnDelete();
            $t->unsignedBigInteger('purchase_debit_note_id');$t->foreign(['company_id','financial_year_id','purchase_debit_note_id'],'purchase_debit_note_lines_parent_fk')->references(['company_id','financial_year_id','id'])->on('purchase_debit_notes')->restrictOnDelete();
            $t->unsignedBigInteger('bill_financial_year_id');$t->unsignedBigInteger('purchase_bill_id');$t->unsignedBigInteger('purchase_bill_item_id');
            $t->foreign(['company_id','bill_financial_year_id','purchase_bill_id','purchase_bill_item_id'],'purchase_debit_source_fk')->references(['company_id','financial_year_id','purchase_bill_id','id'])->on('purchase_bill_items')->restrictOnDelete();
            $t->unsignedInteger('position');$t->string('description');$t->decimal('quantity',15,3)->default('0.000');$t->string('itc_treatment',40);
            foreach(['taxable_amount','cgst','sgst','utgst','igst','cess','total'] as $field)$this->money($t,$field);$t->timestamps();
            $t->unique(['purchase_debit_note_id','position'],'purchase_debit_lines_position_unique');
        });
    }
    public function down():void {
        foreach(['purchase_bills_immutable','purchase_bill_items_immutable'] as $prefix)foreach(['i','u','d'] as $suffix)DB::unprepared("DROP TRIGGER IF EXISTS {$prefix}_$suffix");
        Schema::dropIfExists('purchase_debit_note_lines');Schema::dropIfExists('purchase_debit_notes');
        Schema::table('purchase_bill_items',fn(Blueprint $t)=>$t->dropUnique('purchase_bill_item_identity'));
    }
};
