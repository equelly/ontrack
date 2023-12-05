<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Service\Order\Service;
use Illuminate\Http\Request;



class BaseController extends Controller
{
    public $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }
    
}

