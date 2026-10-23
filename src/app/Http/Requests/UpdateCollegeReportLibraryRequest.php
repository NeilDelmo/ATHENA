<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\CollegeReportLibrary;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class UpdateCollegeReportLibraryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:0'],
            'items' => ['present', 'array', 'list', 'max:2000'],
            'items.*' => ['required', 'array:id,source_id,parent_id,name', 'required_array_keys:id,source_id,parent_id,name'],
            'items.*.id' => ['required', 'string', 'max:200', 'distinct:ignore_case'],
            'items.*.source_id' => ['required', 'string', 'max:200'],
            'items.*.parent_id' => ['present', 'nullable', 'string', 'max:200'],
            'items.*.name' => ['required', 'string', 'max:80', 'not_regex:/[\\\\\\/\x00-\x1f]/'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $library = app(CollegeReportLibrary::class);
            $catalog = $library->catalog(now()->year, $this->validated());
            foreach ($this->validated('items') as $index => $item) {
                if (! $catalog->has($item['source_id']) || ($catalog->has($item['id']) ? $item['id'] !== $item['source_id'] : ! Str::isUuid($item['id']))) {
                    $validator->errors()->add("items.{$index}.source_id", 'Choose an available report or folder.');
                }
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $nodes = $library->nodes($catalog, $this->validated());
            $currentNodes = $library->nodes($catalog, $this->user()->report_library_workspace ?? []);
            $changed = [];
            foreach ($this->validated('items') as $item) {
                $current = $currentNodes->get($item['id']);
                if ($current === null || $current['parent_id'] !== $item['parent_id'] || $current['name'] !== $item['name']) {
                    $changed[$item['id']] = true;
                }
            }
            $names = [];
            foreach ($nodes as $node) {
                $parent = $node['parent_id'];
                if ($parent !== null && (! $nodes->has($parent) || $nodes[$parent]['kind'] !== 'folder')) {
                    $validator->errors()->add('items', 'Choose an existing destination folder.');

                    return;
                }
                $name = ($parent ?? 'root').'/'.mb_strtolower(trim($node['name']));
                if (isset($names[$name]) && (isset($changed[$node['id']]) || isset($changed[$names[$name]]))) {
                    $validator->errors()->add('items', 'An item with this name already exists in the destination.');

                    return;
                }
                $names[$name] = $node['id'];
                $visited = [$node['id'] => true];
                while ($parent !== null && $nodes->has($parent)) {
                    if (isset($visited[$parent]) || count($visited) > 50) {
                        $validator->errors()->add('items', 'A folder cannot contain itself or exceed 50 levels.');

                        return;
                    }
                    $visited[$parent] = true;
                    $parent = $nodes[$parent]['parent_id'];
                }
            }
        }];
    }
}
