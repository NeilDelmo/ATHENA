<?php

namespace App\Support;

class GADChecklistData
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{project_title: string, project_leader: string, verifier_name: string, verifier_role: string}
     */
    public static function fromValidated(array $validated): array
    {
        return [
            'project_title' => trim((string) $validated['project_title']),
            'project_leader' => trim((string) $validated['project_leader']),
            'verifier_name' => (string) config('gad_checklist.verifier.name'),
            'verifier_role' => (string) config('gad_checklist.verifier.role'),
        ];
    }
}
