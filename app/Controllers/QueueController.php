<?php

namespace App\Controllers;

use App\Core\Acl;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\MasterData;
use App\Models\QueueNote;
use App\Models\QueueStatusHistory;
use App\Models\Role;
use App\Models\SalesQueue;
use App\Models\User;

class QueueController extends Controller
{
    public function index(Request $request): void
    {
        $scopeSalesId = $this->scopeSalesId();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', ''),
            'priority' => $request->input('priority', ''),
            'survey_status_id' => $request->input('survey_status_id', ''),
            'stage_id' => $request->input('stage_id', ''),
            'sales_id' => $request->input('sales_id', ''),
            'overdue' => $request->input('overdue') ? true : false,
            'include_closed' => $request->input('include_closed') ? true : false,
            'sort' => $request->input('sort', 'queue_number'),
            'dir' => $request->input('dir', 'asc'),
            'page' => (int) $request->input('page', 1),
        ];

        if ($scopeSalesId !== null) {
            $filters['scope_sales_id'] = $scopeSalesId;
        }

        $result = SalesQueue::search($filters);
        $salesRoleId = $this->salesRoleId();

        $this->view('queue/index', [
            'pageTitle' => 'Antrian Sales',
            'queues' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'filters' => $filters,
            'dashboardCounts' => SalesQueue::dashboardCounts($scopeSalesId),
            'statusMap' => MasterData::allAsMap('queue_statuses'),
            'priorityMap' => MasterData::allAsMap('priorities'),
            'surveyStatusMap' => MasterData::allAsMap('survey_statuses'),
            'stageMap' => MasterData::allAsMap('queue_stages'),
            'salesUsers' => $salesRoleId ? User::activeByRole($salesRoleId) : [],
            'canManage' => Acl::can('queue.manage'),
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $queue = $this->findAuthorized((int) $params['id']);

        $this->view('queue/show', [
            'pageTitle' => 'Antrian #' . $queue['queue_number'],
            'queue' => $queue,
            'timeline' => $this->buildTimeline((int) $queue['id']),
            'statusMap' => MasterData::allAsMap('queue_statuses'),
            'priorityMap' => MasterData::allAsMap('priorities'),
            'surveyStatusMap' => MasterData::allAsMap('survey_statuses'),
            'stageMap' => MasterData::allAsMap('queue_stages'),
            'estimators' => User::activeEstimators(),
            'surveyors' => User::activeSurveyors(),
            'salesUsers' => ($id = $this->salesRoleId()) ? User::activeByRole($id) : [],
            'canManage' => Acl::can('queue.manage'),
            'canOperate' => $this->canOperate($queue),
            'canReassign' => Acl::can('queue.manage'),
        ]);
    }

    public function addNote(Request $request, array $params): void
    {
        $queue = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/queue/' . $queue['id']);

            return;
        }

        if (!$this->canOperate($queue)) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), ['note' => 'required|max:2000']);
        if ($validator->fails()) {
            Session::flash('error', 'Catatan wajib diisi.');
            $this->redirect('/queue/' . $queue['id']);

            return;
        }

        $note = trim((string) $request->input('note'));
        QueueNote::add((int) $queue['id'], Auth::id(), $note);

        AuditLogger::log((int) Auth::id(), 'queue_note_added', 'queue', (int) $queue['id'], null, ['note' => $note]);

        Session::flash('success', 'Catatan berhasil ditambahkan.');
        $this->redirect('/queue/' . $queue['id']);
    }

    // ------------------------------------------------------------------
    // Shared helpers (mirrors LeadController's row-level scoping pattern)
    // ------------------------------------------------------------------

    public function findAuthorized(int $id): array
    {
        $queue = SalesQueue::withRelations($id);

        if ($queue === null) {
            $this->abort(404);
        }

        $scopeSalesId = $this->scopeSalesId();
        if ($scopeSalesId !== null && (int) $queue['sales_id'] !== $scopeSalesId) {
            $this->abort(403, 'Anda hanya dapat mengakses antrian yang ditugaskan kepada Anda.');
        }

        return $queue;
    }

    private function scopeSalesId(): ?int
    {
        return (Acl::hasRole('sales') && !Acl::can('queue.manage')) ? Auth::id() : null;
    }

    public function isReadOnlyForSales(array $queue): bool
    {
        $scopeSalesId = $this->scopeSalesId();

        return $scopeSalesId !== null && (int) $queue['sales_id'] !== $scopeSalesId;
    }

    /**
     * Who may change this queue item's status/priority/deadline/follow-up/
     * notes: queue.manage holders, or the Sales rep it's actually assigned
     * to. A Manager/Engineer with only queue.view must stay read-only even
     * though they aren't "sales-scoped" (they can see every row).
     */
    private function canOperate(array $queue): bool
    {
        return Acl::can('queue.manage')
            || (Acl::hasRole('sales') && (int) $queue['sales_id'] === Auth::id());
    }

    private function salesRoleId(): ?int
    {
        static $id = null;
        if ($id === null) {
            $role = Role::findBySlug('sales');
            $id = $role ? (int) $role['id'] : 0;
        }

        return $id;
    }

    private function buildTimeline(int $queueId): array
    {
        $statusEvents = array_map(function ($row) {
            return [
                'type' => 'status',
                'created_at' => $row['created_at'],
                'actor' => $row['changed_by_name'] ?? 'Sistem',
                'from' => $row['from_status'],
                'to' => $row['to_status'],
                'notes' => $row['notes'],
            ];
        }, QueueStatusHistory::forQueue($queueId));

        $noteEvents = array_map(function ($row) {
            return [
                'type' => 'note',
                'created_at' => $row['created_at'],
                'actor' => $row['user_name'] ?? 'Sistem',
                'note' => $row['note'],
            ];
        }, QueueNote::forQueue($queueId));

        // 'queue_created' is intentionally excluded — it already appears as
        // the opening entry in $statusEvents above (from_status = null).
        $actionLabels = [
            'queue_priority_changed' => 'Urgensi diubah',
            'queue_assigned' => 'Penugasan sales diubah',
            'queue_deadline_changed' => 'Deadline diubah',
            'queue_followup_date_changed' => 'Tanggal follow up diubah',
            'queue_task_name_changed' => 'Nama tugas diubah',
            'queue_survey_status_changed' => 'Status survey diubah',
            'queue_stage_changed' => 'Prioritas diubah',
            'queue_estimator_changed' => 'Estimator diubah',
            'queue_surveyor_changed' => 'Surveyor diubah',
            'queue_notes_field_changed' => 'Catatan tambahan diubah',
            'queue_engineering_start_date_changed' => 'Tanggal mulai engineering diubah',
            'queue_engineering_end_date_changed' => 'Tanggal akhir engineering diubah',
            'queue_procurement_start_date_changed' => 'Tanggal mulai procurement diubah',
            'queue_procurement_end_date_changed' => 'Tanggal akhir procurement diubah',
        ];

        $auditEvents = array_values(array_filter(array_map(function ($row) use ($actionLabels) {
            if (!isset($actionLabels[$row['action']])) {
                return null;
            }

            return [
                'type' => 'audit',
                'created_at' => $row['created_at'],
                'actor' => $row['user_name'] ?? 'Sistem',
                'action' => $row['action'],
                'label' => $actionLabels[$row['action']],
            ];
        }, AuditLog::forRecord('queue', $queueId))));

        $merged = array_merge($statusEvents, $noteEvents, $auditEvents);
        usort($merged, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $merged;
    }
}
