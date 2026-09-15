<?php

namespace App\Controllers\Api;

use App\Core\Acl;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Proposal;

class ProposalApiController extends Controller
{
    /** Polled by the proposal dashboard to keep the tab counts live. */
    public function summary(Request $request): void
    {
        $scopeSalesId = Acl::hasRole('sales') ? Auth::id() : null;

        $this->json([
            'counts' => Proposal::dashboardCounts($scopeSalesId),
            'server_time' => date('c'),
        ]);
    }

    /** Polled by the proposal detail page to notice out-of-band changes (e.g. Manager approving elsewhere). */
    public function ping(Request $request, array $params): void
    {
        $proposal = $this->authorizedProposal((int) $params['id']);
        if ($proposal === null) {
            return;
        }

        $this->json([
            'status' => $proposal['status'],
            'total' => $proposal['total'],
            'updated_at' => $proposal['updated_at'],
        ]);
    }

    private function authorizedProposal(int $id): ?array
    {
        $proposal = Proposal::withRelations($id);

        if ($proposal === null) {
            $this->json(['error' => 'not_found'], 404);

            return null;
        }

        if (Acl::hasRole('sales') && (int) $proposal['sales_id'] !== Auth::id()) {
            $this->json(['error' => 'forbidden'], 403);

            return null;
        }

        return $proposal;
    }
}
