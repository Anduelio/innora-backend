<?php

namespace App\Traits;

use App\Helpers\ApiCodes;
use Illuminate\Http\Exceptions\HttpResponseException;
use Throwable;

trait HasMessages
{
    protected $successMessages = [];

    protected $errorMessages = [];

    protected $data = [];

    protected $code;

    protected $validCodes = [
        ApiCodes::SUCCESS,
        ApiCodes::ACCEPTED,
        ApiCodes::BAD_REQUEST,
        ApiCodes::RESOURCE_NOT_FOUND,
        ApiCodes::FORBIDDEN,
        ApiCodes::UNPROCESSABLE_ENTITY,
        ApiCodes::UNAUTHENTICATED,
        ApiCodes::METHOD_NOT_ALLOWED,
        ApiCodes::INTERNAL_SERVER_ERROR,
        ApiCodes::SERVICE_UNAVAILABLE,
        ApiCodes::TEMPORARY_REDIRECT,
    ];

    /**
     * Get the first success message in the array or a default message if empty.
     *
     * @return string The success message
     */
    public function getSuccessMessage()
    {
        return $this->successMessages[0] ?? ApiCodes::getSuccessMessage();
    }

    /**
     * Set a success message.
     *
     * @param  string  $message  The success message to be added
     * @return $this The instance of the class
     */
    public function setSuccessMessage(string $message)
    {
        $this->successMessages[] = $message;

        return $this;
    }

    /**
     * Get the first error message in the array or a default message if empty.
     *
     * @return string The error message
     */
    public function getErrorMessage()
    {
        return $this->errorMessages[0] ?? ApiCodes::getGeneralErrorMessage();
    }

    /**
     * Set an error message.
     *
     * @param  string  $message  The error message to be added
     * @return $this The instance of the class
     */
    public function setErrorMessage(string $message)
    {
        $this->errorMessages[] = $message;

        return $this;
    }

    /**
     * Set the internal data array.
     *
     * @param  array  $data  The data to store (defaults to an empty array)
     * @return $this Returns the current instance for method chaining
     */
    public function setData(array $data = []): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get the stored data array.
     *
     * @return array The currently stored data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set an error code.
     *
     * @param  mixed  $code  The error code to be added
     * @return $this The instance of the class
     */
    public function setErrorCode(mixed $code)
    {
        $this->code = $this->purifyHttpCode($code);

        return $this;
    }

    /**
     * Get the error code.
     *
     * @return mixed
     */
    public function getErrorCode()
    {
        return $this->code ?? 503;
    }

    /**
     * Set an error message with code.
     *
     * @return $this The instance of the class
     */
    public function setError(string $message, int $code = 503, array $data = [])
    {
        $this->setErrorMessage(__($message));
        $this->setErrorCode($code);
        $this->setData($data);

        return $this;
    }

    /**
     * Get  error with message and code.
     *
     * @return array
     */
    public function getError()
    {
        return [$this->getErrorMessage(), $this->getErrorCode(), $this->getData()];
    }

    /**
     * Catch and rethrow an exception, setting an error message.
     *
     * @param  Throwable  $exception  The caught exception object
     *
     * @throws Throwable Re-throws the caught exception
     */
    public function throwExceptionError(Throwable $exception)
    {
        $this->setErrorCode($exception->getCode());
        $this->setErrorMessage($exception->getMessage());
        throw $exception;
    }

    /**
     * Throws a validation error with a custom message.
     *
     * Prefer lang keys under lang/{locale}/messages.php, e.g. messages.orders.x
     *
     * @param  string  $field  The field name that caused the validation error.
     * @param  string  $message  Translation key (messages.*).
     * @param  array<string, mixed>  $replace  Placeholder replacements for __.
     *
     * @throws HttpResponseException Always throws this exception with the formatted error response.
     */
    public function throwValidationError(string $field, string $message, array $replace = [])
    {
        $message = __($message, $replace);
        $this->setErrorMessage($message);
        $this->setErrorCode(422);

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => [
                $field => [$message],
            ],
        ], 422));
    }

    /**
     * Throws an authorization error (403). Used when an actor may hit an endpoint
     * (permission granted) but is not allowed to see or act on this specific
     * record (row-level visibility).
     *
     * @param  array<string, mixed>  $replace
     *
     * @throws HttpResponseException Always throws this exception with the formatted error response.
     */
    public function throwAuthorizationError(string $message = 'messages.common.unauthorized', array $replace = [])
    {
        $message = __($message, $replace);
        $this->setErrorMessage($message);
        $this->setErrorCode(403);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
        ], 403));
    }

    /**
     * Throws a validation error with a custom message and array with errors.
     *
     * @param  string  $message  Translation key (messages.*).
     * @param  array<string, mixed>  $errors  The array of errors.
     * @param  array<string, mixed>  $replace
     *
     * @throws HttpResponseException Always throws this exception with the formatted error response.
     */
    public function throwValidationErrors(string $message, array $errors, array $replace = [])
    {
        $message = __($message, $replace);
        $errors = $this->translateMessageTree($errors);
        $this->setErrorMessage($message);
        $this->setErrorCode(422);
        $this->setData($errors);

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $errors,
        ], 422));
    }

    /**
     * @param  array<string, mixed>  $messages
     * @return array<string, mixed>
     */
    private function translateMessageTree(array $messages): array
    {
        foreach ($messages as $key => $value) {
            if (is_array($value)) {
                $messages[$key] = $this->translateMessageTree($value);
            } elseif (is_string($value)) {
                $messages[$key] = __($value);
            }
        }

        return $messages;
    }

    /**
     * Resolve a valid HTTP response code .
     *
     * @return int
     */
    public function purifyHttpCode(mixed $code)
    {
        return in_array($code, $this->validCodes) ? $code : 503;
    }
}
