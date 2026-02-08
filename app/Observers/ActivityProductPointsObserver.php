<?php

namespace App\Observers;

use App\Models\ActivityProduct;
use App\Services\UserPointsService;

class ActivityProductPointsObserver
{
    public function __construct(
        private UserPointsService $userPointsService
    ) {}

    public function created(ActivityProduct $activityProduct): void
    {
        $this->userPointsService->grantProductPoints($activityProduct);
    }

    public function deleted(ActivityProduct $activityProduct): void
    {
        $this->userPointsService->reverseProductPoints($activityProduct);
    }
}
