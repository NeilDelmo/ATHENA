<?php

namespace App\Support;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;

class ProposalWorkspacePeople
{
    /** @return list<array{key: string, name: string, email: string, contact: string, title: string, department: string, avatar: string, college: string, linked: bool, owner: bool}> */
    public function forDraft(ProposalDraft $draft): array
    {
        $draft->loadMissing([
            'owner:id,name,email,contact_number,professional_title,department,avatar,college',
            'members.user:id,name,email,contact_number,professional_title,department,avatar,college',
        ]);

        return collect([[
            'key' => 'owner-'.$draft->owner->getKey(),
            'name' => $draft->owner->name,
            'email' => mb_strtolower(trim($draft->owner->email)),
            'contact' => (string) ($draft->owner->contact_number ?? ''),
            'title' => (string) ($draft->owner->professional_title ?? ''),
            'department' => (string) ($draft->owner->department ?? ''),
            'avatar' => (string) ($draft->owner->avatar ?? ''),
            'college' => (string) ($draft->owner->college ?? ''),
            'linked' => true,
            'owner' => true,
        ]])
            ->concat($draft->members->map(
                fn (ProposalDraftMember $member): array => [
                    'key' => 'member-'.$member->getKey(),
                    'name' => $member->user?->name ?? $member->name,
                    'email' => mb_strtolower(trim($member->user?->email ?? $member->email)),
                    'contact' => (string) ($member->user?->contact_number ?? ''),
                    'title' => (string) ($member->user?->professional_title ?? ''),
                    'department' => (string) ($member->user?->department ?? ''),
                    'avatar' => (string) ($member->user?->avatar ?? ''),
                    'college' => (string) ($member->user?->college ?? ''),
                    'linked' => $member->isLinked(),
                    'owner' => false,
                ],
            ))
            ->unique(fn (array $person): string => $person['email'])
            ->values()
            ->all();
    }
}
