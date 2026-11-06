<?php
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'capacity_athena') { throw new RuntimeException('Wrong synthetic database'); }
require_once __DIR__.'/seed-web.php';
$context=json_decode(file_get_contents(storage_path('app/private/capacity-context.json')),true,512,JSON_THROW_ON_ERROR);
$draft=App\Models\ProposalDraft::with('documents','owner','researchCall')->findOrFail($context['draft_id']);
$readiness=app(App\Support\ProposalDraftReadiness::class);
echo json_encode(['draft'=>$draft->getAttributes(),'errors'=>$readiness->errors($draft),'blockers'=>$readiness->blockers($draft),'current_sources'=>$draft->documents->mapWithKeys(fn($d)=>[$d->document_type=>$d->source_data])->all(),'seed_sources'=>capacityWebDraftSources($draft->owner)],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
