<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManageProjectDisseminationRequest;
use App\Models\ProjectConference;
use App\Models\ResearcherProfile;
use App\Models\ResearchPublication;
use App\Models\TopicProposal;
use App\Services\ConferenceScraperService;
use App\Services\ResearchPublicationDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectDisseminationController extends Controller
{
    public function show(ManageProjectDisseminationRequest $request, TopicProposal $topic, ConferenceScraperService $conferences): View
    {
        $canEdit = $request->user()->isUsingWorkspace('faculty_researcher') && $topic->isAccessibleTo($request->user());
        $abstract = $topic->narrativeReports()->where('report_type', 'terminal')->latest('id')->first()?->terminal_data['abstract'] ?? $topic->description;

        return view('research.dissemination', [
            'topic' => $topic,
            'canEdit' => $canEdit,
            'projectAbstract' => Str::limit(strip_tags((string) $abstract), 1500),
            'suggestedQuery' => $conferences->suggestedQuery($topic->title),
            'conferences' => $topic->conferences()->latest()->paginate(10, ['*'], 'conference_page'),
            'publications' => $topic->publications()->with('user:id,name')->latest('research_publications.created_at')->paginate(10, ['*'], 'publication_page')->fragment('publications'),
            'profile' => $canEdit ? ResearcherProfile::where('user_id', $request->user()->id)->first() : null,
            'ownPublications' => $canEdit ? ResearchPublication::where('user_id', $request->user()->id)->latest()->limit(100)->get() : collect(),
        ]);
    }

    public function authors(ManageProjectDisseminationRequest $request, TopicProposal $topic, ResearchPublicationDiscoveryService $discovery): JsonResponse
    {
        return response()->json($discovery->authors($request->validated('query'), $request->validated('institution')));
    }

    public function profile(ManageProjectDisseminationRequest $request, TopicProposal $topic, ResearchPublicationDiscoveryService $discovery): JsonResponse
    {
        $data = ['scholar_url' => $request->validated('scholar_url')];
        if ($id = $request->validated('author_id')) {
            $author = $discovery->author($id);
            $data += [
                'openalex_id' => $author['id'], 'display_name' => $author['name'],
                'affiliation' => $author['affiliation'], 'orcid' => $author['orcid'],
                'confirmed_at' => now(),
            ];
        }
        $profile = ResearcherProfile::updateOrCreate(['user_id' => $request->user()->id], $data);

        return response()->json(['profile' => $profile->only(['openalex_id', 'display_name', 'affiliation', 'orcid', 'scholar_url'])]);
    }

    public function papers(ManageProjectDisseminationRequest $request, TopicProposal $topic, ResearchPublicationDiscoveryService $discovery): JsonResponse
    {
        $authorId = $request->validated('author_id') ?: $this->confirmedProfile($request)->openalex_id;

        return response()->json($discovery->papers($authorId, (int) ($request->validated('page') ?? 1)));
    }

    public function doi(ManageProjectDisseminationRequest $request, TopicProposal $topic, ResearchPublicationDiscoveryService $discovery): JsonResponse
    {
        return response()->json(['paper' => $discovery->doi($request->validated('doi'))]);
    }

    public function import(ManageProjectDisseminationRequest $request, TopicProposal $topic, ResearchPublicationDiscoveryService $discovery): JsonResponse
    {
        if ($doi = $request->validated('lookup_doi')) {
            $paper = $discovery->doi($doi);
            if ($paper['openalex_id'] !== $request->validated('work_id')) {
                throw ValidationException::withMessages(['publication' => 'The selected paper does not match the DOI lookup. Search again.']);
            }
        } else {
            $paper = $discovery->work($request->validated('work_id'), $this->confirmedProfile($request)->openalex_id);
        }
        $publication = ResearchPublication::firstOrCreate(
            ['user_id' => $request->user()->id, 'fingerprint' => ResearchPublication::fingerprint($paper)],
            [...$paper, 'confirmed_at' => now()],
        );
        $publication->topics()->syncWithoutDetaching([$topic->id]);

        return response()->json(['message' => 'Publication confirmed and linked to this project.', 'publication_id' => $publication->id]);
    }

    public function manual(ManageProjectDisseminationRequest $request, TopicProposal $topic): RedirectResponse
    {
        $data = $request->safe()->except('confirmed');
        $publication = ResearchPublication::firstOrCreate(
            ['user_id' => $request->user()->id, 'fingerprint' => ResearchPublication::fingerprint($data)],
            [...$data, 'confirmed_at' => now(), 'source' => 'Researcher entry'],
        );
        $publication->topics()->syncWithoutDetaching([$topic->id]);

        return redirect()->to(route('research.dissemination.show', $topic).'#publications')->with('success', 'Publication saved and linked. Researcher-entered metadata is labelled separately from indexed records.');
    }

    public function link(ManageProjectDisseminationRequest $request, TopicProposal $topic): RedirectResponse
    {
        $publication = ResearchPublication::where('user_id', $request->user()->id)->findOrFail($request->validated('publication_id'));
        $publication->topics()->syncWithoutDetaching([$topic->id]);

        return redirect()->to(route('research.dissemination.show', $topic).'#publications')->with('success', 'Your saved publication is linked to this project.');
    }

    public function unlink(ManageProjectDisseminationRequest $request, TopicProposal $topic, ResearchPublication $publication): RedirectResponse
    {
        $publication->topics()->detach($topic->id);

        return redirect()->to(route('research.dissemination.show', $topic).'#publications')->with('success', 'Publication unlinked from this project. It remains in your saved publications.');
    }

    public function searchConferences(ManageProjectDisseminationRequest $request, TopicProposal $topic, ConferenceScraperService $scraper): JsonResponse
    {
        $data = $scraper->search($request->validated('query'));
        if ($scraper->allSourcesFailed()) {
            return response()->json(['message' => 'Conference discovery is unavailable right now. Your shortlist is safe. You can still add a conference from its official website.'], 503);
        }
        $data['results'] = collect($data['results'])
            ->filter(fn (array $item): bool => ! $request->validated('scope') || $item['scope'] === $request->validated('scope'))
            ->filter(fn (array $item): bool => ! $request->boolean('open_only') || $item['deadline_status'] !== 'closed')
            ->map(function (array $item) use ($request, $topic): array {
                $key = hash('sha256', $item['url'].Str::random(20));
                Cache::put($this->candidateCacheKey($request, $topic, $key), $item, now()->addHours(2));

                return [...$item, 'candidate_key' => $key];
            })->values()->all();

        return response()->json($data);
    }

    public function storeConference(ManageProjectDisseminationRequest $request, TopicProposal $topic): RedirectResponse
    {
        $data = $request->safe()->except('candidate_key');
        if ($key = $request->validated('candidate_key')) {
            $candidate = Cache::get($this->candidateCacheKey($request, $topic, $key));
            if (! is_array($candidate)) {
                throw ValidationException::withMessages(['conference' => 'This search result expired. Search again before saving it.']);
            }
            $data = [...$data, 'title' => $candidate['title'], 'url' => $candidate['url'], 'source' => $candidate['source'], 'source_checked_at' => $candidate['source_checked_at']];
        }
        $url = $data['url'];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $identity = parse_url($url, PHP_URL_HOST) === 'www.wikicfp.com' && isset($query['eventid'])
            ? 'wikicfp:'.$query['eventid'] : rtrim($url, '/');
        $conference = $topic->conferences()->firstOrCreate(
            ['fingerprint' => hash('sha256', $identity)],
            [...$data, 'added_by' => $request->user()->id],
        );

        return back()->with('success', $conference->wasRecentlyCreated ? 'Conference added to the project shortlist.' : 'This conference is already in the project shortlist.');
    }

    public function updateConference(ManageProjectDisseminationRequest $request, TopicProposal $topic, ProjectConference $conference): RedirectResponse
    {
        $conference->update($request->safe()->except(['candidate_key', 'url']));

        return back()->with('success', 'Conference details and submission progress saved.');
    }

    private function confirmedProfile(ManageProjectDisseminationRequest $request): ResearcherProfile
    {
        $profile = ResearcherProfile::where('user_id', $request->user()->id)->whereNotNull('openalex_id')->whereNotNull('confirmed_at')->first();
        if (! $profile) {
            throw ValidationException::withMessages(['profile' => 'Find and confirm your author profile first, or use a DOI lookup.']);
        }

        return $profile;
    }

    private function candidateCacheKey(ManageProjectDisseminationRequest $request, TopicProposal $topic, string $key): string
    {
        return 'conference-candidate:'.$request->user()->id.':'.$topic->id.':'.$key;
    }
}
