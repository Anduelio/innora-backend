<?php

namespace App\Traits;

use App\Singletons\DataManager;
use Illuminate\Pipeline\Pipeline;

trait PipelineHandler
{
    public function applyPipeline($builder, array $validated, array $filters = [])
    {
        app(DataManager::class)->setParameters($validated);

        return app(Pipeline::class)
            ->send($builder)
            ->through($filters)
            ->thenReturn();
    }
}
