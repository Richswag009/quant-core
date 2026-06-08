<?php

namespace App\Exceptions;

// use App\Http\Traits\HasApiResponse;
use App\Http\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;

class BuildResponse
{
    use ResponseTrait;

    protected Exception|\Throwable $exception;

    protected ?Request $request;

    public function __construct(Exception|\Throwable $e, ?Request $request = null)
    {
        $this->exception = $e;

        $this->request = $request;
    }

    public function handle()
    {
        $method = (new ReflectionClass($this->exception))->getShortName();

        // dd($method);
        if (method_exists($this, $handler = 'handle' . $method)) {
            return $this->{$handler}();
        }

        // Log detailed error internally for debugging (never expose to user)
        Log::error('Unhandled Exception ' . $method, [
            'exception_class' => get_class($this->exception),
            'message' => $this->exception->getMessage(),
            'file' => $this->exception->getFile(),
            'line' => $this->exception->getLine(),
            'user_id' => auth()->id(),
            'request_url' => request()->url(),
            'request_method' => request()->method(),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
        ]);

        // $code = $this->exception->getCode();

        // Check if the code is an integer and a valid HTTP response code
        // if (is_numeric($code) && http_response_code($code) !== false) {
        //     $responseCode = $code;
        // } else {
        //     $responseCode = 500;
        // }

        $code = (int) $this->exception->getCode();

        if ($code >= 100 && $code <= 599) {
            $responseCode = $code;
        } else {
            $responseCode = 500;
        }


        // Always use generic message in production, never expose exception details
        if ($this->isProductionEnvironment()) {
            $message = 'Something went wrong, Please Contact Admin';
            $exception = null; // Don't pass exception to prevent any leaks
        } else {
            $message = $this->exception->getMessage();
            $exception = $this->exception;
        }

        // This would send a generic message to the client
        return $this->serverErrorResponse($message, $responseCode, $exception);
    }

    protected function handleValidationException(): Response
    {
        return (new ValidationResponseException($this->exception->validator, $this->request))->getResponse();
    }

    protected function handleAuthenticationException(): JsonResponse
    {
        return $this->unauthenticatedResponse($this->exception->getMessage());
    }

    protected function handleModelNotFoundException(): JsonResponse
    {
        return $this->notFoundResponse('Resource not available.');
    }

    protected function handleNotFoundHttpException(): JsonResponse
    {
        return $this->notFoundResponse('Route not found.');
    }

    protected function handleHttpResponseException()
    {
        return $this->exception->getResponse();
    }

    protected function handleBatchException(): JsonResponse
    {
        return $this->badRequestResponse(
            $this->exception->getMessage()
        );
    }

    protected function handleHttpException(): JsonResponse
    {
        return $this->jsonResponse($this->exception->getMessage(), $this->exception->getStatusCode());
    }

    protected function handleAuthorizationException(): JsonResponse
    {
        return $this->jsonResponse($this->exception->getMessage(), 403);
    }

    protected function handleThrottleRequestsException(): JsonResponse
    {
        return $this->jsonResponse($this->exception->getMessage(), 429);
    }

    /**
     * Check if we're in a production environment where errors should be hidden
     * This method provides multiple layers of protection against exposing sensitive data
     */
    private function isProductionEnvironment(): bool
    {
        // Check environment name
        if (app()->environment(['production', 'staging'])) {
            return true;
        }

        // Check if APP_DEBUG is false (additional safety)
        if (! config('app.debug')) {
            return true;
        }

        // Check if we're running on a production URL (additional safety)
        $productionDomains = ['optimus.ng', 'api.optimus.ng', 'backoffice.optimus.ng'];
        $currentDomain = request()->getHost();

        foreach ($productionDomains as $domain) {
            if (str_contains($currentDomain, $domain)) {
                return true;
            }
        }

        return false;
    }
}
