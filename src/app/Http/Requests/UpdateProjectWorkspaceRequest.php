<?php

namespace App\Http\Requests;

use App\Models\TopicProposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TopicProposal::class) ?? false;
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
            'folders' => ['present', 'array', 'list', 'max:200'],
            'folders.*' => ['required', 'array:id,name,parent_id', 'required_array_keys:id,name,parent_id'],
            'folders.*.id' => ['required', 'uuid', 'distinct:ignore_case'],
            'folders.*.name' => ['required', 'string', 'max:80'],
            'folders.*.parent_id' => ['present', 'nullable', 'uuid'],
            'assignments' => ['present', 'array', 'list', 'max:10000'],
            'assignments.*' => ['required', 'array:project_id,folder_id', 'required_array_keys:project_id,folder_id'],
            'assignments.*.project_id' => ['required', 'integer', 'min:1', 'distinct'],
            'assignments.*.folder_id' => ['required', 'uuid'],
            'view' => ['required', Rule::in(['grid', 'list'])],
            'grouping' => ['required', Rule::in(['year', 'none'])],
            'sort' => ['required', Rule::in(['attention', 'title', 'newest', 'oldest'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $folders = $this->input('folders');
        if (is_array($folders)) {
            $this->merge(['folders' => array_map(function (mixed $folder): mixed {
                if (! is_array($folder)) {
                    return $folder;
                }

                foreach (['id', 'parent_id'] as $key) {
                    if (isset($folder[$key]) && is_string($folder[$key])) {
                        $folder[$key] = strtolower($folder[$key]);
                    }
                }
                if (isset($folder['name']) && is_string($folder['name'])) {
                    $folder['name'] = trim($folder['name']);
                }

                return $folder;
            }, $folders)]);
        }

        $assignments = $this->input('assignments');
        if (is_array($assignments)) {
            $this->merge(['assignments' => array_map(function (mixed $assignment): mixed {
                if (is_array($assignment) && isset($assignment['folder_id']) && is_string($assignment['folder_id'])) {
                    $assignment['folder_id'] = strtolower($assignment['folder_id']);
                }

                return $assignment;
            }, $assignments)]);
        }
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $key) {
                $validator->errors()->add($key, 'This field is not part of your project workspace.');
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $folders = collect($this->input('folders'))->keyBy('id');
            $siblingNames = [];
            foreach ($this->input('folders') as $index => $folder) {
                $parentId = $folder['parent_id'];
                if ($parentId !== null && ! $folders->has($parentId)) {
                    $validator->errors()->add("folders.{$index}.parent_id", 'The parent folder does not exist.');
                }
                $nameKey = ($parentId ?? '').'\0'.mb_strtolower($folder['name']);
                if (isset($siblingNames[$nameKey])) {
                    $validator->errors()->add("folders.{$index}.name", 'A folder with this name already exists here.');
                }
                $siblingNames[$nameKey] = true;

                $visited = [$folder['id'] => true];
                while ($parentId !== null && $folders->has($parentId)) {
                    if (isset($visited[$parentId])) {
                        $validator->errors()->add('folders', 'Folders cannot be nested inside themselves.');
                        break;
                    }
                    $visited[$parentId] = true;
                    $parentId = $folders->get($parentId)['parent_id'];
                }
            }

            $assignments = $this->input('assignments');
            $projects = TopicProposal::withIssuedNotice()->whereKey(array_column($assignments, 'project_id'))
                ->get(['id', 'user_id', 'status', 'project_status', 'notice_to_proceed_issued_at'])->keyBy('id');
            foreach ($assignments as $index => $assignment) {
                if (! $folders->has($assignment['folder_id'])) {
                    $validator->errors()->add("assignments.{$index}.folder_id", 'The destination folder does not exist.');
                }
                $project = $projects->get($assignment['project_id']);
                if ($project === null || Gate::forUser($this->user())->denies('view', $project)) {
                    $validator->errors()->add("assignments.{$index}.project_id", 'Choose a project you can access with an issued Notice to Proceed.');
                }
            }
        }];
    }
}
