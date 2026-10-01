<?php

use App\Services\ResearchHeadReportQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['database.default' => 'report_queue_test', 'database.connections.report_queue_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('topics', function (Blueprint $table) {
        $table->id();
        $table->integer('user_id');
        $table->string('title');
        $table->string('status');
        $table->timestamp('notice_to_proceed_issued_at')->nullable();
    });
    foreach (['project_progress_reports', 'project_narrative_reports'] as $name) {
        Schema::create($name, function (Blueprint $table) use ($name) {
            $table->id();
            $table->integer('topic_id');
            $table->string('submission_status')->default('submitted');
            $table->string('review_status')->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('created_at')->default('2026-09-01 12:00:00');
            if ($name === 'project_progress_reports') {
                $table->integer('supersedes_report_id')->nullable();
                $table->date('reporting_date')->default('2026-09-01');
            } else {
                $table->string('report_type')->default('progress');
                $table->date('submission_date')->default('2026-09-01');
            }
        });
    }
    DB::table('users')->insert(['id' => 1, 'name' => 'Faculty Example']);
    DB::table('topics')->insert([
        ['id' => 1, 'user_id' => 1, 'title' => 'Coastal Research', 'status' => 'approved', 'notice_to_proceed_issued_at' => '2026-08-01'],
        ['id' => 2, 'user_id' => 1, 'title' => 'Unissued Project', 'status' => 'approved', 'notice_to_proceed_issued_at' => null],
        ['id' => 3, 'user_id' => 1, 'title' => 'Pending Proposal', 'status' => 'pending', 'notice_to_proceed_issued_at' => '2026-08-01'],
    ]);
});

test('report queue excludes drafts, unissued projects and superseded submitted versions', function () {
    DB::table('project_progress_reports')->insert([
        ['id' => 1, 'topic_id' => 1, 'submission_status' => 'submitted', 'supersedes_report_id' => null],
        ['id' => 2, 'topic_id' => 1, 'submission_status' => 'submitted', 'supersedes_report_id' => 1],
        ['id' => 3, 'topic_id' => 1, 'submission_status' => 'prepared', 'supersedes_report_id' => 2],
        ['id' => 4, 'topic_id' => 2, 'submission_status' => 'submitted', 'supersedes_report_id' => null],
        ['id' => 5, 'topic_id' => 3, 'submission_status' => 'submitted', 'supersedes_report_id' => null],
    ]);
    DB::table('project_narrative_reports')->insert([
        ['id' => 1, 'topic_id' => 1, 'report_type' => 'terminal', 'submission_status' => 'submitted'],
        ['id' => 2, 'topic_id' => 1, 'report_type' => 'terminal', 'submission_status' => 'submitted'],
        ['id' => 3, 'topic_id' => 1, 'report_type' => 'terminal', 'submission_status' => 'prepared'],
        ['id' => 4, 'topic_id' => 1, 'report_type' => 'progress', 'submission_status' => 'submitted'],
        ['id' => 5, 'topic_id' => 2, 'report_type' => 'progress', 'submission_status' => 'submitted'],
    ]);
    $queue = app(ResearchHeadReportQueue::class);
    expect($queue->pendingCount())->toBe(3)
        ->and($queue->query('quarterly')->pluck('id')->all())->toBe([2])
        ->and($queue->query('terminal')->pluck('id')->all())->toBe([2])
        ->and($queue->query('progress')->pluck('id')->all())->toBe([4]);
});

test('report queue filters status and faculty or project search before paginating', function () {
    DB::table('project_progress_reports')->insert(['id' => 1, 'topic_id' => 1]);
    DB::table('project_narrative_reports')->insert(['id' => 1, 'topic_id' => 1, 'report_type' => 'terminal', 'review_status' => 'reviewed']);
    $queue = app(ResearchHeadReportQueue::class);
    expect($queue->query(status: 'pending', search: 'Coastal')->paginate(1)->total())->toBe(1)
        ->and($queue->query(status: 'reviewed', search: 'Faculty Example')->first()->report_type)->toBe('terminal')
        ->and($queue->query(search: 'Unmatched')->count())->toBe(0)
        ->and($queue->query(search: "' OR 1=1 --")->count())->toBe(0);
});
