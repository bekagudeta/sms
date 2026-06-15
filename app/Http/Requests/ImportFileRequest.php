<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a bulk import upload (ImportController::bulkImport).
 *
 * The frontend ImportModal posts: entity_type, file, mappings[] and skip_header.
 */
class ImportFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware already enforces authentication/permissions for imports.
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('imports.max_file_kb', 10240);
        $entityTypes = array_keys(config('imports.entities', []));

        return [
            'entity_type' => ['required', 'string', Rule::in($entityTypes)],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:'.$maxKb],
            'mappings' => ['sometimes', 'array'],
            'mappings.*' => ['nullable', 'string'],
            'skip_header' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'entity_type.required' => 'The import type is missing.',
            'entity_type.in' => 'The selected import type is not supported.',
            'file.required' => 'Please choose a file to import.',
            'file.mimes' => 'The file must be a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.max' => 'The file is too large to import.',
        ];
    }

    /**
     * Normalize the string "1"/"0" posted by the frontend into a real boolean
     * before validation runs.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('skip_header')) {
            $this->merge([
                'skip_header' => filter_var($this->input('skip_header'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
