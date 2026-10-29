<?php

namespace app\controller;

use app\BaseController;

class Page extends BaseController
{
    public function about()
    {
        return $this->render('page/about');
    }

    public function service()
    {
        return $this->render('page/service');
    }

    public function contact()
    {
        return $this->render('page/contact');
    }
}
