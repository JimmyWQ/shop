<?php

namespace app\controller;

use app\BaseController;
use app\model\Category;
use app\model\Product as ProductModel;
use think\exception\HttpException;

class Product extends BaseController
{
    public function index()
    {
        return $this->listing(null);
    }

    public function category(string $slug)
    {
        $category = Category::where('slug', $slug)->where('status', 1)->find();
        if (!$category) {
            throw new HttpException(404, '分类不存在');
        }
        return $this->listing($category);
    }

    private function listing(?Category $category)
    {
        $keyword = trim((string) $this->request->get('q', ''));
        $keyword = mb_substr($keyword, 0, 60);
        $sort = (string) $this->request->get('sort', 'recommend');
        $allowedSorts = ['recommend', 'new', 'price_asc', 'price_desc', 'sales'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'recommend';
        }

        $query = ProductModel::with('category')->where('status', 1);
        if ($category) {
            $query->where('category_id', $category->id);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                    ->whereOr('subtitle', 'like', '%' . $keyword . '%');
            });
        }

        switch ($sort) {
            case 'new':
                $query->order('id', 'desc');
                break;
            case 'price_asc':
                $query->order('price', 'asc');
                break;
            case 'price_desc':
                $query->order('price', 'desc');
                break;
            case 'sales':
                $query->order('sales', 'desc');
                break;
            default:
                $query->order('sort', 'asc')->order('id', 'desc');
        }

        $products = $query->paginate([
            'list_rows' => 9,
            'query'     => $this->request->get(),
        ]);
        $categories = Category::where('status', 1)->order('sort', 'asc')->select();

        $counts = [];
        $rows = ProductModel::where('status', 1)
            ->field('category_id, COUNT(*) AS total')
            ->group('category_id')
            ->select();
        foreach ($rows as $row) {
            $counts[(int) $row['category_id']] = (int) $row['total'];
        }
        foreach ($categories as $categoryItem) {
            $categoryItem->product_count = $counts[(int) $categoryItem->id] ?? 0;
        }

        $base = $category ? '/category/' . $category->slug : '/shop';
        $params = $this->request->get();
        unset($params['sort'], $params['page']);
        $sortUrls = [];
        foreach ($allowedSorts as $key) {
            $sortUrls[$key] = $base . '?' . http_build_query(array_merge($params, ['sort' => $key]));
        }

        return $this->render('product/list', [
            'products'   => $products,
            'categories' => $categories,
            'category'   => $category,
            'keyword'    => $keyword,
            'sort'       => $sort,
            'counts'     => $counts,
            'sortUrls'   => $sortUrls,
            'pageTitle'  => $category ? $category->name : ($keyword !== '' ? '搜索「' . $keyword . '」' : '全部好物'),
        ]);
    }

    public function detail(string $slug)
    {
        $product = ProductModel::with('category')
            ->where('slug', $slug)
            ->where('status', 1)
            ->find();
        if (!$product) {
            throw new HttpException(404, '商品不存在或已下架');
        }

        $related = ProductModel::with('category')
            ->where('category_id', $product->category_id)
            ->where('id', '<>', $product->id)
            ->where('status', 1)
            ->order('sales', 'desc')
            ->limit(4)
            ->select();

        return $this->render('product/detail', [
            'product'    => $product,
            'related'    => $related,
            'highlights' => $product->highlights_list,
            'specs'      => $product->specs_list,
            'saving'     => max(0, (float) $product->original_price - (float) $product->price),
        ]);
    }
}
