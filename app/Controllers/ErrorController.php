<?php
namespace App\Controllers;
use App\Core\Controller;

class ErrorController extends Controller {
    public function notFound() {
        $this->view('errors/404', ['page_title' => 'Sayfa Bulunamadı'], 'main');
    }
}
