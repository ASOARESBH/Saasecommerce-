<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiResponse;
use App\Core\TenantContext;
use App\Services\CatalogService;

class CatalogController extends ApiController
{
    private CatalogService $catalog;
    public function __construct() { $this->catalog = new CatalogService(); }

    public function products(): never
    {
        $this->authenticate(['catalog:read'], true);
        [$page, $perPage, $offset] = $this->paginate();
        $result = $this->catalog->products(['pagination' => [$page, $perPage, $offset], 'category' => $this->input('category'), 'search' => $this->input('search'), 'featured' => $this->input('featured')]);
        ApiResponse::success($result['items'], 200, ['page' => $result['page'], 'per_page' => $result['per_page'], 'pages' => $result['pages'], 'total' => $result['total']]);
    }

    public function categories(): never
    {
        $this->authenticate(['catalog:read'], true);
        ApiResponse::success($this->catalog->categories());
    }

    public function addons(): never
    {
        $this->authenticate(['catalog:read'], true);
        ApiResponse::success($this->catalog->addons());
    }

    public function combos(): never
    {
        $this->authenticate(['catalog:read'], true);
        ApiResponse::success($this->catalog->combos());
    }

    public function settings(): never
    {
        $this->authenticate(['settings:read'], true);
        $tenant = TenantContext::get();
        ApiResponse::success([
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'settings' => TenantContext::settings(),
            'currency' => TenantContext::setting('currency', 'BRL'),
            'timezone' => TenantContext::setting('timezone', 'America/Sao_Paulo'),
        ]);
    }
}
