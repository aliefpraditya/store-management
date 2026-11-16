<?php

namespace App\Http\Requests\Central;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Used for both adding (POST) and editing (PUT) a store; the store being edited is the `store` route
 * parameter. The code is only accepted when adding, and the database name only while the store
 * database has not been set up successfully.
 */
class SaveStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'code' => Str::lower(trim($this->string('code')->toString())),
            'receipt_prefix' => Str::upper(trim($this->string('receipt_prefix')->toString())),
        ], fn (string $value): bool => $value !== ''));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $editedStore = $this->editedStore();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'receipt_prefix' => ['required', 'string', 'regex:/^[A-Z0-9]{2,10}$/'],
        ];

        if ($editedStore === null) {
            $rules['code'] = ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('stores', 'code')];
        }

        if ($editedStore === null || $editedStore->status->isAwaitingProvisioning()) {
            $rules['db_name'] = [
                'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique('stores', 'db_name')->ignore($editedStore),
                Rule::notIn([$this->centralDatabaseName()]),
            ];
        }

        return $rules + [
            'db_username' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'db_host' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama toko wajib diisi.',
            'receipt_prefix.required' => 'Prefiks nota wajib diisi.',
            'receipt_prefix.regex' => 'Prefiks nota 2–10 karakter, huruf besar atau angka.',
            'code.required' => 'Kode toko wajib diisi.',
            'code.min' => 'Kode toko minimal :min karakter.',
            'code.max' => 'Kode toko maksimal :max karakter.',
            'code.regex' => 'Kode toko hanya huruf kecil, angka, dan garis bawah, dan diawali huruf.',
            'code.unique' => 'Kode toko sudah dipakai.',
            'db_name.required' => 'Nama database wajib diisi.',
            'db_name.regex' => 'Nama database hanya huruf, angka, dan garis bawah.',
            'db_name.unique' => 'Database ini sudah dipakai toko lain.',
            'db_name.not_in' => 'Database pusat tidak boleh dipakai sebagai database toko.',
            'db_username.required' => 'User database wajib diisi.',
            'db_username.regex' => 'User database hanya huruf, angka, titik, garis bawah, atau strip.',
            'db_host.regex' => 'Host database tidak valid.',
        ];
    }

    private function editedStore(): ?Store
    {
        $store = $this->route('store');

        return $store instanceof Store ? $store : null;
    }

    private function centralDatabaseName(): string
    {
        return config('database.connections.'.config('tenancy.database.central_connection').'.database');
    }
}
