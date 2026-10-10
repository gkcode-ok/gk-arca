<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arca_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('cuit', 11)->nullable();
            $table->string('razon_social', 255)->nullable();
            $table->string('condicion_iva', 60)->nullable();
            $table->enum('entorno', ['prod', 'homo'])->default('homo');
            $table->unsignedBigInteger('gkarca_tenant_id')->nullable();
            $table->text('api_token')->nullable();
            $table->enum('status', ['not_configured', 'connected', 'disconnected', 'error'])->default('not_configured');
            $table->enum('certificate_status', ['none', 'valid', 'expiring', 'expired'])->default('none');
            $table->string('certificate_name', 190)->nullable();
            $table->timestamp('certificate_expires_at')->nullable();
            $table->string('point_of_sale', 10)->nullable();
            $table->string('web_service', 20)->default('WSFEv1');
            $table->timestamp('last_connection_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadatos')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('company_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arca_connections');
    }
};
