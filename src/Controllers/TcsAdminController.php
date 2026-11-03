<?php

namespace Tacraft\TcsLogin\Controllers;

use Illuminate\Routing\Controller;
use Tacraft\TcsLogin\Configuration;

class TcsAdminController extends Controller
{
    public function config()
    {
        return (new Configuration())->render();
    }
}
