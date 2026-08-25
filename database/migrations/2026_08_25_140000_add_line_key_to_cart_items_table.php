<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->string('line_key', 100)->nullable()->after('product_variation_id');
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->unique('token', 'carts_token_unique');
        });

        $seen = [];
        DB::table('cart_items')
            ->orderBy('id')
            ->get(['id', 'cart_id', 'product_id', 'product_variation_id'])
            ->each(function (object $item) use (&$seen): void {
                $lineKey = "product:{$item->product_id}:variation:".($item->product_variation_id ?? 'none');
                $scope = "{$item->cart_id}:{$lineKey}";

                if (isset($seen[$scope])) {
                    throw new RuntimeException("Duplicate logical CartItem rows exist for cart {$item->cart_id} and {$lineKey}.");
                }

                $seen[$scope] = true;
                DB::table('cart_items')->where('id', $item->id)->update(['line_key' => $lineKey]);
            });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->string('line_key', 100)->nullable(false)->change();
            $table->unique(['cart_id', 'line_key'], 'cart_item_line_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique('cart_item_line_key_unique');
            $table->dropColumn('line_key');
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropUnique('carts_token_unique');
        });
    }
};
