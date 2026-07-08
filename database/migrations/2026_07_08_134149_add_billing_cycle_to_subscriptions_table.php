<?php

use App\Enums\BillingCycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('subscriptions', 'billing_cycle')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_cycle')->default(BillingCycle::MONTHLY->value)->after('plan_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('subscriptions', 'billing_cycle')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });
    }
};
