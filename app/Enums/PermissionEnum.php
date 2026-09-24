<?php

namespace App\Enums;

enum PermissionEnum: string
{
    case FoliosView = 'folios.view';
    case FoliosAddCharge = 'folios.add_charge';
    case FoliosRecordPayment = 'folios.record_payment';
    case FoliosClose = 'folios.close';
    case FoliosVoidCharge = 'folios.void_charge';
    case FoliosRefundPayment = 'folios.refund_payment';
    case FoliosReopen = 'folios.reopen';
    case FoliosCheckoutOutstanding = 'folios.checkout_outstanding';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
}
