<?php namespace App\Models;

use Eloquent;
use Auth;
use Cache;

/**
 * Class InvoiceDesign
 */
class InvoiceDesign extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return mixed
     */
    public static function getDesigns()
    {
        $account = Auth::user()->account;
        $designs = Cache::get('invoiceDesigns');
        $utf8 = $forceUtf8 || $account->utf8_invoices;

        foreach ($designs as $design) {
            if ($design->id > Auth::user()->maxInvoiceDesignId()) {
                $designs->pull($design->id);
            }

            if ($utf8) {
                $design->javascript = $design->pdfmake;
            }
            $design->pdfmake = null;

            if ($design->id == CUSTOM_DESIGN) {
                if ($utf8 && $account->custom_design) {
                    $design->javascript = $account->custom_design;
                } else {
                    $designs->pop();
                }
            }
        }

        return $designs;
    }
}
