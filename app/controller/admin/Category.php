<?php

namespace app\controller\admin;

use app\model\Category as CategoryModel;
use app\model\Product;
use think\exception\HttpException;
use think\facade\Db;

class Category extends AdminBase
{
    public function index()
    {
        $categories = CategoryModel::order('sort', 'asc')->order('id', 'asc')->select();
        $counts = [];
        $rows = Product::field('category_id, COUNT(*) AS total')->group('category_id')->select();
        foreach ($rows as $row) {
            $counts[(int) $row['category_id']] = (int) $row['total'];
        }
        foreach ($categories as $category) {
            $category->product_count = $counts[(int) $category->id] ?? 0;
        }

        return $this->render('admin/category/index', [
            'categories' => $categories,
            'counts'     => $counts,
        ]);
    }

    public function create()
    {
        return $this->render('admin/category/form', [
            'category'   => null,
            'values'     => ['name' => '', 'slug' => '', 'description' => '', 'cover' => '', 'sort' => 0, 'status' => 1],
            'formAction' => '/admin/categories',
            'isEdit'     => false,
        ]);
    }

    public function store()
    {
        $data = $this->prepareData();
        $error = $this->validateData($data, null);
        if ($error !== null) {
            flash('error', $error);
            $this->keepOld($this->rawFormData());
            return redirect('/admin/categories/create');
        }
        CategoryModel::create($data);
        flash('success', '分类已创建。');
        return redirect('/admin/categories');
    }

    public function edit(int $id)
    {
        $category = CategoryModel::find($id);
        if (!$category) {
            throw new HttpException(404, '分类不存在');
        }
        return $this->render('admin/category/form', [
            'category'   => $category,
            'values'     => $category->toArray(),
            'formAction' => '/admin/categories/' . $category->id,
            'isEdit'     => true,
        ]);
    }

    public function update(int $id)
    {
        $category = CategoryModel::find($id);
        if (!$category) {
            throw new HttpException(404, '分类不存在');
        }
        $data = $this->prepareData();
        $error = $this->validateData($data, $id);
        if ($error !== null) {
            flash('error', $error);
            $this->keepOld($this->rawFormData());
            return redirect('/admin/categories/' . $id . '/edit');
        }
        $category->save($data);
        flash('success', '分类已更新。');
        return redirect('/admin/categories');
    }

    public function delete(int $id)
    {
        $category = CategoryModel::find($id);
        if (!$category) {
            throw new HttpException(404, '分类不存在');
        }
        if (Product::where('category_id', $id)->count() > 0) {
            flash('error', '该分类下还有商品，请先移动或删除商品。');
            return redirect('/admin/categories');
        }
        $category->delete();
        flash('success', '分类已删除。');
        return redirect('/admin/categories');
    }

    public function toggle(int $id)
    {
        $category = CategoryModel::find($id);
        if (!$category) {
            throw new HttpException(404, '分类不存在');
        }
        $category->status = $category->status ? 0 : 1;
        $category->save();
        flash('success', $category->status ? '分类已启用。' : '分类已停用。');
        return redirect('/admin/categories');
    }

    private function rawFormData(): array
    {
        return [
            'name'        => (string) $this->request->post('name', ''),
            'slug'        => (string) $this->request->post('slug', ''),
            'description' => (string) $this->request->post('description', ''),
            'cover'       => (string) $this->request->post('cover', ''),
            'sort'        => (int) $this->request->post('sort', 0),
            'status'      => (int) $this->request->post('status', 1),
        ];
    }

    private function prepareData(): array
    {
        $raw = $this->rawFormData();
        $slug = strtolower(trim($raw['slug']));
        if ($slug === '') {
            $slug = 'category-' . date('ymdHis');
        }
        $slug = trim((string) preg_replace('/[^a-z0-9-]+/', '-', $slug), '-');
        if ($slug === '') {
            $slug = 'category-' . date('ymdHis');
        }
        $cover = trim($raw['cover']);
        if ($cover === '') {
            $cover = '/static/images/products/placeholder.svg';
        }
        if (!str_starts_with($cover, '/') && !preg_match('#^https?://#i', $cover)) {
            $cover = '/' . $cover;
        }

        return [
            'name'        => trim($raw['name']),
            'slug'        => $slug,
            'description' => trim($raw['description']),
            'cover'       => $cover,
            'sort'        => $raw['sort'],
            'status'      => $raw['status'] === 1 ? 1 : 0,
        ];
    }

    private function validateData(array $data, ?int $id): ?string
    {
        if ($data['name'] === '' || mb_strlen($data['name']) > 60) {
            return '分类名称需为 1–60 个字符。';
        }
        if ($data['slug'] === '' || !preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
            return 'URL 标识只能包含小写字母、数字和连字符。';
        }
        if (CategoryModel::where('slug', $data['slug'])->where('id', '<>', $id ?? 0)->count()) {
            return '这个 URL 标识已经被使用。';
        }
        if (mb_strlen($data['description']) > 255) {
            return '分类描述不能超过 255 个字符。';
        }
        if (mb_strlen($data['cover']) > 255) {
            return '分类图片路径或 URL 过长。';
        }
        return null;
    }
}
