<?php namespace App\Ninja\Import\FreshBooks;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class InvoiceTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ( ! $this->getClientId($data->organization)) {
            return false;
        }

        if ($this->hasInvoice($data->invoice_number)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->organization),
                'invoice_number' => $this->getInvoiceNumber($data->invoice_number),
                'paid' => (float) $data->paid,
                'po_number' => $data->po_number,
                'terms' => $data->terms,
                'public_notes' => $data->notes,
                'invoice_date_sql' => $data->create_date,
                'invoice_items' => [
                    [
                        'product_key' => '',
                        'notes' => $data->notes,
                        'cost' => (float) $data->amount,
                        'qty' => 1,
                    ]
                ],
            ];
        });
    }
}