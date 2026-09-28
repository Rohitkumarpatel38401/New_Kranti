<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\EnrollmentStatus;
use App\Enums\FranchiseApplicationStatus;
use App\Enums\RechargeStatus;
use App\Filament\Admin\Resources\Enrollments\EnrollmentResource;
use App\Filament\Admin\Resources\FranchiseApplications\FranchiseApplicationResource;
use App\Filament\Admin\Resources\WalletRechargeRequests\WalletRechargeRequestResource;
use App\Models\Enrollment;
use App\Models\FranchiseApplication;
use App\Models\WalletRechargeRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * HQ's to-do list: everything waiting on an admin decision.
 */
class PendingApprovalsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        $admin = auth('admin')->user();

        return array_values(array_filter([
            $admin?->can('viewAny', Enrollment::class) ? Stat::make('Enrollments awaiting approval',
                Enrollment::query()->where('status', EnrollmentStatus::PendingApproval)->count())
                ->url(EnrollmentResource::getUrl('index', ['filters' => ['status' => ['value' => EnrollmentStatus::PendingApproval->value]]])) : null,
            $admin?->can('viewAny', WalletRechargeRequest::class) ? Stat::make('Wallet recharges to verify',
                WalletRechargeRequest::query()->where('status', RechargeStatus::Pending)->count())
                ->url(WalletRechargeRequestResource::getUrl('index')) : null,
            $admin?->can('viewAny', FranchiseApplication::class) ? Stat::make('New franchise applications',
                FranchiseApplication::query()->where('status', FranchiseApplicationStatus::New)->count())
                ->url(FranchiseApplicationResource::getUrl('index')) : null,
        ]));
    }
}
