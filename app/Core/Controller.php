<?php

class Controller {
    
    // Method untuk memanggil view
    public function view($view, $data = [])
    {
        require_once '../app/Views/' . $view . '.php';
    }

    // Method untuk memanggil model (bila ada koneksi DB nanti)
    public function model($model)
    {
        require_once '../app/Models/' . $model . '.php';
        return new $model;
    }
}
