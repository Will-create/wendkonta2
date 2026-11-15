<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionDatabaseResetTest extends TestCase
{
    public function test_database_reset_is_not_scheduled_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->app->forgetInstance(Schedule::class);
        $schedule = $this->app->make(Schedule::class);

        $this->assertNotEmpty($schedule->events());
        foreach ($schedule->events() as $event) {
            $this->assertStringNotContainsString('reset:db', $event->command);
        }
    }

    public function test_database_reset_refuses_production_before_changing_data(): void
    {
        $this->app['env'] = 'production';
        Schema::create('production_reset_sentinel', function (Blueprint $table) {
            $table->id();
            $table->string('value');
        });
        DB::table('production_reset_sentinel')->insert(['value' => 'preserve this record']);

        $this->assertSame(1, Artisan::call('reset:db'));
        $this->assertStringContainsString('disabled in production', Artisan::output());
        $this->assertDatabaseHas('production_reset_sentinel', ['value' => 'preserve this record']);
    }
}
