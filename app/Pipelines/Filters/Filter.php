<?php

namespace App\Pipelines\Filters;

use App\Singletons\DataManager;
use Closure;

abstract class Filter
{
    public string $attribute;

    public array $parameters;

    public $dataManager;

    public function __construct(DataManager $dataManager)
    {
        $this->dataManager = $dataManager;
        $this->parameters = $this->dataManager->getParameters();
    }

    /**
     * Handle method to execute the filter logic
     *
     * @param  mixed  $data  The data to be filtered
     * @param  Closure  $next  The next filter or action in the pipeline
     * @return mixed
     */
    public function handle($data, Closure $next)
    {
        return $this->shouldApplyFilter($next($data));
    }

    /**
     * Method to determine whether the filter should be applied
     *
     * @param  mixed  $passable  The data passed through the filter
     * @return mixed
     */
    public function shouldApplyFilter($passable)
    {
        // Check if the attribute exists in DataManager, if not, return the passable data
        if (! $this->dataManager->has($this->attribute) || is_null($this->dataManager->getParameter($this->attribute))) {
            return $passable;
        }

        // Apply the filter on the passable data
        return $this->applyFilter($passable);
    }

    /**
     * Abstract method for applying the actual filtering logic.
     *
     * This method must be implemented by the subclasses.
     *
     * @param  mixed  $builder  The data to be filtered
     * @return mixed
     */
    abstract protected function applyFilter($builder);
}
