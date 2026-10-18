<?php

namespace Database\Seeders;

use App\Actions\LinkLiteratureSourceToProposal;
use App\Actions\SaveLiteratureSource;
use App\Actions\SaveProposalDraftDocument;
use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VerifiedFloodLiteratureSeeder extends Seeder
{
    public const TITLE = 'IoT-Based Flood Monitoring and Early Warning System for Coastal Barangays';

    private const ORIGINAL_RRL = 'Recent studies associate well-designed interventions in disaster preparedness and environmental monitoring with measurable efficiency, quality, and satisfaction gains. Adoption depends on usability, trust, accessibility, and the readiness of the people who will use the system.';

    private const ORIGINAL_REFERENCES = 'Reyes, P. (2025). Low-cost sensor networks for flood-prone communities. Philippine Engineering Journal, 47(1), 12-29. Santos, M. (2025). Applied research methods for community-centered technology. Philippine Journal of Science, 154(2), 101-115.';

    public function run(
        SaveLiteratureSource $saveSource,
        LinkLiteratureSourceToProposal $linkSource,
        SaveProposalDraftDocument $saveDocument,
    ): void {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample literature may not be changed in production.');
        }

        DB::transaction(function () use ($saveSource, $linkSource, $saveDocument): void {
            $drafts = ProposalDraft::query()->with('owner')->where('project_title', self::TITLE)
                ->where('status', ProposalDraft::STATUS_DRAFT)->whereNull('topic_id')->lockForUpdate()->get();

            foreach ($drafts as $draft) {
                $document = $draft->documents()->where('document_type', 'detailed_proposal')
                    ->where('position', 0)->lockForUpdate()->first();
                $data = $document?->source_data ?? [];
                if ($document === null
                    || ($data['related_literature'] ?? '') !== self::ORIGINAL_RRL
                    || ($data['references'] ?? '') !== self::ORIGINAL_REFERENCES
                    || ! in_array($data['literature_citations'] ?? '', ['', '[]'], true)
                    || $draft->literatureSources()->exists()) {
                    continue;
                }

                $paragraphs = [];
                $references = [];
                $citations = [];
                foreach ($this->sources() as $index => $entry) {
                    $fingerprint = LiteratureSource::fingerprintFor($entry['doi'], $entry['title'], $entry['year']);
                    $source = LiteratureSource::query()->where('fingerprint', $fingerprint)->first();
                    if ($source !== null) {
                        foreach (['title', 'authors', 'venue', 'volume', 'issue', 'pages', 'publisher', 'url', 'publication_date', 'year'] as $field) {
                            $actual = match ($field) {
                                'year' => $source->publication_year,
                                'publication_date' => $source->publication_date?->toDateString(),
                                default => $source->getAttribute($field),
                            };
                            if ($actual !== ($entry[$field] ?? null)) {
                                throw new RuntimeException('Existing source metadata differs from the verified sample. Review DOI '.$entry['doi'].' before retrying.');
                            }
                        }
                    }
                    $source ??= $saveSource->handle($entry, $draft->owner)['source'];
                    $link = $linkSource->handle($draft, $source, $draft->owner, $entry['note'], 'full_text')['link'];
                    $number = $index + 1;
                    $paragraphs[] = '<p>'.e($entry['note']).'<span data-proposal-citation="'.$link->id.'"> ['.$number.']</span></p>';
                    $references[] = '<p data-proposal-managed-reference="'.$link->id.'">'.e($link->referenceDraft($number)).'</p>';
                    $citations[] = [
                        'id' => 'verified-flood-'.$link->id, 'source_link_id' => $link->id,
                        'literature_source_id' => $source->id, 'field' => 'related_literature',
                        'selected_text' => '', 'locator' => '', 'created_at' => now()->toIso8601String(),
                    ];
                }
                $paragraphs[] = '<p>Together, these studies connect measurement validation with reliable communication. They do not establish accuracy, warning effectiveness, or community response for the proposed coastal barangays. The local study must evaluate its own sensor readings, alert delivery and delays, outages, and users\' understanding of warnings. Site selection, reference measurements, alert thresholds, and the community evaluation protocol still require researcher specification before implementation.</p>';

                $saveDocument->handle(
                    $draft, $draft->owner, 'detailed_proposal', 0,
                    $draft->currentDocumentVersion('detailed_proposal', 0, $document),
                    ['source_data' => [
                        ...$data, 'related_literature' => implode('', $paragraphs),
                        'references' => implode('', $references),
                        'literature_citations' => json_encode($citations, JSON_THROW_ON_ERROR),
                    ]],
                    changeNote: 'Replace unverified sample references with publisher-verified flood monitoring literature and linked citations.',
                    forceCheckpoint: true,
                    preserveCurrentWorkingCopy: true,
                );
                $this->command?->info('Updated verified literature for sample draft '.$draft->id.'.');
            }
        });
    }

    /** @return list<array<string, mixed>> */
    private function sources(): array
    {
        // Publisher records and full text checked on 2026-10-17; these are example evidence, not local project results.
        return [
            [
                'title' => 'Low-Cost Real-Time Water Level Monitoring Network for Falling Water River Watershed: A Case Study',
                'authors' => 'A. Kalyanapu; C. Owusu; T. Wright; T. Datta',
                'year' => 2023, 'publication_date' => '2023-02-26',
                'venue' => 'Geosciences', 'volume' => '13', 'issue' => '3', 'pages' => 'Art. no. 65', 'publisher' => 'MDPI',
                'doi' => '10.3390/geosciences13030065', 'url' => 'https://www.mdpi.com/2076-3263/13/3/65',
                'full_text_url' => 'https://www.mdpi.com/2076-3263/13/3/65/pdf',
                'source' => 'Publisher record', 'type' => 'journal-article', 'is_open_access' => true,
                'note' => 'Kalyanapu and colleagues (2023) developed a low-cost ultrasonic water-level network with cellular communication for the Falling Water River watershed in Tennessee. Comparison with a USGS streamgage included long-term monitoring and storm events. The study illustrates why flood sensors should be evaluated against reference measurements under changing water conditions. Its watershed findings do not establish the accuracy of a different sensor installation in Philippine coastal barangays.',
            ],
            [
                'title' => 'Laboratory performance assessment of low-cost water level sensor for field monitoring in the tropics',
                'authors' => 'N. Ding; Q. Zhu; F. Cherqui; N. Walcker; J.-L. Bertrand-Krajewski; P. Hamel',
                'year' => 2025, 'publication_date' => '2025-05-01',
                'venue' => 'Water Research X', 'volume' => '27', 'pages' => 'Art. no. 100298', 'publisher' => 'Elsevier',
                'doi' => '10.1016/j.wroa.2024.100298', 'url' => 'https://www.sciencedirect.com/science/article/pii/S2589914724000872',
                'full_text_url' => 'https://www.sciencedirect.com/science/article/pii/S2589914724000872',
                'source' => 'Publisher record', 'type' => 'journal-article', 'is_open_access' => true,
                'note' => 'Ding and colleagues (2025) assessed low-cost pressure water-level sensors through temperature-controlled laboratory tests and a field experiment in Singapore. Their findings emphasize individual device calibration and limitations under shallow-water, temperature-changing, and wet/dry conditions. Because pressure and ultrasonic devices use different measurement principles, the reported performance cannot be transferred directly to an ultrasonic flood-monitoring prototype. The proposed deployment therefore needs calibration and field checks for the devices and conditions actually selected.',
            ],
            [
                'title' => 'Recent Advances in Internet of Things Solutions for Early Warning Systems: A Review',
                'authors' => 'M. Esposito; L. Palma; A. Belli; L. Sabbatini; P. Pierleoni',
                'year' => 2022, 'publication_date' => '2022-03-09',
                'venue' => 'Sensors', 'volume' => '22', 'issue' => '6', 'pages' => 'Art. no. 2124', 'publisher' => 'MDPI',
                'doi' => '10.3390/s22062124', 'url' => 'https://www.mdpi.com/1424-8220/22/6/2124',
                'full_text_url' => 'https://www.mdpi.com/1424-8220/22/6/2124/pdf',
                'source' => 'Publisher record', 'type' => 'journal-article', 'is_open_access' => true,
                'note' => 'Esposito and colleagues (2022) reviewed IoT early-warning architectures across several hazards, including floods. The review identifies sensing, communication, and application layers and discusses trade-offs involving coverage, delay, energy use, and fault tolerance. It also highlights insufficient attention to sensor and communication failures. This broader architectural evidence supports evaluating the warning delivery chain as well as water-level measurements, but it does not demonstrate community alert comprehension or the effectiveness of the proposed local system.',
            ],
        ];
    }
}
