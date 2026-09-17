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
use App\Models\EngineerAssignment;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadSales;
use App\Models\LeadStatusHistory;
use App\Models\MasterData;
use App\Models\Notification;
use App\Models\Prelim;
use App\Models\ProcurementPriceValidation;
use App\Models\ProcurementRequest;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\SalesQueue;
use App\Models\User;

class LeadController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', ''),
            'simple_status' => $request->input('simple_status', ''),
            'priority' => $request->input('priority', ''),
            'source_id' => $request->input('source_id', ''),
            'category_id' => $request->input('category_id', ''),
            'type_id' => $request->input('type_id', ''),
            'system_id' => $request->input('system_id', ''),
            'funding_id' => $request->input('funding_id', ''),
            'sales_id' => $request->input('sales_id', ''),
            'follow_up' => $request->input('follow_up', ''),
            'sort' => $request->input('sort', 'created_at'),
            'dir' => $request->input('dir', 'desc'),
            'page' => (int) $request->input('page', 1),
            'trashed' => $request->input('trashed') ? true : false,
        ];

        if ($this->scopeSalesId() !== null) {
            $filters['scope_sales_id'] = $this->scopeSalesId();
            $filters['trashed'] = false; // sales users never see the trash bin
        }

        $result = Lead::search($filters);

        $salesByLead = LeadSales::forLeads(array_column($result['rows'], 'id'));
        foreach ($result['rows'] as &$row) {
            $row['sales_names'] = $salesByLead[(int) $row['id']] ?? [];

            // Same computation as the Lead detail page and the Lead Monitoring
            // report — Lead::searchSelect() already joined the flags this needs,
            // so this stays a single query for the whole list, no N+1.
            $activeQueue = empty($row['queue_id']) ? null : ['survey_status_code' => $row['survey_status_code']];
            $row['current_position'] = Lead::currentPosition($row, $activeQueue, [], [], [
                'validation' => !empty($row['pending_validation_id']),
                'procurement' => !empty($row['active_procurement_id']),
                'salesEngineer' => !empty($row['active_sales_engineer_id']),
                'salesEngineerPurpose' => $row['active_sales_engineer_purpose'] ?? null,
                'engineer' => !empty($row['active_engineer_id']),
                'engineerPurpose' => $row['active_engineer_purpose'] ?? null,
                'prelim' => !empty($row['active_prelim_id']),
            ]);

            // current_pic_name is only ever joined from the queue row (Survey/
            // Data Antrian) — showing it for any other resolved stage would be
            // stale/wrong (e.g. an old queue PIC left over from before the
            // lead moved on). Only trust it when that's genuinely the current
            // stage; fall back to the lead's own Sales for the Sales/Proposal/
            // Prelim stages, and '-' where the list query has no assignee name
            // (Engineer/Sales Engineer/Procurement/Approval Harga, or a
            // Survey run via a Sales Engineer/Engineer assignment rather than
            // the Antrian queue) — the Detail Lead page resolves the correct
            // name for those instead.
            $positionLabel = $row['current_position']['label'];
            $surveyViaAssignment = $positionLabel === 'Survey' && (
                (!empty($row['active_sales_engineer_id']) && ($row['active_sales_engineer_purpose'] ?? 'engineering') === 'survey')
                || (!empty($row['active_engineer_id']) && ($row['active_engineer_purpose'] ?? 'engineering') === 'survey')
            );
            if (!$surveyViaAssignment && in_array($positionLabel, ['Survey', 'Data Antrian'], true)) {
                $row['current_pic_display'] = $row['current_pic_name'] ?? '-';
            } elseif (in_array($positionLabel, ['Sales', 'Proposal', 'Prelim'], true)) {
                $row['current_pic_display'] = $row['sales_name'] ?? '-';
            } else {
                $row['current_pic_display'] = '-';
            }
        }
        unset($row);

        $this->view('leads/index', [
            'pageTitle' => 'Leads',
            'leads' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'filters' => $filters,
            'statusCounts' => Lead::countByStatus($this->scopeSalesId()),
            'statusMap' => MasterData::allAsMap('lead_statuses'),
            'priorityMap' => MasterData::allAsMap('priorities'),
            'sources' => MasterData::allAsMap('lead_sources', true),
            'categories' => MasterData::allAsMap('lead_categories', true),
            'leadTypes' => MasterData::allAsMap('lead_types', true),
            'leadSystems' => MasterData::allAsMap('lead_systems', true),
            'fundingSources' => MasterData::allAsMap('funding_sources', true),
            'salesUsers' => User::activeSales(),
            'canManage' => Acl::can('lead.edit'),
            'canAssign' => Acl::can('lead.assign'),
            'canDelete' => Acl::can('lead.delete'),
        ]);
    }

    public function create(): void
    {
        $this->renderForm(null);
    }

    public function store(Request $request): void
    {
        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/leads/create');

            return;
        }

        $validator = new Validator($request->all(), [
            'customer_name' => 'required|max:150',
            'email' => 'email|max:150',
            'phone' => 'max:30',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        if ($validator->fails()) {
            Session::flash('error', collect_first_error($validator->errors()));
            Session::flashOld($request->only(['customer_name', 'company_name', 'phone', 'email', 'address', 'site_location', 'pln_id', 'electricity_bill', 'source_id', 'category_id', 'type_id', 'system_id', 'funding_id', 'need_type_id', 'needs_description', 'estimated_value', 'size_kwp', 'notes', 'note2', 'priority', 'sales_ids', 'follow_up_date']));
            $this->redirect('/leads/create');

            return;
        }

        $actor = Auth::user();

        // A scoped Sales user always creates their own lead regardless of what
        // was posted; otherwise take whichever sales were checked (first = primary).
        $salesIds = $this->scopeSalesId() !== null
            ? [$this->scopeSalesId()]
            : array_values(array_unique(array_filter(array_map('intval', (array) $request->input('sales_ids', [])))));
        $primarySalesId = $salesIds[0] ?? null;

        $notes = trim((string) $request->input('notes', '')) ?: null;
        $note2 = trim((string) $request->input('note2', '')) ?: null;

        $lead = Lead::createWithCode([
            'customer_name' => trim((string) $request->input('customer_name')),
            'company_name' => trim((string) $request->input('company_name', '')) ?: null,
            'phone' => trim((string) $request->input('phone', '')) ?: null,
            'email' => trim((string) $request->input('email', '')) ?: null,
            'address' => trim((string) $request->input('address', '')) ?: null,
            'site_location' => trim((string) $request->input('site_location', '')) ?: null,
            'pln_id' => trim((string) $request->input('pln_id', '')) ?: null,
            'electricity_bill' => $request->input('electricity_bill') !== '' && $request->input('electricity_bill') !== null ? (float) $request->input('electricity_bill') : null,
            'source_id' => $this->nullableInt($request->input('source_id')),
            'category_id' => $this->nullableInt($request->input('category_id')),
            'type_id' => $this->nullableInt($request->input('type_id')),
            'system_id' => $this->nullableInt($request->input('system_id')),
            'funding_id' => $this->nullableInt($request->input('funding_id')),
            'need_type_id' => $this->nullableInt($request->input('need_type_id')),
            'needs_description' => trim((string) $request->input('needs_description', '')) ?: null,
            'estimated_value' => $request->input('estimated_value') !== '' ? (float) $request->input('estimated_value') : null,
            'size_kwp' => $request->input('size_kwp') !== '' ? (float) $request->input('size_kwp') : null,
            'notes' => $notes,
            'note2' => $note2,
            'note_updated_at' => ($notes !== null || $note2 !== null) ? date('Y-m-d H:i:s') : null,
            'status' => 'new',
            'priority' => $request->input('priority'),
            'sales_id' => $primarySalesId,
            'follow_up_date' => $request->input('follow_up_date') ?: null,
            'created_by' => $actor['id'],
            'updated_by' => $actor['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!empty($salesIds)) {
            LeadSales::sync($lead['id'], $salesIds);
        }

        LeadStatusHistory::record($lead['id'], null, 'new', (int) $actor['id'], 'Lead dibuat.');

        AuditLogger::log((int) $actor['id'], 'lead_created', 'lead', $lead['id'], null, [
            'lead_code' => $lead['lead_code'],
            'customer_name' => $request->input('customer_name'),
            'type_id' => $this->nullableInt($request->input('type_id')),
            'system_id' => $this->nullableInt($request->input('system_id')),
            'funding_id' => $this->nullableInt($request->input('funding_id')),
        ]);

        Session::flash('success', "Lead {$lead['lead_code']} berhasil dibuat.");
        $this->redirect('/leads/' . $lead['id']);
    }

    public function show(Request $request, array $params): void
    {
        $lead = $this->findAuthorized((int) $params['id']);

        $timeline = $this->buildTimeline((int) $lead['id']);
        $procurementRole = Role::findBySlug('procurement');
        $activeQueue = SalesQueue::activeForLead((int) $lead['id']);
        $statusMap = MasterData::allAsMap('lead_statuses');
        $queueStatusMap = MasterData::allAsMap('queue_statuses');

        // Revisi Sub-Fase 2/4: computed once, reused both for the page's own
        // sections AND to derive Current Process below (Lead::currentPosition()).
        // Revisi Alur Bisnis (Prelim): unfiltered by purpose here (any active
        // assignment of that type, survey OR engineering) — correct for
        // Current Process/top-panel PIC since only one can realistically be
        // open at a time per the mandated flow ordering.
        $activeEngineerAssignment = EngineerAssignment::activeForLead((int) $lead['id'], 'engineer');
        $activeSalesEngineerAssignment = EngineerAssignment::activeForLead((int) $lead['id'], 'sales_engineer');
        $activeProcurementRequest = ProcurementRequest::activeForLead((int) $lead['id']);
        $pendingValidation = $activeProcurementRequest !== null
            ? ProcurementPriceValidation::pendingForRequest((int) $activeProcurementRequest['id'])
            : null;

        // Revisi Alur Bisnis (Prelim): purpose-scoped lookups for the page's
        // two distinct sections — "Survey" (pra-Prelim, lengkapi Data Awal)
        // and "Engineering" (pasca-ACC, Proposal+BOQ) — so each section only
        // ever shows assignments that actually belong to that stage.
        $surveyEngineerAssignment = EngineerAssignment::activeForLead((int) $lead['id'], 'engineer', 'survey');
        $surveySalesEngineerAssignment = EngineerAssignment::activeForLead((int) $lead['id'], 'sales_engineer', 'survey');
        $engineeringEngineerAssignment = EngineerAssignment::activeForLead((int) $lead['id'], 'engineer', 'engineering');
        $engineeringSalesEngineerAssignment = EngineerAssignment::activeForLead((int) $lead['id'], 'sales_engineer', 'engineering');

        $latestPrelim = Prelim::latestForLead((int) $lead['id']);
        $activePrelim = ($latestPrelim !== null && $latestPrelim['status'] !== 'approved') ? $latestPrelim : null;
        $missingPrelimFields = Lead::missingPrelimFields($lead);

        $this->view('leads/show', [
            'pageTitle' => $lead['lead_code'],
            'lead' => $lead,
            'timeline' => $timeline,
            'assignedSales' => LeadSales::forLead((int) $lead['id']),
            'simplifiedStatus' => Lead::simplifiedStatus($lead['status']),
            'currentPosition' => Lead::currentPosition($lead, $activeQueue, $statusMap, $queueStatusMap, [
                'validation' => $pendingValidation,
                'procurement' => $activeProcurementRequest,
                'salesEngineer' => $activeSalesEngineerAssignment,
                'salesEngineerPurpose' => $activeSalesEngineerAssignment['purpose'] ?? null,
                'engineer' => $activeEngineerAssignment,
                'engineerPurpose' => $activeEngineerAssignment['purpose'] ?? null,
                'prelim' => $activePrelim,
            ]),
            'statusMap' => $statusMap,
            'queueStatusMap' => $queueStatusMap,
            'priorityMap' => MasterData::allAsMap('priorities'),
            'salesUsers' => User::activeSales(),
            'canManage' => Acl::can('lead.edit') && !$this->isReadOnlyForSales($lead),
            'canAssign' => Acl::can('lead.assign'),
            'canDelete' => Acl::can('lead.delete'),
            'activeQueue' => $activeQueue,
            'canEnqueue' => Acl::can('lead.edit') && !$this->isReadOnlyForSales($lead),
            // Revisi Alur Bisnis (Prelim) — Data Awal completeness + Prelim itself.
            'missingPrelimFields' => $missingPrelimFields,
            'prelim' => $latestPrelim,
            'prelimStatusMap' => MasterData::allAsMap('prelim_statuses'),
            'canCreatePrelim' => Acl::can('prelim.create') && !$this->isReadOnlyForSales($lead) && !$lead['deleted_at'],
            // Revisi Alur Bisnis (Prelim) — Survey partner: Sales Engineer atau
            // Engineer, dipilih Sales sebelum Prelim dibuat (data belum lengkap).
            'surveyEngineerAssignment' => $surveyEngineerAssignment,
            'surveySalesEngineerAssignment' => $surveySalesEngineerAssignment,
            'canRequestSurvey' => Acl::can('lead.edit') && Acl::can('engineer.view') && !$this->isReadOnlyForSales($lead) && !$lead['deleted_at'],
            // Revisi Sub-Fase 2: Engineer (survey lapangan lanjutan/desain) dan
            // Sales Engineer (analisa teknis, modul engineer_assignments yang
            // sudah ada) dibedakan lewat assignment_type, sehingga satu lead
            // bisa punya assignment aktif untuk masing-masing tipe. Revisi Alur
            // Bisnis (Prelim): sekarang juga dipisah oleh purpose='engineering'
            // — section ini murni untuk tahap pasca-ACC Prelim.
            'activeEngineerAssignment' => $engineeringEngineerAssignment,
            'latestEngineerAssignment' => EngineerAssignment::latestForLead((int) $lead['id'], 'engineer', 'engineering'),
            'activeSalesEngineerAssignment' => $engineeringSalesEngineerAssignment,
            'latestSalesEngineerAssignment' => EngineerAssignment::latestForLead((int) $lead['id'], 'sales_engineer', 'engineering'),
            'engineerStatusMap' => MasterData::allAsMap('engineer_statuses'),
            'engineerFieldUsers' => User::activeEngineers(),
            'salesEngineerUsers' => User::activeSalesEngineers(),
            'canRequestEngineer' => Acl::can('lead.edit') && Acl::can('engineer.view') && !$this->isReadOnlyForSales($lead) && Prelim::hasApprovedForLead((int) $lead['id']),
            'activeProcurementRequest' => $activeProcurementRequest,
            'latestProcurementRequest' => ProcurementRequest::latestForLead((int) $lead['id']),
            'procurementStatusMap' => MasterData::allAsMap('procurement_statuses'),
            'procurementUsers' => $procurementRole ? User::activeByRole((int) $procurementRole['id']) : [],
            // Revisi Alur Bisnis (Prelim) — this is the skip-Engineering shortcut
            // straight to Procurement, gated by the same Prelim-ACC requirement
            // as ProcurementController::requestFromLead() enforces server-side.
            'canRequestProcurement' => Acl::can('lead.edit') && Acl::can('procurement.view') && !$this->isReadOnlyForSales($lead) && Prelim::hasApprovedForLead((int) $lead['id']),
            'priceValidations' => ($priceValidationRequest = $activeProcurementRequest ?? ProcurementRequest::latestForLead((int) $lead['id'])) !== null
                ? ProcurementPriceValidation::forRequest((int) $priceValidationRequest['id'])
                : [],
            'proposals' => Proposal::forLead((int) $lead['id']),
            'proposalStatusMap' => MasterData::allAsMap('proposal_statuses'),
            // Revisi Alur Bisnis (Prelim) — this gates the Lead page's own
            // "Buat Proposal Baru" (skip-Engineering/Procurement shortcut,
            // ProposalController::createFromLead()); Procurement's own
            // "Buat Proposal" button is separately gated by that request's
            // own pricing_completed status, unreachable before Prelim ACC now.
            'canCreateProposal' => Acl::can('lead.edit') && Acl::can('proposal.create') && !$this->isReadOnlyForSales($lead) && Prelim::hasApprovedForLead((int) $lead['id']),
            'followUps' => FollowUp::forLead((int) $lead['id']),
            'canLogFollowUp' => Acl::can('followup.create') && !$this->isReadOnlyForSales($lead) && !$lead['deleted_at'],
            'followUpMethodLabels' => (new FollowUpController())->methodLabels(),
            'followUpResponseLabels' => (new FollowUpController())->responseLabels(),
            'canManageDeal' => Acl::can('lead.edit') && !$this->isReadOnlyForSales($lead) && !$lead['deleted_at'],
            'wonLostProposals' => array_values(array_filter(Proposal::forLead((int) $lead['id']), fn ($p) => in_array($p['status'], ['sent', 'viewed', 'negotiation', 'accepted'], true))),
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $lead = $this->findAuthorized((int) $params['id']);
        $this->renderForm($lead);
    }

    public function update(Request $request, array $params): void
    {
        $lead = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/leads/' . $lead['id'] . '/edit');

            return;
        }

        $validator = new Validator($request->all(), [
            'customer_name' => 'required|max:150',
            'email' => 'email|max:150',
            'phone' => 'max:30',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        if ($validator->fails()) {
            Session::flash('error', collect_first_error($validator->errors()));
            $this->redirect('/leads/' . $lead['id'] . '/edit');

            return;
        }

        $actor = Auth::user();

        $notes = trim((string) $request->input('notes', '')) ?: null;
        $note2 = trim((string) $request->input('note2', '')) ?: null;
        $notesChanged = $notes !== $lead['notes'] || $note2 !== $lead['note2'];

        $newData = [
            'customer_name' => trim((string) $request->input('customer_name')),
            'company_name' => trim((string) $request->input('company_name', '')) ?: null,
            'phone' => trim((string) $request->input('phone', '')) ?: null,
            'email' => trim((string) $request->input('email', '')) ?: null,
            'address' => trim((string) $request->input('address', '')) ?: null,
            'site_location' => trim((string) $request->input('site_location', '')) ?: null,
            'pln_id' => trim((string) $request->input('pln_id', '')) ?: null,
            'electricity_bill' => $request->input('electricity_bill') !== '' && $request->input('electricity_bill') !== null ? (float) $request->input('electricity_bill') : null,
            'source_id' => $this->nullableInt($request->input('source_id')),
            'category_id' => $this->nullableInt($request->input('category_id')),
            'type_id' => $this->nullableInt($request->input('type_id')),
            'system_id' => $this->nullableInt($request->input('system_id')),
            'funding_id' => $this->nullableInt($request->input('funding_id')),
            'need_type_id' => $this->nullableInt($request->input('need_type_id')),
            'needs_description' => trim((string) $request->input('needs_description', '')) ?: null,
            'estimated_value' => $request->input('estimated_value') !== '' ? (float) $request->input('estimated_value') : null,
            'size_kwp' => $request->input('size_kwp') !== '' ? (float) $request->input('size_kwp') : null,
            'notes' => $notes,
            'note2' => $note2,
            'priority' => $request->input('priority'),
            'follow_up_date' => $request->input('follow_up_date') ?: null,
            'updated_by' => $actor['id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($notesChanged) {
            $newData['note_updated_at'] = date('Y-m-d H:i:s');
        }

        // Reassignment is gated separately from the rest of the edit form —
        // a Sales user editing their own lead's details can never smuggle in
        // a sales_ids[] change they're not authorized to make.
        if (Acl::can('lead.assign')) {
            $salesIds = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('sales_ids', [])))));
            if (!empty($salesIds)) {
                LeadSales::sync((int) $lead['id'], $salesIds);
                $newData['sales_id'] = $salesIds[0];
            }
        }

        Lead::update((int) $lead['id'], $newData);

        AuditLogger::log((int) $actor['id'], 'lead_updated', 'lead', (int) $lead['id'], [
            'customer_name' => $lead['customer_name'],
            'priority' => $lead['priority'],
            'type_id' => $lead['type_id'],
            'system_id' => $lead['system_id'],
            'funding_id' => $lead['funding_id'],
        ], [
            'customer_name' => $newData['customer_name'],
            'priority' => $newData['priority'],
            'type_id' => $newData['type_id'],
            'system_id' => $newData['system_id'],
            'funding_id' => $newData['funding_id'],
        ]);

        Session::flash('success', 'Lead berhasil diperbarui.');
        $this->redirect('/leads/' . $lead['id']);
    }

    public function destroy(Request $request, array $params): void
    {
        $lead = Lead::withRelations((int) $params['id']);
        if ($lead === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/leads');

            return;
        }

        Lead::softDelete((int) $lead['id']);

        AuditLogger::log((int) Auth::id(), 'lead_deleted', 'lead', (int) $lead['id'], ['lead_code' => $lead['lead_code']], null);

        Session::flash('success', "Lead {$lead['lead_code']} dipindahkan ke sampah.");
        $this->redirect('/leads');
    }

    public function restore(Request $request, array $params): void
    {
        $lead = Lead::withRelationsIncludingTrashed((int) $params['id']);
        if ($lead === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/leads?trashed=1');

            return;
        }

        Lead::restore((int) $lead['id']);

        AuditLogger::log((int) Auth::id(), 'lead_restored', 'lead', (int) $lead['id'], null, ['lead_code' => $lead['lead_code']]);

        Session::flash('success', "Lead {$lead['lead_code']} berhasil dipulihkan.");
        $this->redirect('/leads?trashed=1');
    }

    /**
     * "Lead → Masuk Antrian": the Phase 6 entry point into Sales Queue.
     * Creates the sales_queue row and moves the lead's own status to
     * in_queue — the two stay independently manageable afterward.
     */
    public function enqueue(Request $request, array $params): void
    {
        $lead = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        if (SalesQueue::activeForLead((int) $lead['id']) !== null) {
            Session::flash('error', 'Lead ini sudah memiliki antrian aktif.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        if (empty($lead['sales_id'])) {
            Session::flash('error', 'Tugaskan lead ke sales terlebih dahulu sebelum memasukkan ke antrian.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        $actor = Auth::user();
        $now = date('Y-m-d H:i:s');

        $queueId = SalesQueue::createForLead([
            'lead_id' => (int) $lead['id'],
            'sales_id' => (int) $lead['sales_id'],
            'priority' => $lead['priority'],
            'status' => 'new',
            'entered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'last_updated_at' => $now,
        ]);

        \App\Models\QueueStatusHistory::record($queueId, null, 'new', (int) $actor['id'], 'Lead dimasukkan ke antrian.');
        AuditLogger::log((int) $actor['id'], 'queue_created', 'queue', $queueId, null, ['lead_code' => $lead['lead_code']]);

        $oldStatus = $lead['status'];
        Lead::update((int) $lead['id'], ['status' => 'in_queue', 'updated_by' => $actor['id'], 'updated_at' => $now]);
        LeadStatusHistory::record((int) $lead['id'], $oldStatus, 'in_queue', (int) $actor['id'], 'Masuk antrian sales.');

        Session::flash('success', 'Lead berhasil dimasukkan ke antrian.');
        $this->redirect('/queue/' . $queueId);
    }

    // ------------------------------------------------------------------
    // Phase 10 — Deal Management: close a lead out as Won or Lost.
    // ------------------------------------------------------------------

    public function markWon(Request $request, array $params): void
    {
        $lead = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        if (!Acl::can('lead.edit') || $this->isReadOnlyForSales($lead) || in_array($lead['status'], ['won', 'lost'], true)) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), [
            'deal_value' => 'required|numeric',
            'closing_date' => 'required',
        ]);

        if ($validator->fails()) {
            Session::flash('error', 'Nilai deal dan tanggal closing wajib diisi dengan benar.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        $wonProposalId = $this->nullableInt($request->input('won_proposal_id'));
        if ($wonProposalId !== null) {
            $proposal = Proposal::find($wonProposalId);
            if ($proposal === null || (int) $proposal['lead_id'] !== (int) $lead['id']) {
                $wonProposalId = null;
            }
        }

        $actor = Auth::user();
        $now = date('Y-m-d H:i:s');
        $oldStatus = $lead['status'];

        Lead::update((int) $lead['id'], [
            'status' => 'won',
            'deal_value' => (float) $request->input('deal_value'),
            'won_proposal_id' => $wonProposalId,
            'closing_date' => $request->input('closing_date'),
            'customer_confirmed' => $request->input('customer_confirmed') ? 1 : 0,
            'confirmation_notes' => trim((string) $request->input('confirmation_notes', '')) ?: null,
            'lost_reason' => null,
            'updated_by' => $actor['id'],
            'updated_at' => $now,
        ]);

        if ($wonProposalId !== null) {
            $wonProposal = Proposal::find($wonProposalId);
            if ($wonProposal !== null && $wonProposal['status'] !== 'accepted') {
                Proposal::update($wonProposalId, ['status' => 'accepted', 'responded_at' => $now, 'updated_at' => $now]);
                \App\Models\ProposalStatusHistory::record($wonProposalId, $wonProposal['status'], 'accepted', (int) $actor['id'], 'Deal ditandai Won.');
            }
        }

        LeadStatusHistory::record((int) $lead['id'], $oldStatus, 'won', (int) $actor['id'], 'Deal closing: Rp ' . number_format((float) $request->input('deal_value'), 0, ',', '.'));
        AuditLogger::log((int) $actor['id'], 'lead_marked_won', 'lead', (int) $lead['id'], ['status' => $oldStatus], ['status' => 'won', 'deal_value' => $request->input('deal_value')]);

        $managerRole = Role::findBySlug('manager');
        if ($managerRole !== null) {
            foreach (User::activeByRole((int) $managerRole['id']) as $manager) {
                Notification::create(
                    (int) $manager['id'],
                    'lead_won',
                    'Deal Baru: Won',
                    "Lead {$lead['lead_code']} ({$lead['customer_name']}) ditutup sebagai Won senilai Rp " . number_format((float) $request->input('deal_value'), 0, ',', '.') . '.',
                    '/leads/' . $lead['id']
                );
            }
        }

        Session::flash('success', 'Lead ditandai sebagai Won. Selamat!');
        $this->redirect('/leads/' . $lead['id']);
    }

    public function markLost(Request $request, array $params): void
    {
        $lead = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        if (!Acl::can('lead.edit') || $this->isReadOnlyForSales($lead) || in_array($lead['status'], ['won', 'lost'], true)) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), ['lost_reason' => 'required|max:1000']);
        if ($validator->fails()) {
            Session::flash('error', 'Alasan kalah wajib diisi.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        $actor = Auth::user();
        $now = date('Y-m-d H:i:s');
        $oldStatus = $lead['status'];
        $reason = trim((string) $request->input('lost_reason'));

        Lead::update((int) $lead['id'], [
            'status' => 'lost',
            'lost_reason' => $reason,
            'closing_date' => $request->input('closing_date') ?: date('Y-m-d'),
            'deal_value' => null,
            'won_proposal_id' => null,
            'updated_by' => $actor['id'],
            'updated_at' => $now,
        ]);

        LeadStatusHistory::record((int) $lead['id'], $oldStatus, 'lost', (int) $actor['id'], $reason);
        AuditLogger::log((int) $actor['id'], 'lead_marked_lost', 'lead', (int) $lead['id'], ['status' => $oldStatus], ['status' => 'lost', 'lost_reason' => $reason]);

        $managerRole = Role::findBySlug('manager');
        if ($managerRole !== null) {
            foreach (User::activeByRole((int) $managerRole['id']) as $manager) {
                Notification::create(
                    (int) $manager['id'],
                    'lead_lost',
                    'Deal Ditutup: Lost',
                    "Lead {$lead['lead_code']} ({$lead['customer_name']}) ditutup sebagai Lost: {$reason}",
                    '/leads/' . $lead['id']
                );
            }
        }

        Session::flash('success', 'Lead ditandai sebagai Lost.');
        $this->redirect('/leads/' . $lead['id']);
    }

    /** Undo an accidental Won/Lost — reopens the deal for the pipeline stage it was closed from. */
    public function reopenDeal(Request $request, array $params): void
    {
        $lead = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        if (!Acl::can('lead.edit') || $this->isReadOnlyForSales($lead) || !in_array($lead['status'], ['won', 'lost'], true)) {
            $this->abort(403);

            return;
        }

        $actor = Auth::user();
        $now = date('Y-m-d H:i:s');
        $oldStatus = $lead['status'];

        $lastTransition = LeadStatusHistory::mostRecentTransitionTo((int) $lead['id'], $oldStatus);
        $restoredStatus = $lastTransition['from_status'] ?? (!empty($lead['won_proposal_id']) ? 'proposal' : 'follow_up');
        if (in_array($restoredStatus, ['won', 'lost'], true) || $restoredStatus === null) {
            $restoredStatus = !empty($lead['won_proposal_id']) ? 'proposal' : 'follow_up';
        }

        Lead::update((int) $lead['id'], [
            'status' => $restoredStatus,
            'updated_by' => $actor['id'],
            'updated_at' => $now,
        ]);

        LeadStatusHistory::record((int) $lead['id'], $oldStatus, $restoredStatus, (int) $actor['id'], 'Status deal dibuka kembali.');
        AuditLogger::log((int) $actor['id'], 'lead_deal_reopened', 'lead', (int) $lead['id'], ['status' => $oldStatus], ['status' => $restoredStatus]);

        Session::flash('success', 'Status deal dibuka kembali.');
        $this->redirect('/leads/' . $lead['id']);
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    private function renderForm(?array $lead): void
    {
        $assignedSalesIds = $lead
            ? array_column(LeadSales::forLead((int) $lead['id']), 'id')
            : ($this->scopeSalesId() !== null ? [$this->scopeSalesId()] : []);

        $this->view('leads/form', [
            'pageTitle' => $lead ? 'Ubah Lead' : 'Tambah Lead',
            'lead' => $lead,
            'sources' => MasterData::allAsMap('lead_sources', true),
            'categories' => MasterData::allAsMap('lead_categories', true),
            'leadTypes' => MasterData::allAsMap('lead_types', true),
            'leadSystems' => MasterData::allAsMap('lead_systems', true),
            'fundingSources' => MasterData::allAsMap('funding_sources', true),
            'needTypes' => MasterData::allAsMap('need_types', true),
            'priorities' => MasterData::allAsMap('priorities', true),
            'salesUsers' => User::activeSales(),
            'assignedSalesIds' => $assignedSalesIds,
            // Reassigning an existing lead's sales needs lead.assign; the field
            // still shows on create for anyone not scoped to their own leads.
            'showSalesField' => $this->scopeSalesId() === null && (!$lead || Acl::can('lead.assign')),
        ]);
    }

    private function findAuthorized(int $id): array
    {
        $lead = Lead::withRelations($id);

        if ($lead === null) {
            $this->abort(404);
        }

        $scopeSalesId = $this->scopeSalesId();
        if ($scopeSalesId !== null && (int) $lead['sales_id'] !== $scopeSalesId && !LeadSales::isAssigned($id, $scopeSalesId)) {
            $this->abort(403, 'Anda hanya dapat mengakses lead yang ditugaskan kepada Anda.');
        }

        return $lead;
    }

    /**
     * Sales users are scoped to their own leads; everyone else with
     * lead.view (Admin Sales, Manager, Engineer Sales, Super Admin) sees
     * the whole list.
     */
    private function scopeSalesId(): ?int
    {
        return Acl::hasRole('sales') ? Auth::id() : null;
    }

    private function isReadOnlyForSales(array $lead): bool
    {
        $scopeSalesId = $this->scopeSalesId();

        return $scopeSalesId !== null
            && (int) $lead['sales_id'] !== $scopeSalesId
            && !LeadSales::isAssigned((int) $lead['id'], $scopeSalesId);
    }

    private function nullableInt(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    private function buildTimeline(int $leadId): array
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
        }, LeadStatusHistory::forLead($leadId));

        $auditEvents = array_values(array_filter(array_map(function ($row) {
            if (in_array($row['action'], ['lead_created', 'lead_status_changed', 'lead_marked_won', 'lead_marked_lost', 'lead_deal_reopened'], true)) {
                return null; // all already appear via lead_status_history above
            }

            return [
                'type' => 'audit',
                'created_at' => $row['created_at'],
                'actor' => $row['user_name'] ?? 'Sistem',
                'action' => $row['action'],
                'old_data' => $row['old_data'] ? json_decode($row['old_data'], true) : null,
                'new_data' => $row['new_data'] ? json_decode($row['new_data'], true) : null,
            ];
        }, AuditLog::forRecord('lead', $leadId))));

        $merged = array_merge($statusEvents, $auditEvents);
        usort($merged, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $merged;
    }
}
