<?php

use App\Services\ConferenceScraperService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(12, 0));
    Cache::flush();
    Http::preventStrayRequests();
});

function conferenceWikiFixture(string $title, int $id = 1, string $location = 'Manila, Philippines', string $event = 'Dec 12, 2026', string $deadline = 'Nov 10, 2026'): string
{
    return '<table><tr><td><a href="/cfp/servlet/event.showcfp?eventid='.$id.'">'.$title.'</a></td><td>'.$title.'</td></tr>'
        .'<tr><td>'.$event.'</td><td>'.$location.'</td><td>'.$deadline.'</td></tr></table>';
}

function conferenceEasyChairFixture(string $rows): string
{
    return '<table><thead><tr><th>Acronym</th><th>Name</th><th>Location</th><th>Submission<br/>Deadline</th><th>Start Date</th><th>Topics</th></tr></thead><tbody>'.$rows.'</tbody></table>';
}

function conferenceEasyChairRow(string $id, string $title, string $topics = 'coastal monitoring', string $location = 'Singapore'): string
{
    return '<tr><td><a href="/cfp/'.$id.'">'.$id.'</a></td><td>'.$title.'</td><td>'.$location.'</td>'
        .'<td>Nov 20, 2026</td><td>Dec 15, 2026</td><td><a href="/cfp/topic?tid=1">'.$topics.'</a></td></tr>';
}

test('conference discovery combines real CFP sources and ranks title matches above topic-only matches', function () {
    Http::fake([
        'www.wikicfp.com/*' => Http::response(conferenceWikiFixture('Coastal monitoring conference')),
        'easychair.org/cfp/' => Http::response(conferenceEasyChairFixture(conferenceEasyChairRow('MARINE26', 'Marine Science Forum'))),
    ]);

    $service = app(ConferenceScraperService::class);
    $data = $service->search('coastal monitoring');

    expect(array_column($data['results'], 'source'))->toBe(['WikiCFP', 'EasyChair'])
        ->and($data['results'][1]['submission_deadline'])->toBe('2026-11-20')
        ->and($data['results'][1]['event_starts_on'])->toBe('2026-12-15')
        ->and($data['results'][1]['matched_keywords'])->toBe(['coastal', 'monitoring'])
        ->and($data['results'][1]['match_reasons'])->not->toBeEmpty()
        ->and($data['sources'])->toBe(['WikiCFP', 'EasyChair'])
        ->and($service->allSourcesFailed())->toBeFalse();
    $service->search('  COASTAL   monitoring ');
    Http::assertSentCount(2);
});

test('conference discovery follows relevant WikiCFP categories into a second page without following external links', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'easychair.org')) {
            return Http::response(conferenceEasyChairFixture(''));
        }
        if (str_contains($request->url(), 'tool.search')) {
            return Http::response('<table></table><a href="/cfp/call?conference=coastal%20monitoring">coastal monitoring</a>'
                .'<a href="https://attacker.example/cfp/call?conference=coastal">coastal</a>');
        }
        if (str_contains($request->url(), 'page=2')) {
            return Http::response(conferenceWikiFixture('Coastal monitoring from the next page', 42));
        }

        return Http::response(conferenceWikiFixture('Nursing symposium', 5));
    });

    $data = app(ConferenceScraperService::class)->search('coastal monitoring');
    expect(array_column($data['results'], 'title'))->toBe(['Coastal monitoring from the next page']);
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'attacker.example'));
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'page=2'));
});

test('conference matching understands acronyms without matching fragments inside unrelated words', function () {
    Http::fake([
        'www.wikicfp.com/*' => Http::response(conferenceWikiFixture('Chair design and painting')),
        'easychair.org/cfp/' => Http::response(conferenceEasyChairFixture(conferenceEasyChairRow('AI26', 'Artificial Intelligence Forum', 'machine learning'))),
    ]);

    $results = app(ConferenceScraperService::class)->search('AI research')['results'];
    expect($results)->toHaveCount(1)->and($results[0]['source'])->toBe('EasyChair');
});

test('conference discovery discards past events and deduplicates tracking variants and matching source listings', function () {
    $wiki = conferenceWikiFixture('Coastal monitoring conference 2026', 1, 'Singapore', 'Dec 15, 2026')
        .str_replace('eventid=1', 'eventid=1&amp;copyownerid=2', conferenceWikiFixture('Coastal monitoring conference 2026', 1, 'Singapore', 'Dec 15, 2026'))
        .conferenceWikiFixture('Coastal monitoring old event', 2, 'Manila', 'Oct 1, 2026', '');
    Http::fake([
        'www.wikicfp.com/*' => Http::response($wiki),
        'easychair.org/cfp/' => Http::response(conferenceEasyChairFixture(conferenceEasyChairRow('COAST26', 'Coastal monitoring conference 2026'))),
    ]);

    $results = app(ConferenceScraperService::class)->search('coastal monitoring')['results'];
    expect($results)->toHaveCount(1);
});

test('conference discovery preserves usable results when another source fails and retries that source soon', function () {
    Http::fake([
        'www.wikicfp.com/*' => Http::sequence()->push('', 503)->push(conferenceWikiFixture('Coastal monitoring new conference')),
        'easychair.org/cfp/' => Http::response(conferenceEasyChairFixture(conferenceEasyChairRow('COAST26', 'Coastal monitoring conference'))),
    ]);
    $service = app(ConferenceScraperService::class);
    $data = $service->search('coastal monitoring');
    expect($data['results'])->toHaveCount(1)
        ->and($data['failed_sources'])->toBe(['WikiCFP'])
        ->and($data['warnings'])->not->toBeEmpty()
        ->and($service->allSourcesFailed())->toBeFalse();

    $this->travel(2)->minutes();
    expect($service->search('coastal monitoring')['failed_sources'])->toBeEmpty();
});

test('conference discovery reports unavailable for source outages and challenge pages rather than no matches', function () {
    Http::fake([
        'www.wikicfp.com/*' => Http::failedConnection(),
        'easychair.org/cfp/' => Http::response('<html><title>Please sign in</title><p>Access denied</p></html>'),
    ]);
    $service = app(ConferenceScraperService::class);
    $data = $service->search('coastal monitoring');
    expect($data['results'])->toBeEmpty()
        ->and($data['failed_sources'])->toBe(['WikiCFP', 'EasyChair'])
        ->and($service->allSourcesFailed())->toBeTrue();
});

test('generic conference queries request specific research terms without returning arbitrary listings', function () {
    $data = app(ConferenceScraperService::class)->search('research study conference');
    expect($data['results'])->toBeEmpty()->and($data['warnings'])->not->toBeEmpty();
    Http::assertNothingSent();
});

test('conference relevance rejects substring matches and does not treat edition years as research topics', function () {
    Http::fake([
        'www.wikicfp.com/*' => Http::response(conferenceWikiFixture('Chair design and painting', 1)
            .conferenceWikiFixture('Artificial Intelligence in Education', 2)
            .conferenceWikiFixture('Nursing Forum 2027', 3)),
        'easychair.org/cfp/' => Http::response(conferenceEasyChairFixture('')),
    ]);

    $results = app(ConferenceScraperService::class)->search('AI research 2027')['results'];
    expect(array_column($results, 'title'))->toBe(['Artificial Intelligence in Education']);
});

test('conference matching preserves singular and plural research terms with whole word boundaries', function () {
    Http::fake([
        'www.wikicfp.com/*' => Http::response(conferenceWikiFixture('Wireless Sensors and Networks')
            .conferenceWikiFixture('Wireless networking marketplace', 2)),
        'easychair.org/cfp/' => Http::response(conferenceEasyChairFixture('')),
    ]);

    $results = app(ConferenceScraperService::class)->search('wireless sensor network')['results'];
    expect(array_column($results, 'title'))->toBe(['Wireless Sensors and Networks'])
        ->and($results[0]['matched_keywords'])->toBe(['wireless', 'sensor', 'network'])
        ->and($results[0]['relevance_score'])->toBe(100);
});

test('different conference titles sharing a subtitle and event date remain separate matches', function () {
    Http::fake([
        'www.wikicfp.com/*' => Http::response(conferenceWikiFixture('Coastal systems: Research symposium')
            .conferenceWikiFixture('Marine systems: Research symposium', 2)),
        'easychair.org/cfp/' => Http::response(conferenceEasyChairFixture('')),
    ]);

    $results = app(ConferenceScraperService::class)->search('systems symposium')['results'];
    expect($results)->toHaveCount(2);
});
