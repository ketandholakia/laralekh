<?php
use App\Livewire\Gst\Returns\Detail;
use App\Livewire\Gst\Returns\Dashboard;
use Livewire\Livewire;

it('shows the return period detail only for users with returns view permission',function(){
    [$user,$company]=webSession('returns-viewer',['returns.view']);
    $this->actingAs($user);
    inTenantContext($company,function(){
        Livewire::test(Detail::class,['period'=>'2026-11'])->assertSuccessful();
        Livewire::test(Detail::class,['period'=>'2026-13'])->assertNotFound();
    });
});

it('shows tenant-scoped return periods on the dashboard and blocks users without view access',function(){
    [$user,$company]=webSession('returns-dashboard',['returns.view']);$this->actingAs($user);
    inTenantContext($company,fn()=>Livewire::test(Dashboard::class)->assertSuccessful()->assertSee('GST returns'));
});

it('blocks dashboard access for users without returns view permission',function(){
    [$denied,$otherCompany]=webSession('returns-denied',[]);$this->actingAs($denied);
    inTenantContext($otherCompany,fn()=>Livewire::test(Dashboard::class)->assertForbidden());
});

it('requires finalize permission for snapshot actions',function(){
    [$user,$company]=webSession('returns-viewer',['returns.view']);
    $this->actingAs($user);
    inTenantContext($company,fn()=>Livewire::test(Detail::class,['period'=>'2026-11'])->call('finalize',1)->assertForbidden());
});
