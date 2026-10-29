<?php

namespace app\controller\admin;

use app\model\Category;
use app\model\Product as ProductModel;
use RuntimeException;
use think\exception\HttpException;

class Product extends AdminBase
{
    public function index()
    {
        $keyword = trim((string) $this->request->get('q', ''));
        $categoryId = (int) $this->request->get('category_id', 0);
        $status = (string) $this->request->get('status', 'all');

        $query = ProductModel::with('category');
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                    ->whereOr('slug', 'like', '%' . $keyword . '%');
            });
        }
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        if ($status === '1' || $status === '0') {
            $query->where('status', (int) $status);
        }

        $products = $query->order('sort', 'asc')->order('id', 'desc')->paginate([
            'list_rows' => 10,
            'query'     => $this->request->get(),
        ]);

        return $this->render('admin/product/index', [
            'products'   => $products,
            'categories' => Category::order('sort', 'asc')->select(),
            'keyword'    => $keyword,
            'categoryId' => $categoryId,
            'status'     => $status,
        ]);
    }

    public function create()
    {
        return $this->form(null);
    }

    public function store()
    {
        try {
            $data = $this->prepareData(null);
            $error = $this->validateData($data, null);
            if ($error !== null) {
                flash('error', $error);
                $this->keepOld($this->rawFormData());
                return redirect('/admin/products/create');
            }
            ProductModel::create($data);
            flash('success', '商品「' . $data['name'] . '」已创建。');
            return redirect('/admin/products');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->keepOld($this->rawFormData());
            return redirect('/admin/products/create');
        }
    }

    public function edit(int $id)
    {
        $product = ProductModel::find($id);
        if (!$product) {
            throw new HttpException(404, '商品不存在');
        }
        return $this->form($product);
    }

    public function update(int $id)
    {
        $product = ProductModel::find($id);
        if (!$product) {
            throw new HttpException(404, '商品不存在');
        }

        try {
            $data = $this->prepareData($product);
            $error = $this->validateData($data, $id);
            if ($error !== null) {
                flash('error', $error);
                $this->keepOld($this->rawFormData());
                return redirect('/admin/products/' . $id . '/edit');
            }
            $product->save($data);
            flash('success', '商品信息已更新。');
            return redirect('/admin/products');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->keepOld($this->rawFormData());
            return redirect('/admin/products/' . $id . '/edit');
        }
    }

    public function delete(int $id)
    {
        $product = ProductModel::find($id);
        if (!$product) {
            throw new HttpException(404, '商品不存在');
        }
        $name = $product->name;
        $product->delete();
        flash('success', '商品「' . $name . '」已删除。');
        return redirect('/admin/products');
    }

    public function toggle(int $id)
    {
        $product = ProductModel::find($id);
        if (!$product) {
            throw new HttpException(404, '商品不存在');
        }
        $product->status = $product->status ? 0 : 1;
        $product->save();
        flash('success', $product->status ? '商品已上架。' : '商品已下架。');
        return redirect('/admin/products');
    }

    private function form(?ProductModel $product)
    {
        $values = $product ? $product->toArray() : [
            'name'           => '',
            'category_id'    => 0,
            'slug'           => '',
            'subtitle'       => '',
            'description'    => '',
            'highlights'     => '',
            'specs'          => '',
            'price'          => '',
            'original_price' => '',
            'stock'          => 0,
            'cover'          => '',
            'badge'          => '',
            'featured'       => 0,
            'status'         => 1,
            'sort'           => 0,
        ];

        return $this->render('admin/product/form', [
            'product'    => $product,
            'values'     => $values,
            'formAction' => $product ? '/admin/products/' . $product->id : '/admin/products',
            'isEdit'     => (bool) $product,
            'categories' => Category::order('sort', 'asc')->select(),
        ]);
    }

    private function rawFormData(): array
    {
        return [
            'name'           => (string) $this->request->post('name', ''),
            'category_id'    => (int) $this->request->post('category_id', 0),
            'slug'           => (string) $this->request->post('slug', ''),
            'subtitle'       => (string) $this->request->post('subtitle', ''),
            'description'    => (string) $this->request->post('description', ''),
            'highlights'     => (string) $this->request->post('highlights', ''),
            'specs'          => (string) $this->request->post('specs', ''),
            'price'          => (string) $this->request->post('price', ''),
            'original_price' => (string) $this->request->post('original_price', ''),
            'stock'          => (string) $this->request->post('stock', '0'),
            'cover'          => (string) $this->request->post('cover', ''),
            'badge'          => (string) $this->request->post('badge', ''),
            'featured'       => $this->request->post('featured') ? 1 : 0,
            'status'         => (int) $this->request->post('status', 1),
            'sort'           => (int) $this->request->post('sort', 0),
        ];
    }

    private function prepareData(?ProductModel $product): array
    {
        $raw = $this->rawFormData();
        $slug = strtolower(trim($raw['slug']));
        if ($slug === '') {
            $slug = 'product-' . date('ymdHis') . '-' . random_int(100, 999);
        }
        $slug = trim((string) preg_replace('/[^a-z0-9-]+/', '-', $slug), '-');
        if ($slug === '') {
            $slug = 'product-' . date('ymdHis') . '-' . random_int(100, 999);
        }

        $cover = trim($raw['cover']);
        $uploaded = $this->handleCover();
        if ($uploaded !== '') {
            $cover = $uploaded;
        }
        if ($cover === '' && $product) {
            $cover = $product->cover;
        }
        if ($cover === '') {
            $cover = '/static/images/products/placeholder.svg';
        }
        if (!str_starts_with($cover, '/') && !preg_match('#^https?://#i', $cover)) {
            $cover = '/' . $cover;
        }

        $original = trim($raw['original_price']);

        return [
            'category_id'    => $raw['category_id'],
            'name'           => trim($raw['name']),
            'slug'           => $slug,
            'subtitle'       => trim($raw['subtitle']),
            'description'    => trim($raw['description']),
            'highlights'     => trim($raw['highlights']),
            'specs'          => trim($raw['specs']),
            'price'          => round((float) $raw['price'], 2),
            'original_price' => $original === '' ? null : round((float) $original, 2),
            'stock'          => (int) $raw['stock'],
            'cover'          => $cover,
            'badge'          => trim($raw['badge']),
            'featured'       => (int) $raw['featured'],
            'status'         => $raw['status'] === 1 ? 1 : 0,
            'sort'           => (int) $raw['sort'],
        ];
    }

    private function handleCover(): string
    {
        $file = $this->request->file('cover_file');
        if (!$file) {
            return '';
        }

        if (!$file->isValid()) {
            throw new RuntimeException('图片上传失败，请重新选择文件。');
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            throw new RuntimeException('图片大小不能超过 2MB。');
        }

        $extension = strtolower($file->getOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('仅支持 JPG、PNG 或 WebP 图片。');
        }

        $mime = (string) $file->getMime();
        if (!in_array($mime, ['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('文件内容不是有效的图片。');
        }

        $dir = public_path() . 'uploads';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('无法创建上传目录。');
        }

        $filename = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file->getPathname(), $target)) {
            throw new RuntimeException('图片保存失败，请检查上传目录权限。');
        }
        @chmod($target, 0644);

        return '/uploads/' . $filename;
    }

    private function validateData(array $data, ?int $id): ?string
    {
        if (mb_strlen($data['name']) < 2 || mb_strlen($data['name']) > 120) {
            return '商品名称需为 2–120 个字符。';
        }
        if (!$data['category_id'] || !Category::find($data['category_id'])) {
            return '请选择有效的商品分类。';
        }
        if ($data['slug'] === '' || !preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
            return 'URL 标识只能包含小写字母、数字和连字符。';
        }
        $duplicate = ProductModel::where('slug', $data['slug'])
            ->where('id', '<>', $id ?? 0)
            ->count();
        if ($duplicate) {
            return '这个 URL 标识已经被使用，请换一个。';
        }
        if ($data['price'] <= 0 || $data['price'] > 999999) {
            return '请填写有效的售价。';
        }
        if ($data['original_price'] !== null && $data['original_price'] < $data['price']) {
            return '划线价不能低于售价。';
        }
        if ($data['stock'] < 0 || $data['stock'] > 999999) {
            return '库存需在 0–999999 之间。';
        }
        if (mb_strlen($data['subtitle']) > 255) {
            return '副标题不能超过 255 个字符。';
        }
        if (mb_strlen($data['description']) > 8000) {
            return '商品详情不能超过 8000 个字符。';
        }
        if (mb_strlen($data['badge']) > 30) {
            return '商品标签不能超过 30 个字符。';
        }
        if (mb_strlen($data['cover']) > 255) {
            return '图片路径或 URL 过长。';
        }
        return null;
    }
}
