<?php

namespace App\Http\Requests\Tenant;

/**
 * The change to save, together with the fingerprint of the preview the admin looked at, so the save can refuse to
 * run when the prices moved in between.
 */
class ApplyBulkPriceRequest extends BulkPriceRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'preview_fingerprint' => ['required', 'string', 'size:64']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'preview_fingerprint.required' => 'Lihat pratinjaunya dulu sebelum menerapkan.',
            'preview_fingerprint.size' => 'Pratinjau tidak dikenal. Hitung ulang pratinjaunya, lalu terapkan.',
        ];
    }

    public function previewFingerprint(): string
    {
        return (string) $this->validated('preview_fingerprint');
    }
}
