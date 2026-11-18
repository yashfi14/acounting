<?php

/**
 * ErrorController — Menangani halaman error aplikasi.
 * 
 * Error views (404.php, 500.php) bersifat standalone (punya HTML sendiri)
 * sehingga tidak menggunakan template header/footer.
 */
class ErrorController extends Controller {

    public function index()
    {
        $this->notFound();
    }

    /**
     * 404 Not Found
     */
    public function notFound()
    {
        http_response_code(404);
        // Error pages standalone — punya HTML sendiri
        require_once '../app/Views/errors/404.php';
        exit;
    }

    /**
     * 403 Forbidden
     */
    public function forbidden($errorMessage = null)
    {
        http_response_code(403);
        require_once '../app/Views/errors/403.php';
        exit;
    }

    /**
     * 500 Internal Server Error
     */
    public function general()
    {
        http_response_code(500);
        require_once '../app/Views/errors/500.php';
        exit;
    }
}
