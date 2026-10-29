<?php

namespace app\model;

use think\Model;

class Product extends Model
{
    protected $name = 'products';
    protected $autoWriteTimestamp = 'datetime';
    protected $type = [
        'category_id'    => 'integer',
        'price'          => 'float',
        'original_price' => 'float',
        'stock'          => 'integer',
        'sales'          => 'integer',
        'featured'       => 'integer',
        'status'         => 'integer',
        'sort'           => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function scopeOnline($query)
    {
        $query->where('status', 1);
    }

    public function getHighlightsListAttr(): array
    {
        return $this->splitLines((string) $this->getData('highlights'));
    }

    public function getSpecsListAttr(): array
    {
        $rows = [];
        foreach ($this->splitLines((string) $this->getData('specs')) as $line) {
            $parts = preg_split('/[:：]/u', $line, 2);
            if (count($parts) === 2) {
                $rows[] = ['label' => trim($parts[0]), 'value' => trim($parts[1])];
            } else {
                $rows[] = ['label' => '说明', 'value' => trim($line)];
            }
        }
        return $rows;
    }

    private function splitLines(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        return array_values(array_filter(array_map('trim', $lines), fn ($line) => $line !== ''));
    }
}
