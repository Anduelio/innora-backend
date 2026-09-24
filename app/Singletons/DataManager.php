<?php

namespace App\Singletons;

use Illuminate\Http\Exceptions\HttpResponseException;

class DataManager
{
    /** @var array Parameters stored within the DataManager */
    protected array $parameters = [];

    /**
     * Set a single parameter within the DataManager.
     *
     * @param  string  $key  The key of the parameter to be set.
     * @param  mixed  $value  The value to set for the given key.
     * @return $this
     */
    public function setParameter(string $key, $value)
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /**
     * Retrieve a parameter value from the DataManager by its key.
     *
     * @param  string  $key  The key of the parameter to retrieve.
     * @return mixed|null The value associated with the key or null if not found.
     */
    public function getParameter(string $key)
    {
        return $this->parameters[$key] ?? null;
    }

    /**
     * Set multiple parameters within the DataManager.
     *
     * @param  array  $parameters  An array of key-value pairs to set as parameters.
     * @return $this
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Get all parameters stored within the DataManager.
     *
     * @return array All parameters stored in the DataManager.
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Check if any elements of an array exist as keys in the parameters array.
     *
     * @param  array  $keys  Array of keys to check against the parameters array.
     * @return bool True if any element of the keys array exists as a key in parameters, false otherwise.
     */
    public function hasAny(array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->parameters)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if key is in the parameters array.
     *
     * @param  string  $key  string ket to check against the parameters array.
     * @return bool True if key exists in parameters, false otherwise.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->parameters);
    }

    /**
     * Throws a validation error with a custom message.
     *
     * @param  string  $field  The field name that caused the validation error.
     * @param  string  $message  The custom error message to be returned.
     *
     * @throws HttpResponseException Always throws this exception with the formatted error response.
     */
    /**
     * @param  array<string, mixed>  $replace
     */
    public function throwValidationError(string $field, string $message, array $replace = [])
    {
        $message = __($message, $replace);

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => [
                $field => [$message],
            ],
        ], 422));
    }

    /**
     * Throws a validation error with a custom message and array with errors.
     *
     * @param  string  $message  The field message for response.
     * @param  string  $errors  The array of errors.
     *
     * @throws HttpResponseException Always throws this exception with the formatted error response.
     */
    public function throwCustomValidationError(string $message, array $errors)
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $errors,
        ], 422));
    }

    /**
     * Get all parameters except those specified by the given keys.
     *
     * @param  array|string  $keys  Array of keys or a single key to exclude from the returned parameters.
     * @return array Parameters excluding the specified keys.
     */
    public function except(array|string $keys): array
    {
        $keys = (array) $keys;

        return array_diff_key($this->parameters, array_flip($keys));
    }

    /**
     * Merge a new array of parameters with the existing parameters.
     *
     * @param  array  $newParameters  Array of key-value pairs to merge with the existing parameters.
     * @return $this
     */
    public function mergeParameters(array $newParameters)
    {
        $this->parameters = array_merge($this->parameters, $newParameters);

        return $this;
    }

    /**
     * Check if all elements of an array exist as keys in the parameters array.
     *
     * @param  array  $keys  Array of keys to check against the parameters array.
     * @return bool True if all keys exist in parameters, false otherwise.
     */
    public function hasAll(array $keys): bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $this->parameters)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if all elements of an array exist as keys
     * and their values are not null.
     *
     * @param  array  $keys  Array of keys to check.
     * @return bool True if all keys exist and are not null, false otherwise.
     */
    public function hasAllNotNull(array $keys): bool
    {
        foreach ($keys as $key) {
            if (
                ! array_key_exists($key, $this->parameters)
                || $this->parameters[$key] === null
            ) {
                return false;
            }
        }

        return true;
    }
}
