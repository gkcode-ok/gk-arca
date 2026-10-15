<?php

use GKCode\Arca\Controllers\ArcaConnectionController;
// use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => '/Arca'], function () {
    // Route::get('/pos/productos', [PosController::class, 'products'])->name('Arca.pos.productos');
    // Route::get('/pos/clientes', [PosController::class, 'customers'])->name('Arca.pos.clientes');
    // Route::post('/pos/ventas', [PosController::class, 'store'])->name('Arca.pos.ventas');
    // Route::get('/pos/ventas/{sale}/comprobante', [PosController::class, 'receipt'])->name('Arca.pos.ventas.comprobante');
    Route::get('/estado', [ArcaConnectionController::class, 'estado'])->name('Arca.estado');
    Route::get('/datos-comercio', [ArcaConnectionController::class, 'datosComercio'])->name('Arca.datosComercio');
    Route::post('/conectar', [ArcaConnectionController::class, 'conectar'])->name('Arca.conectar');
    Route::post('/certificado', [ArcaConnectionController::class, 'certificado'])->name('Arca.certificado');
    Route::post('/punto-venta', [ArcaConnectionController::class, 'puntoVenta'])->name('Arca.puntoVenta');
    Route::post('/probar-conexion', [ArcaConnectionController::class, 'probarConexion'])->name('Arca.probarConexion');
    Route::post('/probar-api', [ArcaConnectionController::class, 'probarApi'])->name('Arca.probarApi');
    Route::put('/desconectar', [ArcaConnectionController::class, 'desconectar'])->name('Arca.desconectar');
});
