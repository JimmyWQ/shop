<?php

namespace app\controller;

use app\BaseController;
use app\model\Category;
use app\model\Product;

class Index extends BaseController
{
    public function index()
    {
        $featured = Product::with('category')
            ->where('status', 1)
            ->where('featured', 1)
            ->order('sort', 'asc')
            ->limit(8)
            ->select();

        $newProducts = Product::with('category')
            ->where('status', 1)
            ->order('id', 'desc')
            ->limit(4)
            ->select();

        $categories = Category::where('status', 1)->order('sort', 'asc')->select();

        $counts = [];
        $rows = Product::where('status', 1)
            ->field('category_id, COUNT(*) AS total')
            ->group('category_id')
            ->select();
        foreach ($rows as $row) {
            $counts[(int) $row['category_id']] = (int) $row['total'];
        }
        foreach ($categories as $category) {
            $category->product_count = $counts[(int) $category->id] ?? 0;
        }

        $heroProducts = $featured->slice(0, 3)->values();

        return $this->render('index/index', [
            'featured'    => $featured,
            'newProducts' => $newProducts,
            'categories'  => $categories,
            'counts'      => $counts,
            'heroMain'    => $heroProducts[0] ?? null,
            'heroSideOne' => $heroProducts[1] ?? null,
            'heroSideTwo' => $heroProducts[2] ?? null,
        ]);
    }
}
