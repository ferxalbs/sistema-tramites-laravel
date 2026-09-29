<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tramite_plantilla_campos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('tramite_plantillas')->cascadeOnDelete();
            $table->string('clave_variable', 100);
            $table->string('etiqueta', 160);
            $table->string('grupo', 80)->default('Datos principales');
            $table->string('tipo_campo', 40);
            $table->text('valor_predeterminado')->nullable();
            $table->string('fuente_automatica', 100)->nullable();
            $table->boolean('obligatorio')->default(false);
            $table->boolean('requiere_confirmacion')->default(false);
            $table->boolean('permite_html')->default(false);
            $table->unsignedSmallInteger('orden')->default(1);
            $table->text('opciones')->nullable();
            $table->unsignedSmallInteger('longitud_maxima')->nullable();
            $table->text('reglas_validacion')->nullable();
            $table->string('texto_ayuda', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['plantilla_id', 'clave_variable']);
            $table->index(['plantilla_id', 'orden']);
        });

        Schema::create('tramite_borrador_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrador_id')->constrained('tramite_borradores')->cascadeOnDelete();
            $table->foreignId('campo_id')->constrained('tramite_plantilla_campos');
            $table->foreignId('usuario_id')->constrained('users');
            $table->text('valor')->nullable();
            $table->timestamps();

            $table->unique(['borrador_id', 'campo_id']);
            $table->index(['campo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_borrador_valores');
        Schema::dropIfExists('tramite_plantilla_campos');
    }
};
