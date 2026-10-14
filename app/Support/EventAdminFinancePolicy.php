<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;

class EventAdminFinancePolicy
{
    public static function assertRequestAllowed(Request $request): void
    {
        $path = $request->path();
        $permission = TenantUserCatalog::writePermissionForPath($path);
        $financePath = preg_match('#(?:^|/)(?:finance|fees?|school-fees|ledger|payments?|invoices?|receipts?|refunds?|food-billing|food-payments|state-remittances)(?:[/-]|$)#i', $path);
        $financeReport = collect($request->query())->contains(function ($value, $key) {
            return preg_match('/report|type|export/i', (string) $key) && is_string($value)
                && preg_match('/finance|fee|payment|invoice|receipt|refund|ledger|food.bill/i', $value);
        });
        abort_if($permission === 'fest.finance' || $financePath || $financeReport, 403,
            'This event admin account does not have finance access.');

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            self::assertNoFinancialFields($request->all());
        }
    }

    private static function assertNoFinancialFields(array $data): void
    {
        foreach ($data as $key => $value) {
            abort_if(is_string($key) && self::isFinancialKey($key), 403, 'This account cannot change financial data.');
            if (is_array($value)) {
                self::assertNoFinancialFields($value);
            }
        }
    }

    public static function isFinancialKey(string $key): bool
    {
        $key = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));

        return $key === 'grand_total' || (bool) preg_match('/(?:^|_)(?:fee|fees|finance|financial|payment|payments|invoice|invoices|receipt|receipts|refund|refunds|ledger|bank|banking|upi|price|pricing|cost|amount|paid|due|collected|balance|revenue|expense|expenses|payable|payables|waiver|waivers|arrears|remittance|remittances|beneficiary|ifsc|tax|discount)(?:_|$)/', $key);
    }

    public static function withoutFinance(mixed $data): mixed
    {
        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        }
        if (! is_array($data)) {
            return $data;
        }
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isFinancialKey($key)) {
                unset($data[$key]);
            } else {
                $data[$key] = self::withoutFinance($value);
            }
        }

        return $data;
    }
}
