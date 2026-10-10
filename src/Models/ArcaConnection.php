<?php

namespace GKCode\Arca\Models;

use App\Models\ModelGeneral;
use App\Models\System\Company;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArcaConnection extends ModelGeneral
{
    use SoftDeletes;
    use BelongsToCompany;

    protected $table = 'arca_connections';

    protected $fillable = [
        'company_id',
        'cuit',
        'razon_social',
        'condicion_iva',
        'entorno',
        'gkarca_tenant_id',
        'api_token',
        'status',
        'certificate_status',
        'certificate_name',
        'certificate_expires_at',
        'point_of_sale',
        'web_service',
        'last_connection_at',
        'last_error',
        'metadatos',
    ];

    protected $hidden = ['api_token'];

    protected $casts = [
        'company_id' => 'integer',
        'gkarca_tenant_id' => 'integer',
        'api_token' => 'encrypted',
        'metadatos' => 'array',
        'certificate_expires_at' => 'datetime',
        'last_connection_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public static function generalConfig(string $titulo = 'Facturacion ARCA', string $icon = 'mdi-receipt-text-outline'): array
    {
        return parent::generalConfig($titulo, $icon);
    }
}
