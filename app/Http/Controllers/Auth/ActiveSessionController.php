<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class ActiveSessionController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $currentSessionId = $this->getCurrentSessionId($request);

        $rows = DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        $data = $rows->map(static function (object $row) use ($currentSessionId): array {
            $lastActivity = $row->last_activity;
            $isoDate = is_numeric($lastActivity)
                ? Carbon::createFromTimestamp((int) $lastActivity)->toISOString()
                : null;

            $rowId = is_string($row->id) ? $row->id : (is_numeric($row->id) ? (string) $row->id : '');

            return [
                'id' => $rowId,
                'ip_address' => is_string($row->ip_address) ? $row->ip_address : null,
                'user_agent' => is_string($row->user_agent) ? $row->user_agent : null,
                'last_activity' => $isoDate,
                'is_current' => $currentSessionId !== null && $rowId === $currentSessionId,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function destroy(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $request->user();

        $deleted = DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', $id)
            ->delete();

        if ($deleted === 0) {
            abort(Response::HTTP_NOT_FOUND, 'Sessão não encontrada.');
        }

        $currentSessionId = $this->getCurrentSessionId($request);
        if ($id === $currentSessionId && $request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    public function destroyOthers(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $currentSessionId = $this->getCurrentSessionId($request);

        $query = DB::table('sessions')
            ->where('user_id', $user->getKey());

        if ($currentSessionId !== null) {
            $query->where('id', '!=', $currentSessionId);
        }

        $query->delete();

        return response()->noContent();
    }

    private function getCurrentSessionId(Request $request): ?string
    {
        if ($request->hasSession()) {
            return $request->session()->getId();
        }

        if (app()->bound('session')) {
            $session = app('session');

            return $session->isStarted() ? $session->getId() : null;
        }

        return null;
    }
}
