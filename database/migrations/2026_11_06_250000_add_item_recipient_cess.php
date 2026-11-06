<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up():void {
        Schema::table('invoice_items',function(Blueprint $t){
            if(DB::getDriverName()==='sqlite')$t->string('recipient_cess',17)->default('0.00');
            else $t->decimal('recipient_cess',15,2)->default('0.00');
        });
    }
    public function down():void { Schema::table('invoice_items',fn(Blueprint $t)=>$t->dropColumn('recipient_cess')); }
};
