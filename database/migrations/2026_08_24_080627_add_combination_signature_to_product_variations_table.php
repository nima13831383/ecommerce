<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_variations', 'combination_signature')) {
            Schema::table('product_variations', function (Blueprint $table): void {
                $table->string('combination_signature', 255)->default('')->after('product_id');
            });
        }

        DB::table('product_variations')
            ->orderBy('id')
            ->chunkById(200, function ($variations): void {
                foreach ($variations as $variation) {
                    $valuePairs = DB::table('attribute_value_product_variation as pivot')
                        ->join('attribute_values as value', 'value.id', '=', 'pivot.attribute_value_id')
                        ->where('pivot.product_variation_id', $variation->id)
                        ->orderBy('value.attribute_id')
                        ->get(['value.attribute_id', 'value.id']);

                    if ($valuePairs->isEmpty()) {
                        throw new RuntimeException("Variation {$variation->id} has no attribute values and cannot receive a combination signature.");
                    }

                    if ($valuePairs->pluck('attribute_id')->unique()->count() !== $valuePairs->count()) {
                        throw new RuntimeException("Variation {$variation->id} has multiple values from one attribute axis.");
                    }

                    $signature = $valuePairs
                        ->map(fn ($value) => "{$value->attribute_id}:{$value->id}")
                        ->implode('|');

                    DB::table('product_variations')
                        ->where('id', $variation->id)
                        ->update(['combination_signature' => $signature]);
                }
            });

        $duplicates = DB::table('product_variations')
            ->select('product_id', 'combination_signature')
            ->groupBy('product_id', 'combination_signature')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Duplicate product variation combinations exist. Resolve them before adding the unique constraint.');
        }

        Schema::table('product_variations', function (Blueprint $table): void {
            $table->unique(['product_id', 'combination_signature'], 'product_variation_combination_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_variations', function (Blueprint $table): void {
            $table->dropUnique('product_variation_combination_unique');
            $table->dropColumn('combination_signature');
        });
    }
};
