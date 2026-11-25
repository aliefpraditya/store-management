<?php

namespace App\Http\Requests\Concerns;

/**
 * The `contacts` list (people to call, each with a name and an optional phone) that the supplier and carrier forms share.
 * The key may be left out, which means "leave the contacts as they are".
 */
trait ValidatesContacts
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function contactRules(): array
    {
        return [
            'contacts' => ['sometimes', 'array', 'max:20'],
            'contacts.*.id' => ['nullable', 'integer'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function contactMessages(): array
    {
        return [
            'contacts.max' => 'Kontak terlalu banyak.',
            'contacts.*.name.required' => 'Nama kontak wajib diisi.',
            'contacts.*.phone.max' => 'Nomor telepon kontak terlalu panjang.',
        ];
    }
}
