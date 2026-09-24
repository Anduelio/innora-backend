<?php

namespace App\Traits;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiTrait
{
    protected int $defaultPageSize = 15;

    protected string $pageSizeName = 'page_size';

    protected string $noPaginationName = 'no_pagination';

    protected int $defaultPage = 1;

    protected string $pageName = 'page';

    protected string $simplePagination = 'simple';

    protected string $fullPagination = 'full';

    public function fetchResults(Relation|Builder $query, $paginationType = 'full'): Paginator|Collection
    {
        if (request()->filled($this->noPaginationName) && (bool) request()->get($this->noPaginationName)) {
            return $query->get();
        }
        $pageSize = request()->get($this->pageSizeName);
        $pageSize = is_numeric($pageSize) ? $pageSize : $this->defaultPageSize;
        $page = request()->get('page', $this->defaultPage);

        if ($paginationType === $this->fullPagination) {
            return $query->paginate($pageSize, ['*'], 'page', $page);
        }

        return $query->simplePaginate($pageSize);
    }

    public function getJsonResponse(
        $resourceItems,
        string $resourceClass,
        string $message = '',
        bool $asSingleModel = false,
        array $extraData = []
    ): JsonResponse {
        if ($asSingleModel || $resourceItems instanceof Model) {
            $resource = $resourceClass::make($resourceItems);
        } elseif ($resourceItems && $resourceClass) {
            $resource = $resourceClass::collection($resourceItems);
        } else {
            $resource = $resourceItems;
        }

        $data = $extraData === []
            ? $resource
            : array_merge(['list' => $resource], $extraData);

        $message = __($message !== '' ? $message : 'messages.common.operation_success');

        return $this->responseToJson($message, 200, $data);
    }

    public function generalError(string $message = '', int $status = 503, array $data = []): JsonResponse
    {
        $message = __($message !== '' ? $message : 'messages.common.something_wrong_happened_please_try_again_or_contact_administrator');

        $response = ['success' => false, 'message' => $message];

        if ($data !== []) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    public function successResponse(string $message = '', int $status = 200): JsonResponse
    {
        $message = __($message !== '' ? $message : 'messages.common.operation_success');

        return response()->json(['success' => true, 'message' => $message], $status);
    }

    public function responseToJson($responseMessage = '', $statusId = 200, $data = [], array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => __($responseMessage !== '' ? $responseMessage : 'messages.common.operation_success'),
            'data' => $data,
        ];

        $resource = $data instanceof AnonymousResourceCollection
            ? $data
            : (is_array($data) && ($data['list'] ?? null) instanceof AnonymousResourceCollection
                ? $data['list']
                : null);

        if ($resource !== null && isset($resource->resource)) {
            if ($resource->resource instanceof \Illuminate\Pagination\Paginator) {
                $response['meta'] = $this->extractSimplePaginationData($resource);
            } elseif ($resource->resource instanceof LengthAwarePaginator) {
                $response['meta'] = $this->extractFullPaginationData($resource);
            }
        }

        return response()->json($response, status: $statusId);
    }

    protected function extractSimplePaginationData(AnonymousResourceCollection $data)
    {
        return [
            'current_page' => $data->currentPage(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
            'path' => $data->resolveCurrentPath(),
            'per_page' => $data->perPage(),
        ];
    }

    protected function extractFullPaginationData(AnonymousResourceCollection $data)
    {
        return array_merge($this->extractSimplePaginationData($data), ['total' => $data->total(), 'last_page' => $data->lastPage()]);
    }
}
