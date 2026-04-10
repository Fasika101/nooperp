<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendanceEntry;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AttendanceEntryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AttendanceEntry');
    }

    public function view(AuthUser $authUser, AttendanceEntry $attendanceEntry): bool
    {
        return $authUser->can('View:AttendanceEntry');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AttendanceEntry');
    }

    public function update(AuthUser $authUser, AttendanceEntry $attendanceEntry): bool
    {
        return $authUser->can('Update:AttendanceEntry');
    }

    public function delete(AuthUser $authUser, AttendanceEntry $attendanceEntry): bool
    {
        return $authUser->can('Delete:AttendanceEntry');
    }

    public function restore(AuthUser $authUser, AttendanceEntry $attendanceEntry): bool
    {
        return $authUser->can('Restore:AttendanceEntry');
    }

    public function forceDelete(AuthUser $authUser, AttendanceEntry $attendanceEntry): bool
    {
        return $authUser->can('ForceDelete:AttendanceEntry');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AttendanceEntry');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AttendanceEntry');
    }

    public function replicate(AuthUser $authUser, AttendanceEntry $attendanceEntry): bool
    {
        return $authUser->can('Replicate:AttendanceEntry');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AttendanceEntry');
    }
}
