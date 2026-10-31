<?php

return [
    'array' => ':Attribute harus berupa daftar.',
    'boolean' => ':Attribute harus ya atau tidak.',
    'decimal' => ':Attribute maksimal :decimal angka di belakang koma.',
    'email' => 'Format :attribute belum benar.',
    'enum' => ':Attribute tidak dikenal.',
    'exists' => ':Attribute tidak ditemukan.',
    'gt' => [
        'numeric' => ':Attribute harus lebih dari :value.',
    ],
    'in' => ':Attribute tidak dikenal.',
    'integer' => ':Attribute harus berupa bilangan bulat.',
    'max' => [
        'numeric' => ':Attribute tidak boleh lebih dari :max.',
        'string' => ':Attribute maksimal :max karakter.',
        'array' => ':Attribute maksimal :max item.',
    ],
    'min' => [
        'numeric' => ':Attribute minimal :min.',
        'string' => ':Attribute minimal :min karakter.',
        'array' => ':Attribute minimal :min item.',
    ],
    'numeric' => ':Attribute harus berupa angka.',
    'regex' => 'Format :attribute belum benar.',
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah dipakai.',

    'attributes' => [
        'name' => 'nama',
        'username' => 'username',
        'password' => 'password',
        'sku' => 'SKU',
        'category' => 'kategori',
        'brand' => 'merek',
        'min_stock' => 'stok minimum',
        'cost_price' => 'harga beli',
        'units' => 'satuan',
        'units.*.unit' => 'satuan',
        'units.*.conversion_factor' => 'konversi',
        'units.*.selling_price' => 'harga jual',
        'units.*.barcode' => 'barcode',
    ],
];
