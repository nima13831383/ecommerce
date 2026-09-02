<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->string('status', 20)->default('pending')->change();
            $table->string('carrier_service', 100)->nullable()->after('carrier');
            $table->json('shipping_snapshot')->nullable()->after('shipping_address');
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            $table->unique('order_id', 'shipments_order_id_unique');
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->renameColumn('tracking_code', 'tracking_number');
        });

        Schema::create('shipment_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['shipment_id', 'created_at'], 'shipment_history_shipment_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_status_histories');

        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique('shipments_order_id_unique');
            $table->renameColumn('tracking_number', 'tracking_code');
            $table->dropColumn(['carrier_service', 'shipping_snapshot', 'cancelled_at']);
        });
    }
};
