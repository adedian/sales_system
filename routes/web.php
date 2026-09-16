<?php

use App\Controllers\Api\DashboardApiController;
use App\Controllers\Api\EngineerApiController;
use App\Controllers\Api\LeadApiController;
use App\Controllers\Api\NotificationApiController;
use App\Controllers\Api\ProcurementApiController;
use App\Controllers\Api\ProposalApiController;
use App\Controllers\Api\QueueApiController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\EngineerController;
use App\Controllers\FollowUpController;
use App\Controllers\LeadController;
use App\Controllers\MasterDataController;
use App\Controllers\NotificationController;
use App\Controllers\ProcurementController;
use App\Controllers\ProductController;
use App\Controllers\ProfileController;
use App\Controllers\ProposalController;
use App\Controllers\QueueController;
use App\Controllers\ReportController;
use App\Controllers\RoleController;
use App\Controllers\SettingController;
use App\Controllers\UserController;
use App\Controllers\VendorController;

/** @var \App\Core\Router $router */

$router->get('/', [DashboardController::class, 'index'], ['auth']);

$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

$router->get('/change-password', [AuthController::class, 'showChangePassword'], ['auth']);
$router->post('/change-password', [AuthController::class, 'changePassword'], ['auth']);

$router->get('/dashboard', [DashboardController::class, 'index'], ['auth', 'permission:dashboard.view']);

$router->get('/profile', [ProfileController::class, 'show'], ['auth']);
$router->post('/profile', [ProfileController::class, 'update'], ['auth']);

$router->get('/notifications', [NotificationController::class, 'index'], ['auth']);

$router->get('/users', [UserController::class, 'index'], ['auth', 'permission:user.manage']);
$router->get('/users/create', [UserController::class, 'create'], ['auth', 'permission:user.manage']);
$router->post('/users', [UserController::class, 'store'], ['auth', 'permission:user.manage']);
$router->get('/users/{id}/edit', [UserController::class, 'edit'], ['auth', 'permission:user.manage']);
$router->post('/users/{id}', [UserController::class, 'update'], ['auth', 'permission:user.manage']);
$router->post('/users/{id}/toggle-status', [UserController::class, 'toggleStatus'], ['auth', 'permission:user.manage']);

$router->get('/roles', [RoleController::class, 'index'], ['auth', 'permission:user.manage']);
$router->get('/roles/create', [RoleController::class, 'create'], ['auth', 'permission:user.manage']);
$router->post('/roles', [RoleController::class, 'store'], ['auth', 'permission:user.manage']);
$router->get('/roles/{id}/edit', [RoleController::class, 'edit'], ['auth', 'permission:user.manage']);
$router->post('/roles/{id}', [RoleController::class, 'update'], ['auth', 'permission:user.manage']);
$router->post('/roles/{id}/delete', [RoleController::class, 'destroy'], ['auth', 'permission:user.manage']);

$router->get('/master-data', [MasterDataController::class, 'landing'], ['auth', 'permission:master_data.manage']);
$router->get('/master-data/{type}', [MasterDataController::class, 'index'], ['auth', 'permission:master_data.manage']);
$router->post('/master-data/{type}', [MasterDataController::class, 'store'], ['auth', 'permission:master_data.manage']);
$router->post('/master-data/{type}/{id}', [MasterDataController::class, 'update'], ['auth', 'permission:master_data.manage']);
$router->post('/master-data/{type}/{id}/toggle-status', [MasterDataController::class, 'toggleStatus'], ['auth', 'permission:master_data.manage']);
$router->post('/master-data/{type}/{id}/delete', [MasterDataController::class, 'destroy'], ['auth', 'permission:master_data.manage']);

$router->get('/leads', [LeadController::class, 'index'], ['auth', 'permission:lead.view']);
$router->get('/leads/create', [LeadController::class, 'create'], ['auth', 'permission:lead.create']);
$router->post('/leads', [LeadController::class, 'store'], ['auth', 'permission:lead.create']);
$router->get('/leads/{id}/edit', [LeadController::class, 'edit'], ['auth', 'permission:lead.edit']);
$router->post('/leads/{id}/delete', [LeadController::class, 'destroy'], ['auth', 'permission:lead.delete']);
$router->post('/leads/{id}/restore', [LeadController::class, 'restore'], ['auth', 'permission:lead.delete']);
$router->post('/leads/{id}/enqueue', [LeadController::class, 'enqueue'], ['auth', 'permission:lead.edit']);
$router->post('/leads/{id}/request-engineer', [EngineerController::class, 'requestAssignment'], ['auth', 'permission:lead.edit']);
$router->post('/leads/{id}/request-procurement', [ProcurementController::class, 'requestFromLead'], ['auth', 'permission:lead.edit']);
$router->post('/leads/{id}/proposals', [ProposalController::class, 'createFromLead'], ['auth', 'permission:lead.edit']);
$router->post('/leads/{id}/follow-ups', [FollowUpController::class, 'store'], ['auth', 'permission:followup.create']);
$router->post('/leads/{id}/mark-won', [LeadController::class, 'markWon'], ['auth', 'permission:lead.edit']);
$router->post('/leads/{id}/mark-lost', [LeadController::class, 'markLost'], ['auth', 'permission:lead.edit']);
$router->post('/leads/{id}/reopen-deal', [LeadController::class, 'reopenDeal'], ['auth', 'permission:lead.edit']);
$router->post('/leads/{id}', [LeadController::class, 'update'], ['auth', 'permission:lead.edit']);
$router->get('/leads/{id}', [LeadController::class, 'show'], ['auth', 'permission:lead.view']);

$router->get('/follow-ups', [FollowUpController::class, 'index'], ['auth', 'permission:followup.view']);

$router->get('/queue', [QueueController::class, 'index'], ['auth', 'permission:queue.view']);
$router->post('/queue/{id}/notes', [QueueController::class, 'addNote'], ['auth', 'permission:queue.view']);
$router->get('/queue/{id}', [QueueController::class, 'show'], ['auth', 'permission:queue.view']);

$router->get('/engineer', [EngineerController::class, 'index'], ['auth', 'permission:engineer.view']);
$router->post('/engineer/{id}/accept', [EngineerController::class, 'accept'], ['auth', 'permission:engineer.view']);
$router->post('/engineer/{id}/reject', [EngineerController::class, 'reject'], ['auth', 'permission:engineer.view']);
$router->post('/engineer/{id}/status', [EngineerController::class, 'updateStatus'], ['auth', 'permission:engineer.view']);
$router->post('/engineer/{id}/notes', [EngineerController::class, 'addNote'], ['auth', 'permission:engineer.view']);
$router->post('/engineer/{id}/documents', [EngineerController::class, 'uploadDocument'], ['auth', 'permission:engineer.view']);
$router->get('/engineer/{id}/documents/{docId}', [EngineerController::class, 'downloadDocument'], ['auth', 'permission:engineer.view']);
$router->post('/engineer/{id}/documents/{docId}/delete', [EngineerController::class, 'deleteDocument'], ['auth', 'permission:engineer.view']);
$router->post('/engineer/{id}/result', [EngineerController::class, 'submitResult'], ['auth', 'permission:engineer.view']);
$router->post('/engineer/{id}/return', [EngineerController::class, 'returnToSales'], ['auth', 'permission:engineer.view']);
$router->post('/engineer/{id}/send-to-procurement', [EngineerController::class, 'sendToProcurement'], ['auth', 'permission:engineer.view']);
$router->get('/engineer/{id}', [EngineerController::class, 'show'], ['auth', 'permission:engineer.view']);

$router->get('/vendors', [VendorController::class, 'index'], ['auth', 'permission:vendor.manage']);
$router->get('/vendors/create', [VendorController::class, 'create'], ['auth', 'permission:vendor.manage']);
$router->post('/vendors', [VendorController::class, 'store'], ['auth', 'permission:vendor.manage']);
$router->get('/vendors/{id}/edit', [VendorController::class, 'edit'], ['auth', 'permission:vendor.manage']);
$router->post('/vendors/{id}', [VendorController::class, 'update'], ['auth', 'permission:vendor.manage']);
$router->post('/vendors/{id}/toggle-status', [VendorController::class, 'toggleStatus'], ['auth', 'permission:vendor.manage']);
$router->post('/vendors/{id}/delete', [VendorController::class, 'destroy'], ['auth', 'permission:vendor.manage']);

$router->get('/products', [ProductController::class, 'index'], ['auth', 'permission:product.manage']);
$router->get('/products/create', [ProductController::class, 'create'], ['auth', 'permission:product.manage']);
$router->post('/products', [ProductController::class, 'store'], ['auth', 'permission:product.manage']);
$router->get('/products/{id}/edit', [ProductController::class, 'edit'], ['auth', 'permission:product.manage']);
$router->post('/products/{id}', [ProductController::class, 'update'], ['auth', 'permission:product.manage']);
$router->post('/products/{id}/toggle-status', [ProductController::class, 'toggleStatus'], ['auth', 'permission:product.manage']);
$router->post('/products/{id}/delete', [ProductController::class, 'destroy'], ['auth', 'permission:product.manage']);

$router->get('/settings', [SettingController::class, 'index'], ['auth', 'permission:system.manage']);
$router->post('/settings', [SettingController::class, 'update'], ['auth', 'permission:system.manage']);

$router->get('/procurement', [ProcurementController::class, 'index'], ['auth', 'permission:procurement.view']);
$router->post('/procurement/{id}/items', [ProcurementController::class, 'addItem'], ['auth', 'permission:procurement.view']);
$router->post('/procurement/{id}/items/{itemId}', [ProcurementController::class, 'updateItem'], ['auth', 'permission:procurement.view']);
$router->post('/procurement/{id}/items/{itemId}/delete', [ProcurementController::class, 'deleteItem'], ['auth', 'permission:procurement.view']);
$router->get('/procurement/{id}/items/{itemId}/quotation', [ProcurementController::class, 'downloadQuotation'], ['auth', 'permission:procurement.view']);
$router->post('/procurement/{id}/status', [ProcurementController::class, 'updateStatus'], ['auth', 'permission:procurement.view']);
$router->post('/procurement/{id}/need-revision', [ProcurementController::class, 'markNeedRevision'], ['auth', 'permission:procurement.view']);
$router->post('/procurement/{id}/cancel', [ProcurementController::class, 'cancel'], ['auth', 'permission:procurement.view']);
$router->post('/procurement/{id}/notes', [ProcurementController::class, 'addNote'], ['auth', 'permission:procurement.view']);
$router->post('/procurement/{id}/complete', [ProcurementController::class, 'markCompleted'], ['auth', 'permission:procurement.view']);
$router->post('/procurement/{id}/create-proposal', [ProposalController::class, 'createFromProcurement'], ['auth', 'permission:procurement.view']);
$router->get('/procurement/{id}', [ProcurementController::class, 'show'], ['auth', 'permission:procurement.view']);

$router->get('/proposals', [ProposalController::class, 'index'], ['auth', 'permission:proposal.view']);
$router->post('/proposals/{id}/items', [ProposalController::class, 'addItem'], ['auth', 'permission:proposal.view']);
$router->post('/proposals/{id}/items/{itemId}', [ProposalController::class, 'updateItem'], ['auth', 'permission:proposal.view']);
$router->post('/proposals/{id}/items/{itemId}/delete', [ProposalController::class, 'deleteItem'], ['auth', 'permission:proposal.view']);
$router->post('/proposals/{id}/submit-review', [ProposalController::class, 'submitForReview'], ['auth', 'permission:proposal.view']);
$router->post('/proposals/{id}/approve', [ProposalController::class, 'approve'], ['auth', 'permission:proposal.approve']);
$router->post('/proposals/{id}/request-revision', [ProposalController::class, 'requestRevision'], ['auth', 'permission:proposal.approve']);
$router->post('/proposals/{id}/send', [ProposalController::class, 'send'], ['auth', 'permission:proposal.send']);
$router->post('/proposals/{id}/status', [ProposalController::class, 'updateStatus'], ['auth', 'permission:proposal.view']);
$router->post('/proposals/{id}/negotiations', [ProposalController::class, 'addNegotiation'], ['auth', 'permission:proposal.view']);
$router->post('/proposals/{id}/revise-negotiation', [ProposalController::class, 'reviseFromNegotiation'], ['auth', 'permission:proposal.edit']);
$router->post('/proposals/{id}/notes', [ProposalController::class, 'addNote'], ['auth', 'permission:proposal.view']);
$router->post('/proposals/{id}/delete', [ProposalController::class, 'destroy'], ['auth', 'permission:proposal.view']);
$router->get('/proposals/{id}/pdf', [ProposalController::class, 'pdf'], ['auth', 'permission:proposal.view']);
$router->post('/proposals/{id}', [ProposalController::class, 'update'], ['auth', 'permission:proposal.view']);
$router->get('/proposals/{id}', [ProposalController::class, 'show'], ['auth', 'permission:proposal.view']);

$router->get('/reports', [ReportController::class, 'index'], ['auth', 'permission:report.view']);
$router->get('/reports/leads', [ReportController::class, 'leads'], ['auth', 'permission:report.view']);
$router->get('/reports/queue', [ReportController::class, 'queue'], ['auth', 'permission:report.view']);
$router->get('/reports/engineer', [ReportController::class, 'engineer'], ['auth', 'permission:report.view']);
$router->get('/reports/procurement', [ReportController::class, 'procurement'], ['auth', 'permission:report.view']);
$router->get('/reports/proposals', [ReportController::class, 'proposals'], ['auth', 'permission:report.view']);
$router->get('/reports/follow-ups', [ReportController::class, 'followUps'], ['auth', 'permission:report.view']);
$router->get('/reports/deals', [ReportController::class, 'deals'], ['auth', 'permission:report.view']);
$router->get('/reports/performance', [ReportController::class, 'performance'], ['auth', 'permission:report.view']);

$router->get('/api/dashboard/summary', [DashboardApiController::class, 'summary'], ['auth']);

$router->get('/api/notifications/summary', [NotificationApiController::class, 'summary'], ['auth']);
$router->get('/api/notifications', [NotificationApiController::class, 'index'], ['auth']);
$router->post('/api/notifications/read-all', [NotificationApiController::class, 'markAllRead'], ['auth']);
$router->post('/api/notifications/{id}/read', [NotificationApiController::class, 'markRead'], ['auth']);

$router->get('/api/leads/summary', [LeadApiController::class, 'summary'], ['auth', 'permission:lead.view']);
$router->get('/api/leads/{id}/ping', [LeadApiController::class, 'ping'], ['auth', 'permission:lead.view']);
$router->post('/api/leads/{id}/status', [LeadApiController::class, 'updateStatus'], ['auth', 'permission:lead.edit']);
$router->post('/api/leads/{id}/priority', [LeadApiController::class, 'updatePriority'], ['auth', 'permission:lead.edit']);
$router->post('/api/leads/{id}/assign', [LeadApiController::class, 'assign'], ['auth', 'permission:lead.assign']);
$router->post('/api/leads/{id}/follow-up-date', [LeadApiController::class, 'updateFollowUpDate'], ['auth', 'permission:lead.edit']);

$router->get('/api/queue/summary', [QueueApiController::class, 'summary'], ['auth', 'permission:queue.view']);
$router->get('/api/queue/{id}/ping', [QueueApiController::class, 'ping'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/status', [QueueApiController::class, 'updateStatus'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/priority', [QueueApiController::class, 'updatePriority'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/assign', [QueueApiController::class, 'assign'], ['auth', 'permission:queue.manage']);
$router->post('/api/queue/{id}/deadline', [QueueApiController::class, 'updateDeadline'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/follow-up-date', [QueueApiController::class, 'updateFollowUpDate'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/task-name', [QueueApiController::class, 'updateTaskName'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/survey-status', [QueueApiController::class, 'updateSurveyStatus'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/stage', [QueueApiController::class, 'updateStage'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/estimator', [QueueApiController::class, 'updateEstimator'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/surveyor', [QueueApiController::class, 'updateSurveyor'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/notes-field', [QueueApiController::class, 'updateNotes'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/engineering-start-date', [QueueApiController::class, 'updateEngineeringStartDate'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/engineering-end-date', [QueueApiController::class, 'updateEngineeringEndDate'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/procurement-start-date', [QueueApiController::class, 'updateProcurementStartDate'], ['auth', 'permission:queue.view']);
$router->post('/api/queue/{id}/procurement-end-date', [QueueApiController::class, 'updateProcurementEndDate'], ['auth', 'permission:queue.view']);

$router->get('/api/engineer/summary', [EngineerApiController::class, 'summary'], ['auth', 'permission:engineer.view']);
$router->get('/api/engineer/{id}/ping', [EngineerApiController::class, 'ping'], ['auth', 'permission:engineer.view']);
$router->post('/api/engineer/{id}/priority', [EngineerApiController::class, 'updatePriority'], ['auth', 'permission:engineer.manage']);
$router->post('/api/engineer/{id}/deadline', [EngineerApiController::class, 'updateDeadline'], ['auth', 'permission:engineer.manage']);
$router->post('/api/engineer/{id}/reassign', [EngineerApiController::class, 'reassign'], ['auth', 'permission:engineer.manage']);

$router->get('/api/procurement/summary', [ProcurementApiController::class, 'summary'], ['auth', 'permission:procurement.view']);
$router->get('/api/procurement/{id}/ping', [ProcurementApiController::class, 'ping'], ['auth', 'permission:procurement.view']);
$router->post('/api/procurement/{id}/priority', [ProcurementApiController::class, 'updatePriority'], ['auth', 'permission:procurement.manage']);
$router->post('/api/procurement/{id}/deadline', [ProcurementApiController::class, 'updateDeadline'], ['auth', 'permission:procurement.manage']);
$router->post('/api/procurement/{id}/reassign', [ProcurementApiController::class, 'reassign'], ['auth', 'permission:procurement.manage']);

$router->get('/api/proposals/summary', [ProposalApiController::class, 'summary'], ['auth', 'permission:proposal.view']);
$router->get('/api/proposals/{id}/ping', [ProposalApiController::class, 'ping'], ['auth', 'permission:proposal.view']);
