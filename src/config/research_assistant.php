<?php

return [
    'document' => [
        'maximum_bytes' => (int) env('RESEARCH_ASSISTANT_DOCUMENT_MAX_BYTES', 25 * 1024 * 1024),
        'maximum_characters' => (int) env('RESEARCH_ASSISTANT_DOCUMENT_MAX_CHARACTERS', 24000),
        'maximum_pdf_pages' => (int) env('RESEARCH_ASSISTANT_DOCUMENT_MAX_PDF_PAGES', 40),
        'maximum_docx_xml_bytes' => (int) env('RESEARCH_ASSISTANT_DOCUMENT_MAX_DOCX_XML_BYTES', 12 * 1024 * 1024),
        'extraction_timeout' => (int) env('RESEARCH_ASSISTANT_DOCUMENT_EXTRACTION_TIMEOUT', 20),
        'pdftotext_binary' => env('PDFTOTEXT_BINARY', 'pdftotext'),
    ],

    'conversation_memory' => [
        'recent_messages' => 8,
        'refresh_batch' => 4,
        'maximum_summary_characters' => 4000,
        'maximum_source_characters' => 24000,
        'maximum_completion_tokens' => 700,
    ],
];
