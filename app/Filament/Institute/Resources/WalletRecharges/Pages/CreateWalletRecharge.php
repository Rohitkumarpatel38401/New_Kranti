<?php

namespace App\Filament\Institute\Resources\WalletRecharges\Pages;

use App\Exceptions\DomainRuleException;
use App\Filament\Institute\Resources\WalletRecharges\WalletRechargeResource;
use App\Services\WalletRechargeService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateWalletRecharge extends CreateRecord
{
    protected static string $resource = WalletRechargeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(WalletRechargeService::class)->request($data, auth('institute')->user());
        } catch (DomainRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
