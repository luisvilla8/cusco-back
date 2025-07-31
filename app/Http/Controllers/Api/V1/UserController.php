<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Controllers\Api\V1\UserController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Http\Requests\Api\V1\User\StoreUserRequest;
use App\Http\Requests\Api\V1\User\UpdateUserRequest;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends BaseController
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Store a newly created user (registro público)
     */
    public function store(StoreUserRequest $request)
    {
        $result = $this->userService->createUser($request->validated());

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendCreated(
            new UserResource($result['data']),
            $result['message']
        );
    }

    /**
     * Display a listing of active users.
     */
    public function index(Request $request)
    {
        $result = $this->userService->getAllUsers($request->all());

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return response()->json([
            'success' => $result['success'],
            'data' => $result['data'],
            'meta' => $result['meta'],
            'message' => $result['message']
        ]);
    }

    /**
     * Display a listing of trashed users.
     */
    public function trashed(Request $request)
    {
        $result = $this->userService->getTrashedUsers($request->all());

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return response()->json([
            'success' => $result['success'],
            'data' => $result['data'],
            'meta' => $result['meta'],
            'message' => $result['message']
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(int $id)
    {
        $result = $this->userService->getUser($id);

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse(
            new UserResource($result['data']),
            $result['message']
        );
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, int $id)
    {
        $result = $this->userService->updateUser($id, $request->validated());

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse(
            new UserResource($result['data']),
            $result['message']
        );
    }

    /**
     * Soft delete the specified user.
     */
    public function destroy(int $id)
    {
        $result = $this->userService->deleteUser($id);

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse([], $result['message']);
    }

    /**
     * Restore the specified user.
     */
    public function restore(int $id)
    {
        $result = $this->userService->restoreUser($id);

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse(
            new UserResource($result['data']),
            $result['message']
        );
    }

    /**
     * Permanently delete the specified user.
     */
    public function forceDelete(int $id)
    {
        $result = $this->userService->forceDeleteUser($id);

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse([], $result['message']);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request, int $id)
    {
        $result = $this->userService->changePassword($id, $request->all());

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse([], $result['message']);
    }
}