<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('delivered_at');
        });

        // Everything delivered before read tracking existed counts as read,
        // so nobody's correspondence lights up bold on the day this ships.
        DB::table('messages')->whereNotNull('delivered_at')->update(['read_at' => DB::raw('delivered_at')]);
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};
