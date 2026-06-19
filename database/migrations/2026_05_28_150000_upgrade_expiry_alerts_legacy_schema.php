<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expiry_alerts')) {
            return;
        }

        Schema::table('expiry_alerts', function (Blueprint $table) {
            if (! Schema::hasColumn('expiry_alerts', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('branch_id');
            }
            if (! Schema::hasColumn('expiry_alerts', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('alert_type');
            }
            if (! Schema::hasColumn('expiry_alerts', 'quantity')) {
                $table->decimal('quantity', 12, 3)->default(0)->after('expiry_date');
            }
            if (! Schema::hasColumn('expiry_alerts', 'handled_at')) {
                $table->timestamp('handled_at')->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('expiry_alerts', 'snoozed_until')) {
                $table->timestamp('snoozed_until')->nullable()->after('handled_at');
            }
            if (! Schema::hasColumn('expiry_alerts', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        if (Schema::hasTable('inventory_batches')
            && Schema::hasColumn('expiry_alerts', 'product_id')
            && Schema::hasColumn('expiry_alerts', 'inventory_batch_id')
            && Schema::hasColumn('inventory_batches', 'product_id')) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('
                    UPDATE expiry_alerts
                    SET product_id = COALESCE(product_id, (
                            SELECT ib.product_id FROM inventory_batches ib
                            WHERE ib.id = expiry_alerts.inventory_batch_id
                        )),
                        expiry_date = COALESCE(expiry_date, (
                            SELECT ib.expiry_date FROM inventory_batches ib
                            WHERE ib.id = expiry_alerts.inventory_batch_id
                        )),
                        quantity = CASE WHEN quantity = 0 THEN COALESCE((
                            SELECT ib.quantity FROM inventory_batches ib
                            WHERE ib.id = expiry_alerts.inventory_batch_id
                        ), quantity) ELSE quantity END
                    WHERE EXISTS (
                        SELECT 1 FROM inventory_batches ib
                        WHERE ib.id = expiry_alerts.inventory_batch_id
                    )
                    AND (
                        product_id IS NULL
                        OR expiry_date IS NULL
                        OR quantity = 0
                    )
                ');
            } else {
                DB::statement('
                    UPDATE expiry_alerts ea
                    INNER JOIN inventory_batches ib ON ib.id = ea.inventory_batch_id
                    SET ea.product_id = ib.product_id,
                        ea.expiry_date = COALESCE(ea.expiry_date, ib.expiry_date),
                        ea.quantity = CASE WHEN ea.quantity = 0 THEN ib.quantity ELSE ea.quantity END
                    WHERE ea.product_id IS NULL
                       OR ea.expiry_date IS NULL
                       OR ea.quantity = 0
                ');
            }
        }

        if (Schema::hasColumn('expiry_alerts', 'notified_at') && Schema::hasColumn('expiry_alerts', 'handled_at')) {
            DB::table('expiry_alerts')
                ->whereNull('handled_at')
                ->whereNotNull('notified_at')
                ->update(['handled_at' => DB::raw('notified_at')]);
        }

        if (Schema::hasColumn('expiry_alerts', 'updated_at')) {
            DB::table('expiry_alerts')
                ->whereNull('updated_at')
                ->update(['updated_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('expiry_alerts')) {
            return;
        }

        Schema::table('expiry_alerts', function (Blueprint $table) {
            foreach (['product_id', 'expiry_date', 'quantity', 'handled_at', 'snoozed_until', 'updated_at'] as $column) {
                if (Schema::hasColumn('expiry_alerts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
