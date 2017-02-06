<?php

namespace REBELinBLUE\Deployer\Exceptions;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Whoops\Run as Whoops;

/**
 * Exception handler.
 */
class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthenticationException::class,
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        TokenMismatchException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param \Exception $exception
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Exception               $exception
     *
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        if ($this->isHttpException($exception)) {
            return $this->renderHttpException($exception);
        }

        // Use whoops if it is bound to the container and the exception is safe to pass to whoops
        if ($this->container->bound(Whoops::class) && $this->isSafeToWhoops($exception)) {
            return $this->renderExceptionWithWhoops($request, $exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Render an exception using Whoops.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Exception               $exception
     *
     * @return \Illuminate\Http\Response
     */
    protected function renderExceptionWithWhoops($request, Exception $exception)
    {
        /** @var Whoops $whoops */
        $whoops = $this->container->make(Whoops::class);

        return new Response(
            $whoops->handleException($exception),
            $exception->getStatusCode(),
            $exception->getHeaders()
        );
    }

    /**
     * Don't allow the exceptions which laravel handles specially to be converted to Whoops
     * This is horrible though, see if we can find a better way to do it.
     * GrahamCampbell/Laravel-Exceptions unfortunately doesn't return JSON for whoops pages which are from AJAX.
     *
     * @param \Exception $exception
     *
     * @return bool
     */
    protected function isSafeToWhoops(Exception $exception)
    {
        if ($exception instanceof HttpResponseException) {
            return false;
        } elseif ($exception instanceof ModelNotFoundException) {
            return false;
        } elseif ($exception instanceof AuthorizationException) {
            return false;
        } elseif ($exception instanceof ValidationException && $exception->getResponse()) {
            return false;
        }

        return true;
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest('login');
    }
}
