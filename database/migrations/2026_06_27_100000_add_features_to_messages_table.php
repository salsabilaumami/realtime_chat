<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Reply ke pesan lain (nullable, self-reference)
            $table->foreignId('parent_id')->nullable()->after('type')
                ->constrained('messages')->onDelete('set null');

            // Media (gambar / voice note)
            $table->string('media_path')->nullable()->after('parent_id');

            // Status pesan: sent -> delivered -> read
            $table->string('status')->default('sent')->after('media_path');

            // Soft delete: pesan dihapus tapi tetap ada jejaknya ("Pesan telah dihapus")
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'media_path', 'status']);
            $table->dropSoftDeletes();
        });
    }
};
