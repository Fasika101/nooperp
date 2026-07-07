<?php

use App\Support\OpticalRxConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        OpticalRxConfig::ensureProgressiveAddSeeded();
    }

    public function down(): void
    {
        // Values may have been customized; leave rows in place on rollback.
    }
};
